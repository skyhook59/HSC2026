<?php
// /private/cli/dump_week.php
require __DIR__ . '/../inc/db.php';

$season = (int)($argv[1] ?? date('Y'));
$week   = (int)($argv[2] ?? 1);

echo "GAMES:\n";
$stmt = $db->prepare("SELECT id, home_team, away_team FROM games WHERE season_year=? AND week_number=? ORDER BY id");
$stmt->execute([$season,$week]);
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  printf("  #%d: %s vs %s\n", $r['id'], $r['away_team'], $r['home_team']);
}

echo "\nLINES:\n";
$stmt = $db->prepare("SELECT l.game_id, l.fav_team, l.spread FROM `lines` l JOIN games g ON g.id=l.game_id WHERE g.season_year=? AND g.week_number=? ORDER BY l.game_id");
$stmt->execute([$season,$week]);
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  printf("  game %d: fav=%s spread=%.1f\n", $r['game_id'], $r['fav_team'], $r['spread']);
}
