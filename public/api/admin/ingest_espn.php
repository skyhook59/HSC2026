<?php
declare(strict_types=1);
ini_set('display_errors', '1'); error_reporting(E_ALL);
header('Content-Type: application/json');

// --- Auth via X-FEED-SECRET ---
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require __DIR__ . '/../../../private/inc/db.php';   // adjust path if needed

/*
// Read FEED_SECRET from define() or env
$cfgSecret = defined('FEED_SECRET') ? FEED_SECRET : getenv('FEED_SECRET');
$hdrSecret = $_SERVER['HTTP_X_FEED_SECRET'] ?? '';
$qsSecret  = $_GET['secret'] ?? ''; // optional fallback for quick tests
if (!$cfgSecret || $hdrSecret !== $cfgSecret) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'unauthorized']);
  exit;
}
*/
// --- Inputs ---
$season      = isset($_GET['season']) ? (int)$_GET['season'] : (int)date('Y');
$seasontype  = isset($_GET['seasontype']) ? (int)$_GET['seasontype'] : 2; // 2 = regular season
$week        = isset($_GET['week']) ? (int)$_GET['week'] : null;          // if null and mode=all/range, we loop
$mode        = $_GET['mode'] ?? 'single'; // single | all | range
$fromWeek    = isset($_GET['from']) ? (int)$_GET['from'] : 1;
$toWeek      = isset($_GET['to'])   ? (int)$_GET['to']   : 18;

if ($mode === 'single' && !$week) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'week_required_for_single']);
  exit;
}

// --- Helpers ---
function map_abbr(string $abbr): string {
  $a = strtoupper(trim($abbr));
  // Normalize a few historical/alt codes
  static $map = [
    'WSH' => 'WAS',
    'JAC' => 'JAX',
    'LA'  => 'LAR', // ESPN usually uses LAC/LAR; this is a safety
  ];
  return $map[$a] ?? $a;
}
function map_state(?string $espn): string {
  // ESPN status.type.state: "pre", "in", "post"
  switch (strtolower((string)$espn)) {
    case 'in':   return 'in';
    case 'post': return 'final';
    default:     return 'pre';
  }
}
function fetch_json(string $url): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_USERAGENT => 'HSC-Ingest/1.0',
  ]);
  $body = curl_exec($ch);
  $err  = curl_error($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($err || $code >= 400 || !$body) {
    throw new RuntimeException("fetch_failed code=$code err=$err url=$url");
  }
  $data = json_decode($body, true);
  if (!is_array($data)) throw new RuntimeException("bad_json url=$url");
  return $data;
}

// --- Upsert one week from ESPN ---
function upsert_week(PDO $db, int $season, int $week, int $seasontype = 2): array {
  $base = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard";
  $url  = $base . "?seasontype={$seasontype}&week={$week}&dates={$season}";
  $data = fetch_json($url);

  $events = $data['events'] ?? [];
  $ins = 0; $upd = 0; $skp = 0;

  $sel = $db->prepare("SELECT id FROM games WHERE season_year=? AND week_number=? AND home_team=? AND away_team=?");
  $insStmt = $db->prepare("
    INSERT INTO games (season_year, week_number, home_team, away_team, kickoff_utc, state)
    VALUES (?, ?, ?, ?, ?, ?)
  ");
  $updStmt = $db->prepare("
    UPDATE games
       SET kickoff_utc = ?, state = ?
     WHERE id = ?
  ");

  foreach ($events as $ev) {
    // Each event has competitions[0] with competitors & date
    $comp = $ev['competitions'][0] ?? null;
    if (!$comp) { $skp++; continue; }

    $dateIso  = $comp['date'] ?? null; // ISO8601 "2025-09-07T17:00Z"
    $status   = $comp['status']['type']['state'] ?? null;

    $home = null; $away = null;
    foreach (($comp['competitors'] ?? []) as $c) {
      $abbr = map_abbr($c['team']['abbreviation'] ?? '');
      if (!$abbr) continue;
      $ha = strtolower($c['homeAway'] ?? '');
      if ($ha === 'home') $home = $abbr;
      if ($ha === 'away') $away = $abbr;
    }
    if (!$home || !$away) { $skp++; continue; }

    // kickoff_utc as DATETIME (UTC)
    $kickUtc = null;
    if ($dateIso) {
      try {
        $dt = new DateTime($dateIso);
        $dt->setTimezone(new DateTimeZone('UTC'));
        $kickUtc = $dt->format('Y-m-d H:i:s');
      } catch (Throwable $e) { $kickUtc = null; }
    }
    $state = map_state($status);

    // Find existing row by (season, week, home, away)
    $sel->execute([$season, $week, $home, $away]);
    $id = (int)$sel->fetchColumn();

    if ($id > 0) {
      $updStmt->execute([$kickUtc, $state, $id]);
      $upd++;
    } else {
      $insStmt->execute([$season, $week, $home, $away, $kickUtc, $state]);
      $ins++;
    }
  }

  return ['inserted' => $ins, 'updated' => $upd, 'skipped' => $skp, 'events' => count($events)];
}

// --- Dispatcher ---
$result = ['ok'=>true, 'season'=>$season, 'seasontype'=>$seasontype, 'mode'=>$mode];
$tot = ['inserted'=>0,'updated'=>0,'skipped'=>0,'weeks'=>[]];

try {
  if ($mode === 'single') {
    $r = upsert_week($db, $season, (int)$week, $seasontype);
    $tot['inserted'] += $r['inserted'];
    $tot['updated']  += $r['updated'];
    $tot['skipped']  += $r['skipped'];
    $tot['weeks'] [] = ['week'=>(int)$week] + $r;
  } elseif ($mode === 'range') {
    if ($fromWeek > $toWeek) { [$fromWeek, $toWeek] = [$toWeek, $fromWeek]; }
    for ($w = $fromWeek; $w <= $toWeek; $w++) {
      $r = upsert_week($db, $season, $w, $seasontype);
      $tot['inserted'] += $r['inserted'];
      $tot['updated']  += $r['updated'];
      $tot['skipped']  += $r['skipped'];
      $tot['weeks'] [] = ['week'=>$w] + $r;
      usleep(150000); // be polite
    }
  } elseif ($mode === 'all') {
    for ($w = 1; $w <= 18; $w++) {
      $r = upsert_week($db, $season, $w, $seasontype);
      $tot['inserted'] += $r['inserted'];
      $tot['updated']  += $r['updated'];
      $tot['skipped']  += $r['skipped'];
      $tot['weeks'] [] = ['week'=>$w] + $r;
      usleep(150000);
    }
  } else {
    throw new InvalidArgumentException('bad_mode');
  }

  $result += $tot;
  echo json_encode($result);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'ingest_failed','message'=>$e->getMessage()]);
}
