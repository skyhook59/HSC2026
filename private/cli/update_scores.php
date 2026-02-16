#!/usr/bin/env php
<?php
require __DIR__ . '/../inc/db.php';
$api = 'https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard';
$json = @file_get_contents($api);
if ($json === false) { fwrite(STDERR, "Failed to fetch ESPN\n"); exit(1); }
$payload = json_decode($json, true);
if (!$payload || empty($payload['events'])) { echo "No events\n"; exit; }

function abbr3($code) { static $map=['JAC'=>'JAX','WSH'=>'WAS','LA'=>'LAR']; $c=strtoupper($code); return $map[$c] ?? $c; }

$updated = 0;
foreach ($payload['events'] as $ev) {
  foreach ($ev['competitions'] as $comp) {
    $state = $comp['status']['type']['state'] ?? 'pre';
    $gameState = ($state === 'pre') ? 'pre' : (($state === 'post') ? 'final' : 'in_progress');
    $home = null; $away = null; $hs = 0; $as = 0;
    foreach ($comp['competitors'] as $t) {
      $abbr = abbr3($t['team']['abbreviation']);
      $score = isset($t['score']) ? (int)$t['score'] : 0;
      if (($t['homeAway'] ?? '') === 'home') { $home = $abbr; $hs = $score; }
      else { $away = $abbr; $as = $score; }
    }
    if (!$home || !$away) continue;
    $stmt = $db->prepare("UPDATE games SET home_score=?, away_score=?, state=? WHERE home_team=? AND away_team=? AND state <> 'final'");
    $stmt->execute([$hs,$as,$gameState,$home,$away]);
    $updated += $stmt->rowCount();
  }
}
echo "Updated {$updated} rows\n";
