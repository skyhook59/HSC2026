<?php
#!/usr/bin/env php
//declare(strict_types=1);
/**
 * Location: /home/public/api/admin/score_week.php
 *
 * Web usage:
 *   /api/admin/score_week.php?year=2025&week=7
 *   /api/admin/score_week.php?year=2025&week=7&debug=1   (verbose output)
 *
 * CLI usage:
 *   php /home/public/api/admin/score_week.php 2025 7
 *
 * Behavior:
 *   - If debug=1 (or running via CLI), prints status/results and PHP errors.
 *   - Otherwise, runs silently with no output (safe for include()).
 */

// ----- Bootstrap + includes -----
// From /home/public/api/admin to /home/private/inc: up 3 levels, then /private/inc
$INC_BASE = __DIR__ . '/../../../private/inc';

require $INC_BASE . '/db.php';
require $INC_BASE . '/scoring.php';

// ----- Input handling -----
$isCli = (PHP_SAPI === 'cli');

// Require admin authentication for web requests
if (!$isCli) {
    require $INC_BASE . '/auth_guard.php';
    admin_required();

    // CSRF protection for POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require $INC_BASE . '/csrf.php';
        csrf_protect();
    }
}

// Prefer POST, then GET params for web, argv for CLI. Provide sane defaults.
if ($isCli) {
    $season = isset($argv[1]) ? (int)$argv[1] : (int)date('Y');
    $week   = isset($argv[2]) ? (int)$argv[2] : 1;
    $debug  = true; // CLI is always verbose
} else {
    // Accept POST (from forms) or GET (from API calls)
    $season = isset($_POST['season']) ? (int)$_POST['season'] : (isset($_GET['year']) ? (int)$_GET['year'] : (isset($_GET['season']) ? (int)$_GET['season'] : (int)date('Y')));
    $week   = isset($_POST['week']) ? (int)$_POST['week'] : (isset($_GET['week']) ? (int)$_GET['week'] : 1);
    $debug  = (isset($_GET['debug']) && (string)$_GET['debug'] === '1');
}

// Basic bounds checking (adjust as appropriate for your domain)
if ($season < 2000 || $season > 2100) {
    $season = (int)date('Y');
}
if ($week < 1 || $week > 30) { // tweak max week if needed
    $week = 1;
}

// Error visibility: only show when debugging/CLI
@ini_set('display_errors', $debug ? '1' : '0');
@ini_set('display_startup_errors', $debug ? '1' : '0');
@error_reporting($debug ? E_ALL : 0);

// ----- Execute -----
try {
    // $db is expected to be provided by db.php
    $result = score_week($db, $season, $week);

    if ($debug) {
        if (!$isCli) {
            // Helpful in browser when debugging
            header('Content-Type: text/plain; charset=UTF-8');
        }
        echo "Scored season {$season}, week {$week}\n";
        echo "Users: {$result['users_scored']}, Wins: {$result['wins']}, Losses: {$result['losses']}, Pushes: {$result['pushes']}\n";
    } else {
        // Silent mode: no output at all (safe for include/require)
        // Intentionally do nothing.
        // If you want a programmatic signal for includes, you could set:
        // $GLOBALS['score_week_last_result'] = $result;
    }
} catch (Throwable $e) {
    if ($debug) {
        if (!$isCli) {
            header('Content-Type: text/plain; charset=UTF-8');
        }
        // Print full error details in debug mode
        echo "Error scoring season {$season}, week {$week}:\n";
        echo $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n";
    } else {
        // Silent on error when not debugging
        // Optional: log to file/system logger if you like
        // error_log($e);
    }
}
