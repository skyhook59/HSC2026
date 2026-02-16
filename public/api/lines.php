<?php
require __DIR__ . '/../../private/inc/db.php';
$season = (int)($_GET['season'] ?? date('Y'));
$week   = (int)($_GET['week'] ?? 1);
$includeStarted = isset($_GET['include_started']) && $_GET['include_started']==='1';

$q = "SELECT l.*, g.id AS game_id, g.kickoff_utc, g.state, g.home_team, g.away_team
      FROM `lines` l JOIN games g ON g.id=l.game_id
      WHERE g.season_year=? AND g.week_number=? ";
if (!$includeStarted) $q .= "AND g.state='pre' ";
$q .= "ORDER BY g.kickoff_utc";
$stmt = $db->prepare($q);
$stmt->execute([$season,$week]);
header('Content-Type: application/json');
echo json_encode($stmt->fetchAll());
