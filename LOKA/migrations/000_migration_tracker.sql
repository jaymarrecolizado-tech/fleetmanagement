-- Migration 000: Migration Tracking Table
-- Created: January 22, 2026
-- Purpose: Track executed migrations for version control

CREATE TABLE IF NOT EXISTS schema_migrations (
    migration VARCHAR(255) PRIMARY KEY,
    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
