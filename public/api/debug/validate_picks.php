<?php
declare(strict_types=1);
header('Content-Type: application/json');
require __DIR__ . '/../../../private/inc/db.php';
require __DIR__ . '/../../../private/inc/week.php';
require __DIR__ . '/../../../private/inc/validate_picks.php';

// Optional feed secret guard (comment out if you want it public for now)
if (defined('FEED_SECRET')) {
  $secret = $_SERVER['HTTP_X_FEED_SECRET'] ?? '';
  if (!$secret || !hash_equals(FEED_SECRET, $secret)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'forbidden']);
    exit;
  }
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$userId = (int)($input['user_id'] ?? 0);
$teams  = $input['teams'] ?? [];
$weekQ  = isset($_GET['week']) ? (int)$_GET['week'] : null;
$seasonQ= isset($_GET['season']) ? (int)$_GET['season'] : null;

if ($seasonQ && $weekQ) { $season=$seasonQ; $week=$weekQ; }
else { [$season,$week] = current_season_week($db); }

$result = hsc_validate_picks($db, (int)$season, (int)$week, $userId, $teams);
echo json_encode(['ok'=>$result['ok'], 'season'=>$season, 'week'=>$week, 'result'=>$result], JSON_PRETTY_PRINT);
