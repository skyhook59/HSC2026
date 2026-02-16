<?php
/**
 * PHPUnit Bootstrap File
 *
 * Loaded before running tests. Sets up environment for testing.
 */

// Set test environment
define('TEST_ENV', true);

// Load autoloader if using Composer
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set timezone
date_default_timezone_set('America/New_York');

// Mock environment variables for testing
putenv('DB_HOST=localhost');
putenv('DB_NAME=test_db');
putenv('DB_USER=test_user');
putenv('DB_PASS=test_pass');
