#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/../inc/db.php';

// Config
const WESTGATE_CARD_URL = 'https://www.westgateresorts.com/hotels/nevada/las-vegas/westgate-las-vegas-resort-casino/casino/2025-supercontest-card/';

$season = (int)($argv[1] ?? date('Y'));
$week   = isset($argv[2]) ? (int)$argv[2] : null;

// 1) Fetch the card page HTML
$html = @file_get_contents(WESTGATE_CARD_URL);
if ($html === false) {
    fwrite(STDERR, "Failed to fetch Westgate SuperContest card page\n");
    exit(1);
}

// 2) Find all PDF links that look like "2025 SUPER CONTEST GAME SHEET WEEK X.pdf"
$matches = [];
$pattern = '/href="([^"]+\.pdf)".*?2025\s+SUPER\s+CONTEST\s+GAME\s+SHEET\s+WEEK\s+(\d+)/i';

if (!preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
    fwrite(STDERR, "No matching SuperContest PDFs found on card page\n");
    exit(1);
}

$pdfsByWeek = [];
foreach ($matches as $m) {
    $url  = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
    $wNum = (int)$m[2];
    $pdfsByWeek[$wNum] = $url; // last one wins if duplicates
}

// 3) Decide which week to import
if ($week === null) {
    // If no explicit week is passed, we’ll choose the highest week number present.
    $availableWeeks = array_keys($pdfsByWeek);
    if (empty($availableWeeks)) {
        fwrite(STDERR, "No weeks found in PDFs\n");
        exit(1);
    }
    rsort($availableWeeks, SORT_NUMERIC);
    $week = $availableWeeks[0];
}

if (empty($pdfsByWeek[$week])) {
    fwrite(STDERR, "No PDF found on card page for week {$week}\n");
    exit(1);
}

$pdfUrl = $pdfsByWeek[$week];

// Normalize relative URLs to absolute
if (strpos($pdfUrl, 'http') !== 0) {
    $pdfUrl = rtrim(WESTGATE_CARD_URL, '/') . '/' . ltrim($pdfUrl, '/');
}

echo "Importing lines for season {$season}, week {$week} from {$pdfUrl}\n";

// 4) Call your existing import_lines.php logic
$cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/import_lines.php')
     . ' ' . escapeshellarg((string)$season)
     . ' ' . escapeshellarg((string)$week)
     . ' ' . escapeshellarg($pdfUrl);

passthru($cmd, $exitCode);
exit($exitCode);