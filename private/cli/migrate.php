<?php
/**
 * Database Migration Runner
 *
 * Applies pending database migrations from private/migrations/
 * Tracks applied migrations in schema_migrations table.
 *
 * Usage:
 *   php private/cli/migrate.php        # Apply all pending migrations
 *   php private/cli/migrate.php status # Show migration status
 *   php private/cli/migrate.php create migration_name # Create new migration
 */

require __DIR__ . '/../inc/db.php';

// Ensure schema_migrations table exists
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(14) PRIMARY KEY,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_applied (applied_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    die("Failed to create schema_migrations table: " . $e->getMessage() . "\n");
}

$command = $argv[1] ?? 'migrate';
$migrationsDir = __DIR__ . '/../migrations';

switch ($command) {
    case 'status':
        showStatus($db, $migrationsDir);
        break;
    case 'create':
        $name = $argv[2] ?? null;
        if (!$name) {
            die("Usage: php migrate.php create migration_name\n");
        }
        createMigration($migrationsDir, $name);
        break;
    case 'migrate':
    default:
        runMigrations($db, $migrationsDir);
        break;
}

/**
 * Show migration status
 */
function showStatus(PDO $db, string $migrationsDir): void {
    echo "Migration Status\n";
    echo str_repeat("=", 60) . "\n\n";

    $applied = getAppliedMigrations($db);
    $available = getAvailableMigrations($migrationsDir);

    if (empty($available)) {
        echo "No migrations found in $migrationsDir\n";
        return;
    }

    foreach ($available as $version => $file) {
        $status = isset($applied[$version]) ? '✓ Applied' : '✗ Pending';
        $date = isset($applied[$version]) ? ' (' . $applied[$version] . ')' : '';
        echo sprintf("%-20s %s%s\n", $version, $status, $date);
    }

    echo "\nTotal: " . count($available) . " migrations\n";
    echo "Applied: " . count($applied) . " migrations\n";
    echo "Pending: " . (count($available) - count($applied)) . " migrations\n";
}

/**
 * Run pending migrations
 */
function runMigrations(PDO $db, string $migrationsDir): void {
    $applied = getAppliedMigrations($db);
    $available = getAvailableMigrations($migrationsDir);

    $pending = array_diff_key($available, $applied);

    if (empty($pending)) {
        echo "No pending migrations.\n";
        return;
    }

    echo "Running " . count($pending) . " migration(s)...\n\n";

    foreach ($pending as $version => $file) {
        echo "Applying $version... ";

        try {
            $sql = file_get_contents($file);
            $db->exec($sql);

            // Record migration
            $stmt = $db->prepare("INSERT INTO schema_migrations (version) VALUES (?)");
            $stmt->execute([$version]);

            echo "✓ Done\n";
        } catch (PDOException $e) {
            echo "✗ Failed\n";
            die("Error: " . $e->getMessage() . "\n");
        }
    }

    echo "\nAll migrations applied successfully.\n";
}

/**
 * Create a new migration file
 */
function createMigration(string $migrationsDir, string $name): void {
    if (!is_dir($migrationsDir)) {
        mkdir($migrationsDir, 0755, true);
    }

    $timestamp = date('Ymd_His');
    $filename = $timestamp . '_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($name)) . '.sql';
    $filepath = $migrationsDir . '/' . $filename;

    $template = "-- Migration: " . ucwords(str_replace('_', ' ', $name)) . "\n";
    $template .= "-- Date: " . date('Y-m-d H:i:s') . "\n\n";
    $template .= "-- Write your SQL migration here\n\n";

    file_put_contents($filepath, $template);

    echo "Created migration: $filename\n";
    echo "Edit: $filepath\n";
}

/**
 * Get applied migrations from database
 */
function getAppliedMigrations(PDO $db): array {
    $stmt = $db->query("SELECT version, applied_at FROM schema_migrations ORDER BY version");
    $migrations = [];
    while ($row = $stmt->fetch()) {
        $migrations[$row['version']] = $row['applied_at'];
    }
    return $migrations;
}

/**
 * Get available migrations from filesystem
 */
function getAvailableMigrations(string $migrationsDir): array {
    if (!is_dir($migrationsDir)) {
        return [];
    }

    $files = glob($migrationsDir . '/*.sql');
    $migrations = [];

    foreach ($files as $file) {
        $basename = basename($file, '.sql');
        // Extract version (first 14 chars: YYYYMMDD_HHMMSS or YYYYMMDD)
        if (preg_match('/^(\d{8,14})/', $basename, $matches)) {
            $version = $matches[1];
            $migrations[$version] = $file;
        }
    }

    ksort($migrations);
    return $migrations;
}
