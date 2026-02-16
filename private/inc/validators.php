<?php
/**
 * Input Validation Helpers
 *
 * Centralized validation functions for common input types.
 * Returns validated value or null if invalid.
 */

/**
 * Validate and sanitize season year
 *
 * @param mixed $year Year to validate
 * @return int|null Valid year or null if invalid
 */
function validate_season($year): ?int {
    $y = (int)$year;
    $config = require __DIR__ . '/config.php';
    $min = $config['season']['min_year'];
    $max = $config['season']['max_year'];
    return ($y >= $min && $y <= $max) ? $y : null;
}

/**
 * Validate and sanitize week number
 *
 * @param mixed $week Week number to validate
 * @return int|null Valid week or null if invalid
 */
function validate_week($week): ?int {
    $w = (int)$week;
    $config = require __DIR__ . '/config.php';
    $min = $config['season']['min_week'];
    $max = $config['season']['max_week'];
    return ($w >= $min && $w <= $max) ? $w : null;
}

/**
 * Validate and normalize team abbreviation
 *
 * @param string $abbr Team abbreviation to validate
 * @return string|null Valid team abbreviation or null if invalid
 */
function validate_team_abbr(string $abbr): ?string {
    $config = require __DIR__ . '/config.php';
    $valid = $config['teams'];
    $mappings = $config['team_mappings'];

    $abbr = strtoupper(trim($abbr));

    // Check mappings first (e.g., WSH -> WAS)
    if (isset($mappings[$abbr])) {
        $abbr = $mappings[$abbr];
    }

    return in_array($abbr, $valid, true) ? $abbr : null;
}

/**
 * Validate array of team abbreviations
 *
 * @param array $abbrs Array of team abbreviations
 * @return array|null Array of valid abbreviations or null if any invalid
 */
function validate_team_abbrs(array $abbrs): ?array {
    $validated = [];
    foreach ($abbrs as $abbr) {
        $valid = validate_team_abbr($abbr);
        if ($valid === null) {
            return null;
        }
        $validated[] = $valid;
    }
    return $validated;
}

/**
 * Validate email address
 *
 * @param string $email Email to validate
 * @return string|null Valid email or null if invalid
 */
function validate_email(string $email): ?string {
    $email = strtolower(trim($email));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
}

/**
 * Validate user ID
 *
 * @param mixed $userId User ID to validate
 * @return int|null Valid user ID or null if invalid
 */
function validate_user_id($userId): ?int {
    $id = (int)$userId;
    return ($id > 0) ? $id : null;
}

/**
 * Validate ESPN season type
 *
 * @param mixed $seasonType Season type to validate
 * @return int|null Valid season type (1, 2, or 3) or null if invalid
 */
function validate_season_type($seasonType): ?int {
    $type = (int)$seasonType;
    return in_array($type, [1, 2, 3], true) ? $type : null;
}

/**
 * Validate game state
 *
 * @param string $state Game state to validate
 * @return string|null Valid state ('pre', 'in_progress', 'final') or null
 */
function validate_game_state(string $state): ?string {
    $state = strtolower(trim($state));
    $valid = ['pre', 'in_progress', 'final'];
    return in_array($state, $valid, true) ? $state : null;
}

/**
 * Validate and sanitize spread value
 *
 * @param mixed $spread Spread value to validate
 * @return float|null Valid spread or null if invalid
 */
function validate_spread($spread): ?float {
    if (!is_numeric($spread)) {
        return null;
    }
    $s = (float)$spread;
    // Spreads typically range from -50 to +50
    return ($s >= -50 && $s <= 50) ? $s : null;
}

/**
 * Validate boolean value (accepts various formats)
 *
 * @param mixed $value Value to validate as boolean
 * @return bool|null Boolean value or null if invalid
 */
function validate_boolean($value): ?bool {
    if (is_bool($value)) {
        return $value;
    }
    if (is_numeric($value)) {
        return (int)$value === 1;
    }
    $str = strtolower(trim((string)$value));
    if (in_array($str, ['true', 'yes', '1', 'on'], true)) {
        return true;
    }
    if (in_array($str, ['false', 'no', '0', 'off', ''], true)) {
        return false;
    }
    return null;
}

/**
 * Sanitize string for safe output
 *
 * @param string $str String to sanitize
 * @param bool $allowHtml Allow HTML tags (default: false)
 * @return string Sanitized string
 */
function sanitize_string(string $str, bool $allowHtml = false): string {
    $str = trim($str);
    if ($allowHtml) {
        // Strip only dangerous tags
        $str = strip_tags($str, '<p><br><strong><em><ul><ol><li><a>');
    } else {
        // Remove all HTML
        $str = strip_tags($str);
    }
    return $str;
}

/**
 * Validate URL
 *
 * @param string $url URL to validate
 * @return string|null Valid URL or null if invalid
 */
function validate_url(string $url): ?string {
    $url = trim($url);
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
}
