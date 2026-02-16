<?php
//declare(strict_types=1);

/**
 * Location: /home/public/api/admin/update_scores.php
 *
 * Web usage (silent by default; debug emits JSON):
 *   /api/admin/update_scores.php?season=2025&seasontype=2&mode=single&week=7
 *   /api/admin/update_scores.php?season=2025&seasontype=2&mode=single&week=7&debug=1
 *
 * Query:
 *   season=2025
 *   seasontype=1|2|3      (default 2)
 *   mode=single|range|all (default single)
 *   week=1                (required for single)
 *   from=1&to=18          (for range)
 *   create_if_missing=1
 *   debug=1               (optional; when present, emits JSON + errors)
 */

// ---- debug/silent behavior ----
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';
@ini_set('display_errors', $debug ? '1' : '0');
@ini_set('display_startup_errors', $debug ? '1' : '0');
@error_reporting($debug ? E_ALL : 0);

// Only set headers / print output if debugging.
$emit = $debug;

// (Only in debug) return JSON to the browser
if ($emit && !headers_sent()) {
  header('Content-Type: application/json; charset=UTF-8');
}

// ---- Auth (enable by setting FEED_SECRET const or env var) ----
$cfgSecret = defined('FEED_SECRET') ? FEED_SECRET : getenv('FEED_SECRET');
if ($cfgSecret) {
  $hdrSecret = $_SERVER['HTTP_X_FEED_SECRET'] ?? '';
  $qsSecret  = $_GET['secret'] ?? '';
  if (!hash_equals((string)$cfgSecret, (string)($hdrSecret ?: $qsSecret))) {
    if (!headers_sent()) http_response_code(401);
    if ($emit) echo json_encode(['ok'=>false,'error'=>'unauthorized']);
    // Silent mode: no output.
    return;
  }
}

// ---- Includes ----
require __DIR__ . '/../../../private/inc/db.php'; // provides $db (PDO)
require __DIR__ . '/../../../private/inc/update_scores_core.php'; // provides helper functions

// ---- Inputs ----
$season      = isset($_GET['season']) ? (int)$_GET['season'] : (int)date('Y');
$seasontype  = isset($_GET['seasontype']) ? (int)$_GET['seasontype'] : 2;
$mode        = isset($_GET['mode']) ? strtolower(trim((string)$_GET['mode'])) : 'single';
$week        = isset($_GET['week']) ? (int)$_GET['week'] : null;
$fromWeek    = isset($_GET['from']) ? (int)$_GET['from'] : 1;
$toWeek      = isset($_GET['to'])   ? (int)$_GET['to']   : 18;
$createIfMissing = !empty($_GET['create_if_missing']);

// ---- Validation / clamping ----
if ($season < 2000 || $season > 2100) $season = (int)date('Y');
if (!in_array($seasontype, [1,2,3], true)) $seasontype = 2;

$clampWeek = static function (?int $w): ?int {
  if ($w === null) return null;
  if ($w < 1) return 1;
  if ($w > 18) return 18;
  return $w;
};
$week     = $clampWeek($week);
$fromWeek = $clampWeek($fromWeek) ?? 1;
$toWeek   = $clampWeek($toWeek)   ?? 18;

if (!in_array($mode, ['single','range','all'], true)) {
  if (!headers_sent()) http_response_code(422);
  if ($emit) echo json_encode(['ok'=>false,'error'=>'bad_mode']);
  return;
}
if ($mode === 'single' && $week === null) {
  if (!headers_sent()) http_response_code(422);
  if ($emit) echo json_encode(['ok'=>false,'error'=>'week_required_for_single']);
  return;
}
if ($mode === 'range' && $fromWeek > $toWeek) {
  [$fromWeek, $toWeek] = [$toWeek, $fromWeek];
}

/* --- main --- */
try {
  $out = [
    'ok'=>true,
    'season'=>$season,
    'seasontype'=>$seasontype,
    'mode'=>$mode,
    'inserted'=>0,
    'updated'=>0,
    'skipped'=>0,
    'weeks'=>[]
  ];

  if ($mode === 'single') {
    $r = update_week_scores($db,$season,(int)$week,$seasontype,$createIfMissing);
    $out['inserted'] += $r['inserted']; $out['updated'] += $r['updated']; $out['skipped'] += $r['skipped'];
    $out['weeks'][] = ['week'=>(int)$week] + $r;

  } elseif ($mode === 'range') {
    for ($w=$fromWeek; $w<=$toWeek; $w++) {
      $r = update_week_scores($db,$season,$w,$seasontype,$createIfMissing);
      $out['inserted'] += $r['inserted']; $out['updated'] += $r['updated']; $out['skipped'] += $r['skipped'];
      $out['weeks'][] = ['week'=>$w] + $r;
      usleep(150000); // be polite to ESPN
    }

  } else /* mode === 'all' */ {
    for ($w=1; $w<=18; $w++) {
      $r = update_week_scores($db,$season,$w,$seasontype,$createIfMissing);
      $out['inserted'] += $r['inserted']; $out['updated'] += $r['updated']; $out['skipped'] += $r['skipped'];
      $out['weeks'][] = ['week'=>$w] + $r;
      usleep(150000);
    }
  }

  // expose result to including scripts even in silent mode
  $GLOBALS['update_scores_result'] = $out;

  if ($emit) {
    echo json_encode($out);
  }
} catch (Throwable $e) {
  if (!headers_sent()) http_response_code(500);
  $payload = ['ok'=>false,'error'=>'update_failed','message'=>$debug ? $e->getMessage() : 'internal error'];
  if ($debug) $payload['trace'] = $e->getTraceAsString();

  // expose error result for includes
  $GLOBALS['update_scores_result'] = $payload;

  if ($emit) {
    echo json_encode($payload);
  }
}
