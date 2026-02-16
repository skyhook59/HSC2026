<?php
// private/inc/lines_import.php
// Helpers to import SuperContest lines from the Westgate card page.

require_once __DIR__ . '/db.php';

/**
 * Build the Westgate SuperContest card URL for a given season.
 *
 * For 2025, the URL is:
 *   https://www.westgateresorts.com/.../2025-supercontest-card/
 *
 * For future seasons, confirm the pattern (it is usually "<year>-supercontest-card").
 */
function westgate_card_url_for_season(int $season): string {
    // Adjust if Westgate changes their URL scheme.
    return "https://www.westgateresorts.com/hotels/nevada/las-vegas/westgate-las-vegas-resort-casino/casino/{$season}-supercontest-card/";
}

/**
 * Fetch the Westgate card HTML for this season.
 */
function fetch_westgate_card_html(int $season): ?string {
    $url = westgate_card_url_for_season($season);
    $html = @file_get_contents($url);
    if ($html === false) {
        return null;
    }
    return $html;
}

/**
 * Parse the Westgate card HTML and return an array:
 *   [ weekNumber => pdfUrl, ... ]
 */
function parse_supercontest_pdfs_from_html(string $html, int $season): array {
    $matches = [];
    $pdfsByWeek = [];

    // Look for href="...pdf" lines that mention "SUPER CONTEST GAME SHEET WEEK X".
    // This may need a tweak if Westgate changes wording slightly.
    $pattern = '/href="([^"]+\.pdf)".*?' . $season . '\s+SUPER\s+CONTEST\s+GAME\s+SHEET\s+WEEK\s+(\d+)/i';

    if (!preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
        return [];
    }

    foreach ($matches as $m) {
        $url  = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        $week = (int)$m[2];
        $pdfsByWeek[$week] = $url; // last one wins if duplicates
    }

    return $pdfsByWeek;
}

/**
 * Resolve a possibly-relative Westgate PDF URL to an absolute URL.
 */
function resolve_westgate_pdf_url(int $season, string $pdfUrl): string {
    if (strpos($pdfUrl, 'http') === 0) {
        return $pdfUrl;
    }

    $base = westgate_card_url_for_season($season);
    return rtrim($base, '/') . '/' . ltrim($pdfUrl, '/');
}

/**
 * Core function to:
 *  - find the correct SuperContest PDF on Westgate's page,
 *  - call your existing import logic on it.
 *
 * @param PDO   $db
 * @param int   $season
 * @param int|null $week   If null, auto-select the highest week on the card page.
 *
 * @return array  ['ok' => bool, 'message' => string, 'season' => int, 'week' => int|null, 'pdf_url' => string|null, 'details' => mixed]
 */
function import_supercontest_lines_from_westgate(PDO $db, int $season, ?int $week = null): array {
    $html = fetch_westgate_card_html($season);
    if ($html === null) {
        return [
            'ok'       => false,
            'message'  => "Could not fetch Westgate SuperContest card page for season {$season}.",
            'season'   => $season,
            'week'     => $week,
            'pdf_url'  => null,
            'details'  => null,
        ];
    }

    $pdfsByWeek = parse_supercontest_pdfs_from_html($html, $season);
    if (empty($pdfsByWeek)) {
        return [
            'ok'       => false,
            'message'  => "No SuperContest PDFs found on the Westgate card page for season {$season}.",
            'season'   => $season,
            'week'     => $week,
            'pdf_url'  => null,
            'details'  => null,
        ];
    }

    // If week is not specified, choose the highest week number present.
    if ($week === null) {
        $availableWeeks = array_keys($pdfsByWeek);
        rsort($availableWeeks, SORT_NUMERIC);
        $week = $availableWeeks[0];
    }

    if (empty($pdfsByWeek[$week])) {
        return [
            'ok'       => false,
            'message'  => "No PDF link found on the Westgate card page for week {$week} in season {$season}.",
            'season'   => $season,
            'week'     => $week,
            'pdf_url'  => null,
            'details'  => null,
        ];
    }

    $pdfUrl = resolve_westgate_pdf_url($season, $pdfsByWeek[$week]);

    // === IMPORTANT PART ===
    //
    // Here we call your existing import logic, which currently lives in cli/import_lines.php.
    // In that file you probably do something like:
    //
    //   import_lines_from_pdf($db, $season, $week, $pdfPathOrUrl);
    //
    // If that logic is NOT yet in a function, move the core "parse PDF and insert lines" code
    // into a reusable function (e.g. in private/inc/lines.php) and call it from both:
    //   - cli/import_lines.php, and
    //   - this function.
    //
    // For now I’ll assume you expose a function:
    //   import_lines_from_pdf(PDO $db, int $season, int $week, string $pdfUrl): array
    //
    // Replace the stub call below with the real one.

    if (!function_exists('import_lines_from_pdf')) {
        // Stub: tell you what to wire up.
        return [
            'ok'       => false,
            'message'  => "import_lines_from_pdf() is not defined. Move your PDF parsing logic from cli/import_lines.php into a shared function and require it before calling this.",
            'season'   => $season,
            'week'     => $week,
            'pdf_url'  => $pdfUrl,
            'details'  => null,
        ];
    }

    // Call the real importer.
    $details = import_lines_from_pdf($db, $season, $week, $pdfUrl);

    return [
        'ok'       => true,
        'message'  => "Imported lines for season {$season}, week {$week} from {$pdfUrl}.",
        'season'   => $season,
        'week'     => $week,
        'pdf_url'  => $pdfUrl,
        'details'  => $details,
    ];
}