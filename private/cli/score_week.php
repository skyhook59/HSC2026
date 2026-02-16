#!/usr/bin/env php
<?php
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/scoring.php';

/**
 * Usage:
 *   php /home/YOURSITE/private/cli/score_week.php 2025 1
 */
$season = isset($argv[1]) ? (int)$argv[1] : (int)date('Y');
$week   = isset($argv[2]) ? (int)$argv[2] : 1;

$result = score_week($db, $season, $week);
echo "Scored season {$season}, week {$week}\n";
echo "Users: {$result['users_scored']}, Wins: {$result['wins']}, Losses: {$result['losses']}, Pushes: {$result['pushes']}\n";
