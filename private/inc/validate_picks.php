<?php
declare(strict_types=1);

/**
 * validate_picks.php
 * Centralized validator for picks. Returns a structured array:
 * [
 *   'ok' => bool,
 *   'errors' => [string, ...],
 *   'details' => [ [ 'team' => 'PHI', 'game_id' => 123, 'valid' => true, 'reasons' => [] , 'fav_team' => 'PHI', 'dog_team' => 'DAL', 'spread' => 3.0, 'state' => 'pre'], ... ],
 *   'summary' => [ 'games_found' => 5, 'lines_found' => 5 ]
 * ]
 */
function hsc_normalize_team(string $t): string {
  $t = strtoupper(trim($t));
  // accept common synonyms
  $map = [
    'JAX'=>'JAX','JAC'=>'JAX',
    'LA'=>'LAR', 'LAR'=>'LAR', 'RAMS'=>'LAR',
    'LAC'=>'LAC', 'SD'=>'LAC',
    'WSH'=>'WAS','WFT'=>'WAS',
    'NO'=>'NO','NOR'=>'NO',
    'GB'=>'GB','GNB'=>'GB',
    'KC'=>'KC','KAN'=>'KC',
    'TB'=>'TB','TAM'=>'TB',
    'NE'=>'NE','NWE'=>'NE',
    'NYG'=>'NYG','NYJ'=>'NYJ',
  ];
  return $map[$t] ?? $t;
}

function hsc_validate_picks(PDO $db, int $season, int $week, int $userId, array $teams): array {
  $errors = [];
  $details = [];

  // normalize teams (drop empties)
  $teams = array_values(array_filter(array_map('hsc_normalize_team', $teams), fn($x)=>$x!==''));
  if (count($teams) !== 5) {
    $errors[] = "must_pick_exactly_5";
  }

  // no duplicates
  if (count(array_unique($teams)) !== count($teams)) {
    $errors[] = "duplicate_teams_selected";
  }

  // get all games + lines for the week
  $sql = "SELECT g.id AS game_id, g.home_team, g.away_team, g.state, l.fav_team, l.spread, (CASE WHEN l.fav_team = g.home_team THEN g.away_team ELSE g.home_team END) AS dog_team
          FROM games g
          JOIN `lines` l ON l.game_id = g.id
          WHERE g.season_year = ? AND g.week_number = ?";
  $stmt = $db->prepare($sql);
  $stmt->execute([$season, $week]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $byTeam = [];
  foreach ($rows as $r) {
    // index both sides to this game/line
    $fav = strtoupper($r['fav_team']);
    $dog = strtoupper($r['dog_team']);
    $byTeam[$fav] = $r;
    $byTeam[$dog] = $r;
  }

  $seenGames = [];
  foreach ($teams as $idx => $abbr) {
    $entry = ['index'=>$idx, 'team'=>$abbr, 'valid'=>true, 'reasons'=>[]];
    if (!isset($byTeam[$abbr])) {
      $entry['valid'] = false;
      $entry['reasons'][] = 'team_not_in_week_lines';
      $details[] = $entry;
      continue;
    }
    $r = $byTeam[$abbr];
    $entry['game_id']  = (int)$r['game_id'];
    $entry['fav_team'] = strtoupper($r['fav_team']);
    $entry['dog_team'] = strtoupper($r['dog_team']);
    $entry['spread']   = (float)$r['spread'];
    $entry['state']    = $r['state'];

    if (in_array($r['state'], ['in','final'], true)) {
      $entry['valid'] = false;
      $entry['reasons'][] = 'game_already_started_or_final';
    }

    // can't pick both sides of same game
    if (in_array($r['game_id'], $seenGames, true)) {
      $entry['valid'] = false;
      $entry['reasons'][] = 'both_sides_same_game';
    } else {
      $seenGames[] = $r['game_id'];
    }

    $details[] = $entry;
  }

  // admin grace: no check here; submit handler may allow admin override

  // summary
  $lines_found = 0;
  foreach ($details as $d) if (!empty($d['game_id'])) $lines_found++;
  $ok = empty($errors) && !empty($details) && array_reduce($details, fn($carry,$d)=>$carry && $d['valid'], true);

  if (!$ok && empty($errors)) $errors[] = 'invalid_selection';

  return [
    'ok' => $ok,
    'errors' => $errors,
    'summary' => ['games_found' => count($details), 'lines_found' => $lines_found],
    'details' => $details,
  ];
}
