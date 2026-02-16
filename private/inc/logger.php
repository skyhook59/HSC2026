<?php
/**
 * Structured Logging
 *
 * Provides consistent, structured logging across the application.
 * Logs are written to private/logs/app.log in JSON format for easy parsing.
 *
 * Usage:
 *   log_event('INFO', 'User logged in', ['user_id' => 123]);
 *   log_event('ERROR', 'Database connection failed', ['error' => $e->getMessage()]);
 */

/**
 * Log levels (RFC 5424)
 */
define('LOG_LEVEL_DEBUG', 'DEBUG');
define('LOG_LEVEL_INFO', 'INFO');
define('LOG_LEVEL_WARNING', 'WARNING');
define('LOG_LEVEL_ERROR', 'ERROR');
define('LOG_LEVEL_CRITICAL', 'CRITICAL');

/**
 * Log an event with structured data
 *
 * @param string $level Log level (DEBUG, INFO, WARNING, ERROR, CRITICAL)
 * @param string $message Log message
 * @param array $context Additional context data
 * @return bool True on success, false on failure
 */
function log_event(string $level, string $message, array $context = []): bool {
    // Check if logging is enabled
    static $config = null;
    if ($config === null && file_exists(__DIR__ . '/config.php')) {
        $config = require __DIR__ . '/config.php';
    }

    if ($config && isset($config['logging']['enabled']) && !$config['logging']['enabled']) {
        return true; // Logging disabled
    }

    // Determine log directory
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        if (!@mkdir($logDir, 0755, true)) {
            error_log("Failed to create log directory: $logDir");
            return false;
        }
    }

    $logFile = $logDir . '/app.log';

    // Build log entry
    $entry = [
        'timestamp' => date('c'), // ISO 8601 format
        'level' => strtoupper($level),
        'message' => $message,
        'context' => $context,
        'user_id' => $_SESSION['user_id'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'uri' => $_SERVER['REQUEST_URI'] ?? null,
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
    ];

    // Remove null values to keep logs clean
    $entry = array_filter($entry, function($value) {
        return $value !== null;
    });

    // Write to log file
    $json = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

    // Rotate log file if too large (10MB default)
    $maxSize = ($config['logging']['max_file_size'] ?? 10485760); // 10MB
    if (file_exists($logFile) && filesize($logFile) > $maxSize) {
        $rotated = $logFile . '.' . date('Y-m-d_H-i-s');
        @rename($logFile, $rotated);
    }

    return @file_put_contents($logFile, $json, FILE_APPEND | LOCK_EX) !== false;
}

/**
 * Log a debug message
 *
 * @param string $message Log message
 * @param array $context Additional context
 * @return bool
 */
function log_debug(string $message, array $context = []): bool {
    return log_event(LOG_LEVEL_DEBUG, $message, $context);
}

/**
 * Log an info message
 *
 * @param string $message Log message
 * @param array $context Additional context
 * @return bool
 */
function log_info(string $message, array $context = []): bool {
    return log_event(LOG_LEVEL_INFO, $message, $context);
}

/**
 * Log a warning message
 *
 * @param string $message Log message
 * @param array $context Additional context
 * @return bool
 */
function log_warning(string $message, array $context = []): bool {
    return log_event(LOG_LEVEL_WARNING, $message, $context);
}

/**
 * Log an error message
 *
 * @param string $message Log message
 * @param array $context Additional context
 * @return bool
 */
function log_error(string $message, array $context = []): bool {
    return log_event(LOG_LEVEL_ERROR, $message, $context);
}

/**
 * Log a critical error message
 *
 * @param string $message Log message
 * @param array $context Additional context
 * @return bool
 */
function log_critical(string $message, array $context = []): bool {
    return log_event(LOG_LEVEL_CRITICAL, $message, $context);
}

/**
 * Log an exception
 *
 * @param Throwable $exception Exception to log
 * @param string $level Log level (default: ERROR)
 * @return bool
 */
function log_exception(Throwable $exception, string $level = LOG_LEVEL_ERROR): bool {
    return log_event($level, $exception->getMessage(), [
        'exception' => get_class($exception),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString(),
    ]);
}
