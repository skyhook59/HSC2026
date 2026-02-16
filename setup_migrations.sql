-- HSC Database Setup - Run this to set up migration tracking and rate limiting
-- Execute this SQL file directly in your database

-- Create schema_migrations table for tracking applied migrations
CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(14) PRIMARY KEY,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_applied (applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create rate_limits table for request throttling
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_key_created (key_name, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Record that we've applied the rate limits migration
INSERT IGNORE INTO schema_migrations (version) VALUES ('20250204');

-- Display confirmation
SELECT 'Migration tables created successfully!' AS status;
SELECT * FROM schema_migrations;
