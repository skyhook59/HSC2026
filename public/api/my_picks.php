<?php
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json');

require __DIR__ . '/../../private/inc/db.php';
require __DIR__ . '/../../private/inc/auth_guard.php';
require __DIR__ . '/../../private/inc/week.php';

/* Auth */
api_auth_required();
$userId = (int)($_SESSION['user_id'] ?? 0);

$season = (int)($_GET['season'] ?? 0);
$week = (int)($_GET['week'] ?? 0);

if (!$season || !$week) {
    [$season, $week] = current_season_week($db);
}

// Fetch week info and lock status
$locked = false;
$q = $db->prepare("SELECT lock_at_utc, visible_after_lock FROM weeks WHERE season_year = ? AND week_number = ?");
$q->execute([$season, $week]);
$weekInfo = $q->fetch(PDO::FETCH_ASSOC);

if ($weekInfo && $weekInfo['visible_after_lock'] && (new DateTime('now', new DateTimeZone('UTC')) > new DateTime($weekInfo['lock_at_utc'], new DateTimeZone('UTC')))) {
    $locked = true;
}

try {
    if ($locked) {
        // SQL to fetch all picks for the week
        $sql = "
            SELECT
                u.name,
                ps.game_id,
                ps.team_abbr AS picked_team
            FROM
                picks p
            INNER JOIN
                users u ON p.user_id = u.id
            INNER JOIN
                pick_selections ps ON p.id = ps.pick_id
            WHERE
                p.season_year = ? AND p.week_number = ?
            ORDER BY
                u.name, ps.game_id
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$season, $week]);
        $picks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // SQL to fetch only the current user's picks
        $sql = "
            SELECT
                ps.game_id,
                ps.team_abbr AS picked_team
            FROM
                picks p
            INNER JOIN
                pick_selections ps ON p.id = ps.pick_id
            WHERE
                p.user_id = ? AND p.season_year = ? AND p.week_number = ?
            ORDER BY
                ps.game_id
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId, $season, $week]);
        $picks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'ok' => true,
        'locked' => $locked,
        'picks' => $picks
    ]);

} catch (PDOException $e) {
    error_log('my_picks database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error']);
}
