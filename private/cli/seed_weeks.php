#!/usr/bin/env php
<?php
require __DIR__ . '/../inc/db.php';

/**
 * Seed weeks 1..18 with Saturday 11:59pm PT lock times.
 * Usage: php seed_weeks.php 2025 "2025-09-06"   # Week 1 lock (Saturday date in PT)
 * this only needs to be run once at the beginning of the year, just set what the first lock saturday is 
 */
$season = (int)($argv[1] ?? date('Y'));
$week1_sat = $argv[2] ?? null; // YYYY-MM-DD (the Saturday of Week 1)
if (!$week1_sat) { fwrite(STDERR, "Usage: php seed_weeks.php 2025 2025-09-06\n"); exit(1); }

$tzPT = new DateTimeZone('America/Los_Angeles');
$pt = new DateTimeImmutable($week1_sat . ' 23:59:00', $tzPT);
for ($w=1; $w<=18; $w++) {
  $lockUtc = $pt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
  $stmt = $db->prepare("INSERT INTO weeks (season_year, week_number, lock_at_utc)
    VALUES (?,?,?) ON DUPLICATE KEY UPDATE lock_at_utc=VALUES(lock_at_utc)");
  $stmt->execute([$season, $w, $lockUtc]);
  // next Saturday:
  $pt = $pt->modify('+7 days');
}
echo "Seeded weeks 1..18 for $season\n";
