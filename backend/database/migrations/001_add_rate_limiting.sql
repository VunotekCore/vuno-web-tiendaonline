-- Migration 001: Add rate_limiting table
-- Execute only if table does not exist

CREATE TABLE IF NOT EXISTS rate_limiting (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    namespace   VARCHAR(50) NOT NULL,
    identifier  VARCHAR(64) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lookup (namespace, identifier, created_at)
) ENGINE=InnoDB;
