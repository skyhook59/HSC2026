<?php
// /private/inc/maintenance.php or /inc/maintenance.php
// Helper utilities to auto-update NFL scores via the admin update_scores API
// and re-score the current relevant week, throttled by a simple job table.

require_once __DIR__ . '/week.php';
require_once __DIR__ . '/scoring.php';

/**
 * Ensure the maintenance_jobs table exists.
 */
function maintenance_ensure_table(PDO $db): void {
    $sql = "
      CREATE TABLE IF NOT EXISTS maintenance_jobs (
        job_name      VARCHAR(64) NOT NULL PRIMARY KEY,
        last_run_utc  DATETIME    NOT NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $db->exec($sql);
}

/**
 * Return true if we should run a given job now, based on minimum interval.
 */
function maintenance_should_run(PDO $db, string $jobName, int $minIntervalSeconds): bool {
    maintenance_ensure_table($db);

    $stmt = $db->prepare("SELECT last_run_utc FROM maintenance_jobs WHERE job_name = ?");
    $stmt->execute([$jobName]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // Never run before -> run now
        return true;
    }

    $last = strtotime($row['last_run_utc'] . ' UTC');
    if ($last === false) {
        return true;
    }

    $now = time();
    return ($now - $last) >= $minIntervalSeconds;
}

/**
 * Record that a job has just run.
 */
function maintenance_mark_ran(PDO $db, string $jobName): void {
    maintenance_ensure_table($db);

    $stmt = $db->prepare("
      INSERT INTO maintenance_jobs (job_name, last_run_utc)
      VALUES (?, UTC_TIMESTAMP())
      ON DUPLICATE KEY UPDATE last_run_utc = VALUES(last_run_utc)
    ");
    $stmt->execute([$jobName]);
}

/**
 * Run the full ESPN sync for a single week by including the admin update_scores script,
 * then re-score that week. Returns a summary array.
 *
 * This uses /public/api/admin/update_scores.php in "silent" mode (no headers/output).
 */
function maintenance_update_scores_and_standings(PDO $db): array {
    // 1) Decide which week to update/score.
    //    If we're pre-lock on week N, we usually care about week N-1 results.
    [$season, $autoWeek, $status] = current_season_week($db);

    $weekToScore = $autoWeek;
    if ($status === 'prelock' && $autoWeek > 1) {
        $weekToScore = $autoWeek - 1;
    }

    // 2) Call the admin update_scores logic for that week.
    //
    // We simulate a "single week" web request:
    //   season=YYYY, seasontype=2 (regular season), mode=single, week=$weekToScore.
    // The admin script is silent by default unless debug=1, so we keep debug off.
    $oldGet = $_GET ?? [];
    $oldUpdateResult = $GLOBALS['update_scores_result'] ?? null;

    $_GET = [
        'season'           => $season,
        'seasontype'       => 2,
        'mode'             => 'single',
        'week'             => $weekToScore,
        'from'             => null,
        'to'               => null,
        'create_if_missing'=> 0,
        // no 'debug' => stays silent (no JSON output)
    ];

    // Use update_scores_core.php directly instead of including the web API
    require_once __DIR__ . '/update_scores_core.php';

    $updateResult = update_week_scores($db, $season, $weekToScore, 2, false);

    // Restore globals
    $_GET = $oldGet;
    $GLOBALS['update_scores_result'] = $oldUpdateResult;

    // 3) Re-score that week using the standard scoring helper.
    $scoreResult = score_week($db, $season, $weekToScore);

    return [
        'season'   => $season,
        'week'     => $weekToScore,
        'update'   => $updateResult,
        'score'    => $scoreResult,
    ];
}

/**
 * Public entry point: call this at the top of standings/results pages.
 *
 * Example:
 *   require __DIR__ . '/../private/inc/maintenance.php';
 *   maintenance_maybe_run_scores($db, 300);
 *
 * It will:
 *   - run at most once every $intervalSeconds (default 300 = 5 minutes),
 *   - sync scores for the relevant week via the admin API logic,
 *   - re-score that week into standings/results.
 */
function maintenance_maybe_run_scores(PDO $db, int $intervalSeconds = 300): void {
    $jobName = 'scores_and_standings';

    if (!maintenance_should_run($db, $jobName, $intervalSeconds)) {
        return;
    }

    try {
        $summary = maintenance_update_scores_and_standings($db);
        maintenance_mark_ran($db, $jobName);
        // Optional: log summary
        error_log(sprintf(
            'maintenance: updated & scored season=%d week=%d (inserted=%s updated=%s skipped=%s users_scored=%s)',
            $summary['season'],
            $summary['week'],
            $summary['update']['inserted'] ?? 'n/a',
            $summary['update']['updated'] ?? 'n/a',
            $summary['update']['skipped'] ?? 'n/a',
            $summary['score']['users_scored'] ?? 'n/a'
        ));
    } catch (Throwable $e) {
        error_log('maintenance_maybe_run_scores error: ' . $e->getMessage());
        // Swallow errors so public pages still load.
    }
}