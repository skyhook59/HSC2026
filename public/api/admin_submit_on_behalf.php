<?php
require __DIR__ . '/../../private/inc/db.php';
require __DIR__ . '/../../private/inc/auth_guard.php';
require __DIR__ . '/../../private/inc/csrf.php';
admin_required();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Allow: POST');
  http_response_code(405);
  echo "Method not allowed.";
  exit;
}

// CSRF protection
csrf_protect();

$asUser = (int)($_POST['user_id'] ?? 0);
$season = (int)($_POST['season'] ?? 0);
$week   = (int)($_POST['week'] ?? 0);
$picksAbbr = trim($_POST['picks_abbr'] ?? '');

// Validate basic inputs
if (!$asUser || !$season || !$week || !$picksAbbr){ http_response_code(400); echo "Bad input"; exit; }

// Normalize and split abbreviations
$abbrs = array_filter(array_map(function($s){ return strtoupper(trim($s)); }, explode(',', $picksAbbr)));
$abbrs = array_values(array_unique($abbrs)); // dedupe

if (count($abbrs) !== 5) { http_response_code(422); echo "Exactly 5 unique team abbreviations are required."; exit; }

// Valid NFL 3-letter codes (use your system's codes)
$TEAMS = ['ARI','ATL','BAL','BUF','CAR','CHI','CIN','CLE','DAL','DEN','DET','GB','HOU','IND','JAX','KC','LAC','LAR','LV','MIA','MIN','NE','NO','NYG','NYJ','PHI','PIT','SEA','SF','TB','TEN','WAS'];
foreach ($abbrs as $a) {
  if (!in_array($a, $TEAMS, true)) { http_response_code(422); echo "Invalid team code: $a"; exit; }
}

// Build picks by looking up each team's game that week
$picks = [];
$gameIds = [];
foreach ($abbrs as $a) {
  $stmt = $db->prepare("SELECT id, home_team, away_team FROM games WHERE season_year=? AND week_number=? AND (home_team=? OR away_team=?) LIMIT 1");
  $stmt->execute([$season, $week, $a, $a]);
  $g = $stmt->fetch();
  if (!$g) { http_response_code(422); echo "No game found for $a in week $week"; exit; }
  $gid = (int)$g['id'];
  // Prevent picking both sides of same game
  if (isset($gameIds[$gid])) { http_response_code(422); echo "You cannot pick both sides of the same game (conflict at game ID $gid)."; exit; }

  $gameIds[$gid] = true;
  $picks[] = ['game_id' => $gid, 'picked_team' => $a];
}

// Admin override: replace any existing picks for that user/week (skip time checks)
$db->beginTransaction();
try {
  // 1. Insert/update the pick header row in the 'picks' table
  $db->prepare("INSERT INTO picks (user_id, season_year, week_number, submitted_at_utc, admin_override)
                VALUES (?, ?, ?, UTC_TIMESTAMP(), 1)
                ON DUPLICATE KEY UPDATE submitted_at_utc=VALUES(submitted_at_utc), admin_override=1")
     ->execute([$asUser, $season, $week]);

  // 2. Get the pick_id from the new or existing row
  $q = $db->prepare("SELECT id FROM picks WHERE user_id=? AND season_year=? AND week_number=?");
  $q->execute([$asUser, $season, $week]);
  $pickId = (int)$q->fetchColumn();

  if ($pickId === 0) {
      throw new RuntimeException('Failed to obtain pick_id for admin submission.');
  }

  // 3. Delete old selections for this pick_id
  $db->prepare("DELETE FROM pick_selections WHERE pick_id=?")->execute([$pickId]);

  // 4. Insert new selections into 'pick_selections' table
  $ins = $db->prepare("INSERT INTO pick_selections (pick_id, game_id, team_abbr) VALUES (?, ?, ?)");
  foreach ($picks as $p){
    $ins->execute([$pickId, $p['game_id'], $p['picked_team']]);
  }

  $db->commit();
  redirect('admin.php');
} catch (Throwable $e) {
  if ($db->inTransaction()) $db->rollBack();
  error_log('Admin pick submission failed: ' . $e->getMessage());
  http_response_code(500);
  echo "Failed to save picks.";
  exit;
}
