<?php
// Log server-side diagnostics without rendering paths, queries, or credentials.
if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
}

// Apply security headers
require_once __DIR__ . '/security_headers.php';

// Load .env file if it exists (for production servers without system env vars)
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments and empty lines
        if (strpos(trim($line), '#') === 0 || trim($line) === '') {
            continue;
        }
        // Parse KEY=VALUE format
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Only set if not already set by system
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Database configuration - REQUIRES environment variables
$DB_HOST = getenv('DB_HOST');
$DB_NAME = getenv('DB_NAME');
$DB_USER = getenv('DB_USER');
$DB_PASS = getenv('DB_PASS');

// Validate required environment variables
if (!$DB_HOST || !$DB_NAME || !$DB_USER || !$DB_PASS) {
    http_response_code(500);
    error_log('FATAL: Missing required database environment variables (DB_HOST, DB_NAME, DB_USER, DB_PASS)');
    die('Database configuration error. Please check environment variables.');
}

$DB_DSN  = "mysql:host=$DB_HOST;port=3306;dbname=$DB_NAME;charset=utf8mb4";



$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];
try {
  $db = new PDO($DB_DSN, $DB_USER, $DB_PASS, $options);
} catch (Throwable $e) {
  http_response_code(500);
  header('Content-Type: text/plain');
  echo "Database connection failed.\n";
  error_log('Database connection failed: ' . $e->getMessage());
  exit;
}
// --- Long-lived session config (e.g. 60 days) ---
$lifetime = 60 * 60 * 24 * 60; // 60 days in seconds

// Make PHP keep sessions around this long server-side
ini_set('session.gc_maxlifetime', (string)$lifetime);

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Configure the session cookie BEFORE session_start()
    $sessionDomain = getenv('SESSION_DOMAIN') ?: '';  // Empty string = current domain
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'domain'   => $sessionDomain,
        'secure'   => true,        // true if you're on HTTPS (you should be)
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

// Feed secret for API authentication
$FEED_SECRET = getenv('FEED_SECRET');
if (!$FEED_SECRET) {
    error_log('WARNING: FEED_SECRET not set. Automated feed endpoints are unavailable.');
}
if (!defined('FEED_SECRET') && $FEED_SECRET) {
    define('FEED_SECRET', $FEED_SECRET);
}

// Load application configuration
$config = require __DIR__ . '/config.php';
$BASE_PATH = $config['app']['base_path'];

/**
 * Generate a URL with the application base path
 *
 * @param string $path URL path (e.g., '/menu.php' or '/api/lines.php')
 * @return string Full URL with base path prepended
 */
function url($path) {
    global $BASE_PATH;
    // Remove leading slash if present, we'll add it back
    $path = ltrim($path, '/');
    // If BASE_PATH is empty, just return /path
    // If BASE_PATH is set (e.g., '/HSC-test'), return /HSC-test/path
    return $BASE_PATH . '/' . $path;
}

/**
 * Redirect to a URL with the application base path
 *
 * @param string $path URL path to redirect to
 * @param int $code HTTP status code (default 302)
 */
function redirect($path, $code = 302) {
    header('Location: ' . url($path), true, $code);
    exit;
}
