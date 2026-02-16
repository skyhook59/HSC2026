<?php
/**
 * Health Check Endpoint
 *
 * Provides system health status for monitoring and load balancers.
 * Returns 200 OK if all checks pass, 503 Service Unavailable otherwise.
 *
 * Usage: GET /api/health.php
 *
 * Response format:
 * {
 *   "ok": true/false,
 *   "timestamp": "2025-02-04T10:30:00+00:00",
 *   "checks": {
 *     "database": "ok" | "failed",
 *     "session": "ok" | "failed",
 *     "filesystem": "ok" | "failed"
 *   }
 * }
 */

// Don't apply security headers for health check (some monitoring tools don't like them)
// Instead, manually set minimal headers
header('Content-Type: application/json; charset=UTF-8');

$health = [
    'ok' => true,
    'timestamp' => date('c'),
    'checks' => []
];

// Check: Database connectivity
try {
    // Load db without security headers
    $DB_HOST = getenv('DB_HOST');
    $DB_NAME = getenv('DB_NAME');
    $DB_USER = getenv('DB_USER');
    $DB_PASS = getenv('DB_PASS');

    if (!$DB_HOST || !$DB_NAME || !$DB_USER || !$DB_PASS) {
        throw new Exception('Database configuration missing');
    }

    $DB_DSN = "mysql:host=$DB_HOST;port=3306;dbname=$DB_NAME;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5, // 5 second timeout for health check
    ];

    $db = new PDO($DB_DSN, $DB_USER, $DB_PASS, $options);
    $db->query('SELECT 1');
    $health['checks']['database'] = 'ok';
} catch (Throwable $e) {
    $health['ok'] = false;
    $health['checks']['database'] = 'failed';
    $health['errors']['database'] = $e->getMessage();
}

// Check: Session support
try {
    if (session_status() === PHP_SESSION_DISABLED) {
        throw new Exception('Sessions disabled');
    }
    $health['checks']['session'] = 'ok';
} catch (Throwable $e) {
    $health['ok'] = false;
    $health['checks']['session'] = 'failed';
    $health['errors']['session'] = $e->getMessage();
}

// Check: Filesystem (logs directory writable)
try {
    $logsDir = __DIR__ . '/../../private/logs';
    if (!is_dir($logsDir)) {
        if (!@mkdir($logsDir, 0755, true)) {
            throw new Exception('Cannot create logs directory');
        }
    }
    if (!is_writable($logsDir)) {
        throw new Exception('Logs directory not writable');
    }
    $health['checks']['filesystem'] = 'ok';
} catch (Throwable $e) {
    $health['ok'] = false;
    $health['checks']['filesystem'] = 'failed';
    $health['errors']['filesystem'] = $e->getMessage();
}

// Set HTTP status code
http_response_code($health['ok'] ? 200 : 503);

// Output response
echo json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
