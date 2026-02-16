<?php
/**
 * /public/update.php
 * Public runner:
 *   1) Resolve ($season_year,$week_number) from DB (or ?season=&week= overrides)
 *   2) GET /public/api/admin/update_scores.php?... (seasontype=2, mode=single)
 *   3) CLI: /private/inc/score_week.php $season_year $week_number
 *
 * Returns JSON.
 */

header('Content-Type: application/json');

// Optional token auth (?token=...) – set ADMIN_TOKEN in env to require it
$REQUIRE_TOKEN = getenv('ADMIN_TOKEN') !== false;
$EXPECTED_TOKEN = getenv('ADMIN_TOKEN') ?: null;

function fail($msg, $http = 500, $extra = []) {
  http_response_code($http);
  echo json_encode(array_merge(['ok' => false, 'error' => $msg], $extra), JSON_PRETTY_PRINT);
  exit;
}

if ($REQUIRE_TOKEN) {
  $provided = $_GET['token'] ?? '';
  if (!hash_equals($EXPECTED_TOKEN, $provided)) {
    fail('Unauthorized', 401);
  }
}

// ---------- include DB ----------
$included = false;
$paths = [
  __DIR__ . '/../private/inc/db.php',
  __DIR__ . '/db.php',
  __DIR__ . '/../db.php',
  'db.php',
];
foreach ($paths as $p) {
  if (is_readable($p)) {
    require_once $p;
    $included = true;
    break;
  }
}
if (!$included) {
  @include_once 'db.php';
}

// Accept PDO in $db or $pdo, or mysqli in $mysqli
function get_db_kind() {
  global $db, $pdo, $mysqli;
  if (isset($db) && $db instanceof PDO)   return 'pdo_db';
  if (isset($pdo) && $pdo instanceof PDO) return 'pdo_pdo';
  if (isset($mysqli) && $mysqli instanceof mysqli) return 'mysqli';
  return null;
}
$kind = get_db_kind();
if ($kind === null) {
  fail('DB handle not found (expected $db PDO, $pdo PDO, or $mysqli). Check include path to db.php.');
}

// ---------- helpers ----------
function db_query_row($sql, $params = []) {
  global $db, $pdo, $mysqli;
  $kind = get_db_kind();

  if ($kind === 'pdo_db' || $kind === 'pdo_pdo') {
    $h = ($kind === 'pdo_db') ? $db : $pdo;
    $stmt = $h->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  if ($kind === 'mysqli') {
    // minimal “?” interpolation for a single-row query
    foreach ($params as $v) {
      $qv = "'" . $mysqli->real_escape_string($v) . "'";
      $sql = preg_replace('/\?/', $qv, $sql, 1);
    }
    $res = $mysqli->query($sql);
    if ($res === false) return null;
    $row = $res->fetch_assoc();
    $res->free();
    return $row ?: null;
  }

  return null;
}

function current_utc_mysql() {
  $dt = new DateTime('now', new DateTimeZone('UTC'));
  return $dt->format('Y-m-d H:i:s');
}

// ---------- resolve season/week ----------
$season_year = isset($_GET['season']) ? (int)$_GET['season'] : null;
$week_number = isset($_GET['week'])   ? (int)$_GET['week']   : null;

if (!$season_year || !$week_number) {
  $nowUtc = current_utc_mysql();
  // Latest locked & visible week (weeks.lock_at_utc <= now && visible_after_lock=1)
  $row = db_query_row(
    "SELECT season_year, week_number
     FROM weeks
     WHERE lock_at_utc <= ? AND COALESCE(visible_after_lock,1)=1
     ORDER BY season_year DESC, week_number DESC
     LIMIT 1",
    [$nowUtc]
  );
  if (!$row) {
    fail('Could not resolve season/week from weeks table (no locked/visible week found).', 400, ['now_utc' => $nowUtc]);
  }
  $season_year = $season_year ?: (int)$row['season_year'];
  $week_number = $week_number ?: (int)$row['week_number'];
}

// Basic guardrails
if ($season_year < 2000 || $week_number < 1 || $week_number > 25) {
  fail('Invalid season/week values.', 400, ['season_year' => $season_year, 'week_number' => $week_number]);
}

// ---------- 1) call update_scores API ----------
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$apiPath = "/public/api/admin/update_scores.php";
$query = http_build_query([
  'season'     => $season_year,
  'seasontype' => 2,
  'mode'       => 'single',
  'week'       => $week_number,
]);
$apiUrl = "{$scheme}://{$host}{$apiPath}?{$query}";

$apiInfo = ['url' => $apiUrl, 'http_code' => null, 'body' => null, 'error' => null];
if (function_exists('curl_init')) {
  $ch = curl_init($apiUrl);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT      => 'week-update-runner/1.1',
  ]);
  $body = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);
  $apiInfo['http_code'] = $code;
  $apiInfo['body'] = $body;
  $apiInfo['error'] = $err ?: null;
} else {
  $context = stream_context_create(['http' => ['timeout' => 20, 'header' => "User-Agent: week-update-runner/1.1\r\n"]]);
  $body = @file_get_contents($apiUrl, false, $context);
  $apiInfo['http_code'] = (isset($http_response_header) && preg_match('#\s(\d{3})\s#', $http_response_header[0] ?? '', $m)) ? (int)$m[1] : null;
  $apiInfo['body'] = $body;
  $apiInfo['error'] = $body === false ? 'file_get_contents failed' : null;
}

// ---------- 2) CLI run ----------
$phpBin = defined('PHP_BINARY') ? PHP_BINARY : 'php';
$script = '/private/cli/score_week.php';
if (!is_file($script)) {
  $alt = realpath(__DIR__ . '/../private/cli/score_week.php');
  if ($alt) $script = $alt;
}
$cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg((string)$season_year) . ' ' . escapeshellarg((string)$week_number) . ' 2>&1';
$execInfo = ['cmd' => $cmd, 'exit_code' => null, 'output' => null];

$lastLine = @exec($cmd, $outLines, $exitCode);
$execInfo['exit_code'] = $exitCode;
$execInfo['output'] = is_array($outLines) ? implode("\n", $outLines) : null;

// ---------- response ----------
echo json_encode([
  'ok' => true,
  'resolved' => ['season_year' => $season_year, 'week_number' => $week_number],
  'update_scores' => $apiInfo,
  'score_week'    => $execInfo,
  'db_kind'       => get_db_kind(), // 'pdo_db' | 'pdo_pdo' | 'mysqli'
], JSON_PRETTY_PRINT);
