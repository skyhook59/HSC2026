<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: application/json');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require __DIR__ . '/../../private/inc/db.php';
require __DIR__ . '/../../private/inc/week.php';

/* Auth */
$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$userId) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth_required']);
    exit;
}

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
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error', 'message' => $e->getMessage()]);
}