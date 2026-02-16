-- Migration: Add rate_limits table for request throttling
-- Date: 2025-02-04

CREATE TABLE IF NOT EXISTS rate_limits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  key_name VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_key_created (key_name, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: Old entries can be cleaned up with:
-- DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);
