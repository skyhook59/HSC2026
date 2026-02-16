<?php
/**
 * Rate Limiting
 *
 * Prevents abuse by limiting the number of requests from a single source
 * within a time window.
 *
 * Requires: rate_limits table in database
 * CREATE TABLE rate_limits (
 *   id INT AUTO_INCREMENT PRIMARY KEY,
 *   key_name VARCHAR(255) NOT NULL,
 *   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *   INDEX idx_key_created (key_name, created_at)
 * );
 */

/**
 * Check and enforce rate limit
 *
 * @param PDO $db Database connection
 * @param string $key Rate limit key (e.g., 'login_192.168.1.1')
 * @param int $maxAttempts Maximum attempts allowed
 * @param int $windowSeconds Time window in seconds
 * @param int $httpCode HTTP status code to send on limit exceeded (default 429)
 * @return void Dies with error if rate limit exceeded
 */
function rate_limit(PDO $db, string $key, int $maxAttempts = 5, int $windowSeconds = 300, int $httpCode = 429): void {
    // Clean old entries first (optional, for housekeeping)
    $cleanupStmt = $db->prepare("
        DELETE FROM rate_limits
        WHERE created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)
        LIMIT 1000
    ");
    $cleanupStmt->execute([$windowSeconds * 2]); // Clean entries older than 2x window

    // Check current rate
    $checkStmt = $db->prepare("
        SELECT COUNT(*) FROM rate_limits
        WHERE key_name = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    $checkStmt->execute([$key, $windowSeconds]);
    $count = (int)$checkStmt->fetchColumn();

    if ($count >= $maxAttempts) {
        http_response_code($httpCode);
        error_log("Rate limit exceeded for key: $key (IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ")");
        header('Retry-After: ' . $windowSeconds);
        die('Rate limit exceeded. Please try again later.');
    }

    // Record this attempt
    $insertStmt = $db->prepare("INSERT INTO rate_limits (key_name) VALUES (?)");
    $insertStmt->execute([$key]);
}

/**
 * Check rate limit without enforcing (returns true if limit would be exceeded)
 *
 * @param PDO $db Database connection
 * @param string $key Rate limit key
 * @param int $maxAttempts Maximum attempts allowed
 * @param int $windowSeconds Time window in seconds
 * @return bool True if rate limit would be exceeded
 */
function rate_limit_check(PDO $db, string $key, int $maxAttempts = 5, int $windowSeconds = 300): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM rate_limits
        WHERE key_name = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    $stmt->execute([$key, $windowSeconds]);
    $count = (int)$stmt->fetchColumn();
    return $count >= $maxAttempts;
}

/**
 * Reset rate limit for a key (e.g., after successful login)
 *
 * @param PDO $db Database connection
 * @param string $key Rate limit key to reset
 * @return void
 */
function rate_limit_reset(PDO $db, string $key): void {
    $stmt = $db->prepare("DELETE FROM rate_limits WHERE key_name = ?");
    $stmt->execute([$key]);
}

/**
 * Get client IP address (considering proxies)
 *
 * @return string Client IP address
 */
function get_client_ip(): string {
    // Check for proxy headers (be careful in production - these can be spoofed)
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            // X-Forwarded-For can contain multiple IPs, take the first one
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
