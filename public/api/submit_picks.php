<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: application/json');

// DO NOT start the session here; db.php does that in a controlled way.
require __DIR__ . '/../../private/inc/db.php';
require __DIR__ . '/../../private/inc/week.php';
require __DIR__ . '/../../private/inc/validate_picks.php';
require __DIR__ . '/../../private/inc/email.php';
require __DIR__ . '/../../private/inc/week_lock_helpers.php';

/**
 * Simple GET probe so you can quickly verify the handler is wired up.
 * Example: /api/submit_picks.php?test=1
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['test'])) {
    echo json_encode([
        'ok'      => true,
        'handler' => 'submit_picks.php',
        'version' => 'v2025-12-05-01',
    ]);
    exit;
}

try {
    // --- Auth ---
    $userId  = (int)($_SESSION['user_id'] ?? 0);
    $isAdmin = (bool)($_SESSION['is_admin'] ?? false);

    if (!$userId) {
        error_log('submit_picks: no auth session');
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'auth_required']);
        exit;
    }

    // --- Parse JSON input ---
    $raw   = file_get_contents('php://input') ?: '';
    $input = json_decode($raw, true) ?? [];

    $teams    = isset($input['teams']) && is_array($input['teams']) ? $input['teams'] : [];
    $season   = isset($input['season']) ? (int)$input['season'] : null;
    $week     = isset($input['week'])   ? (int)$input['week']   : null;
    $override = (bool)($input['admin_override'] ?? false);
    $echo     = !empty($input['echo']);

    if (!$season || !$week) {
        [$season, $week] = current_season_week($db);
    }

    error_log(sprintf(
        'submit_picks: START user_id=%d season=%d week=%d teams=%s override=%s',
        $userId,
        $season,
        $week,
        json_encode($teams),
        $override ? 'true' : 'false'
    ));

    // Basic sanity (client-side should already enforce this)
    if (count($teams) !== 5) {
        error_log('submit_picks: wrong_count count=' . count($teams));
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'must_pick_exactly_5']);
        exit;
    }
    if (count(array_unique($teams)) !== 5) {
        error_log('submit_picks: duplicate_teams ' . json_encode($teams));
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'duplicate_teams']);
        exit;
    }

    // --- Lock guard: block non-admins after lock time ---
    if (is_week_locked($db, $season, $week) && !$isAdmin) {
        error_log(sprintf(
            'submit_picks: BLOCKED BY LOCK user_id=%d season=%d week=%d',
            $userId,
            $season,
            $week
        ));
        http_response_code(403);
        echo json_encode([
            'ok'      => false,
            'error'   => 'week_locked',
            'message' => 'Picks are locked for this week. No new submissions or changes are allowed.',
            'season'  => $season,
            'week'    => $week,
        ]);
        exit;
    }

    // --- Validate picks against games + lines ---
    $val = hsc_validate_picks($db, $season, $week, $userId, $teams);

    if (empty($val['ok'])) {
        error_log('submit_picks: VALIDATION FAILED ' . json_encode($val['errors'] ?? $val));
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_picks', 'result' => $val]);
        exit;
    }

    // Expect exactly 5 valid details with game_id + team
    $details = $val['details'] ?? [];
    $validDetails = [];
    foreach ($details as $d) {
        if (!empty($d['valid']) && !empty($d['game_id']) && !empty($d['team'])) {
            $validDetails[] = $d;
        }
    }

    if (count($validDetails) !== 5) {
        error_log('submit_picks: expected_5_selections details=' . json_encode($details));
        http_response_code(400);
        echo json_encode([
            'ok'      => false,
            'error'   => 'expected_5_selections',
            'season'  => $season,
            'week'    => $week,
            'details' => $details,
        ]);
        exit;
    }

    error_log('submit_picks: validation ok');

    // --- Upsert picks header ---
    // ON DUPLICATE KEY uses uniq_user_week (user_id, season_year, week_number)
    $ins = $db->prepare("
        INSERT INTO picks (user_id, season_year, week_number, submitted_at_utc, admin_override)
        VALUES (?, ?, ?, UTC_TIMESTAMP(), ?)
        ON DUPLICATE KEY UPDATE
            submitted_at_utc = VALUES(submitted_at_utc),
            admin_override   = VALUES(admin_override)
    ");
    $ins->execute([$userId, $season, $week, $override ? 1 : 0]);

    // Resolve pick_id even if lastInsertId() returns 0 on duplicate
    $pickId = (int)$db->lastInsertId();
    if ($pickId === 0) {
        $q = $db->prepare("SELECT id FROM picks WHERE user_id=? AND season_year=? AND week_number=?");
        $q->execute([$userId, $season, $week]);
        $pickId = (int)$q->fetchColumn();
    }
    if ($pickId === 0) {
        throw new RuntimeException('Could not resolve pick_id after insert/upsert');
    }

    error_log(sprintf('submit_picks: using pick_id=%d', $pickId));

    // --- Insert selections in a transaction ---
    $db->beginTransaction();

    // Clear any prior selections for this header
    $db->prepare("DELETE FROM pick_selections WHERE pick_id = ?")->execute([$pickId]);

    $insSel = $db->prepare("
        INSERT INTO pick_selections (pick_id, game_id, team_abbr)
        VALUES (?, ?, ?)
    ");

    $inserted = 0;
    foreach ($validDetails as $d) {
        $gameId = (int)$d['game_id'];
        $team   = strtoupper((string)$d['team']);
        $insSel->execute([$pickId, $gameId, $team]);
        $inserted++;
    }

    if ($inserted !== 5) {
        throw new RuntimeException('Expected to insert 5 selections, inserted ' . $inserted);
    }

   $db->commit();
error_log(sprintf('submit_picks: inserted %d selections for pick_id=%d', $inserted, $pickId));

// --- Confirmation email ---
try {
    // Get the user's email address
    $stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userEmail = $stmt->fetchColumn();

    if ($userEmail) {
        // Build a simple HTML list of picks
        $picksList = "<ul>";
        foreach ($validDetails as $d) {
            $team = $d['team'] ?? $d['picked_team'] ?? '';
            $picksList .= "<li>" . htmlspecialchars($team, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>";
        }
        $picksList .= "</ul>";

        $subject = "Your picks for Week {$week} have been submitted!";
        $body = "Hi,<br><br>"
              . "This is a confirmation that we received your picks for Week {$week}. "
              . "Here are your selections:<br>{$picksList}<br>"
              . "Good luck!<br>Helga";

        send_email($userEmail, $subject, $body);
    }
} catch (Throwable $mailEx) {
    error_log('submit_picks: email error ' . $mailEx->getMessage());
    // Do not fail the whole request if email fails
}

echo json_encode([
    'ok'      => true,
    'season'  => $season,
    'week'    => $week,
    'pick_id' => $pickId,
    'saved'   => $inserted,
]);
exit;

} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('submit_picks: EXCEPTION ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'error'   => 'db_error',
        'message' => $e->getMessage(),
    ]);
    exit;
}