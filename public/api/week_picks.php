<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json');

require __DIR__ . '/../../private/inc/db.php';
require __DIR__ . '/../../private/inc/auth_guard.php';
require __DIR__ . '/../../private/inc/week.php';
require __DIR__ . '/../../private/inc/week_lock_helpers.php';

api_auth_required();

$userId = (int)($_SESSION['user_id'] ?? 0);

// --- Determine season/week from query, with sane defaults ---
[$AUTO_SEASON, $AUTO_WEEK, $status] = current_season_week($db);

$season = isset($_GET['season']) ? (int)$_GET['season'] : $AUTO_SEASON;
$week   = isset($_GET['week'])   ? (int)$_GET['week']   : $AUTO_WEEK;

// Clamp to valid 1–18 just in case
if ($week < 1)  $week = 1;
if ($week > 18) $week = 18;

// --- Figure out if this week is locked ---
$locked = is_week_locked($db, $season, $week);

// --- Build query: always this season/week, lock only controls visibility ---
$sql = "
    SELECT
        u.id             AS user_id,
        u.name           AS user_name,
        g.id             AS game_id,
        g.season_year,
        g.week_number,
        g.kickoff_utc,
        g.home_team,
        g.away_team,
        g.home_score,
        g.away_score,
        g.state,
        ps.team_abbr     AS picked_team,
        l.fav_team,
        l.dog_team,
        l.spread
    FROM picks p
    JOIN users u           ON u.id = p.user_id
    JOIN pick_selections ps ON ps.pick_id = p.id
    JOIN games g           ON g.id = ps.game_id
    LEFT JOIN `lines` l      ON l.game_id = ps.game_id
    WHERE g.season_year = :season
      AND g.week_number = :week
";

// Before lock → only show current user’s picks
$params = [
    ':season' => $season,
    ':week'   => $week,
];

if (!$locked) {
    $sql .= " AND p.user_id = :uid";
    $params[':uid'] = $userId;
}

$sql .= "
    ORDER BY
      u.name ASC,
      g.kickoff_utc ASC,
      g.id ASC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Normalize / cast some fields
foreach ($rows as &$r) {
    $r['season_year'] = (int)$r['season_year'];
    $r['week_number'] = (int)$r['week_number'];
    $r['game_id']     = (int)$r['game_id'];
    $r['home_score']  = isset($r['home_score']) ? (int)$r['home_score'] : null;
    $r['away_score']  = isset($r['away_score']) ? (int)$r['away_score'] : null;
}
unset($r);

echo json_encode([
    'ok'      => true,
    'season'  => $season,
    'week'    => $week,
    'locked'  => $locked,
    'results' => $rows,
]);
exit;
