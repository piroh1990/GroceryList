-- scripts/db.schema.sql
--
-- Full database schema for the Grocery List application.
-- Run this file via scripts/deploy.php or import it manually:
--   mysql -u <user> -p <dbname> < scripts/db.schema.sql
--
-- NOTE: The database itself must already exist. deploy.php reads the
-- database name from config/config.php (DB_NAME) and connects to it
-- automatically, so no CREATE DATABASE or USE statement is needed here.

-- ── grocery_lists ─────────────────────────────────────────────────────────────
-- Each row represents one shared grocery list identified by its unique_hash.
-- The last_updated column is used by the short-polling endpoint to avoid
-- querying list_items unless a change has actually occurred.
CREATE TABLE IF NOT EXISTS grocery_lists (
    id           INT           AUTO_INCREMENT PRIMARY KEY,
    unique_hash  VARCHAR(64)   UNIQUE NOT NULL,
    list_name    VARCHAR(100)  DEFAULT 'My Grocery List',
    created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    last_updated TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── list_items ────────────────────────────────────────────────────────────────
-- Each row is a single item inside a grocery list.
-- Deleting a list cascades and removes all its items automatically.
CREATE TABLE IF NOT EXISTS list_items (
    id         INT           AUTO_INCREMENT PRIMARY KEY,
    list_id    INT           NOT NULL,
    item_name  VARCHAR(255)  NOT NULL,
    is_checked TINYINT(1)    DEFAULT 0,
    created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (list_id) REFERENCES grocery_lists(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── users ─────────────────────────────────────────────────────────────────────
-- Registered users. email must be unique. Password is stored as a bcrypt hash.
CREATE TABLE IF NOT EXISTS users (
    id         INT           AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)   UNIQUE NOT NULL,
    email      VARCHAR(255)  UNIQUE NOT NULL,
    password   VARCHAR(255)  NOT NULL,
    created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add owner_id column to grocery_lists if it doesn't exist.
-- This links a list to the user who created it (NULL for anonymous lists).
ALTER TABLE grocery_lists
    ADD COLUMN IF NOT EXISTS owner_id INT DEFAULT NULL;

-- Add foreign-key constraint linking owner_id → users(id).
-- NOTE: IF NOT EXISTS for FOREIGN KEY is only supported in MariaDB 10.5.2+;
-- on older versions or MySQL the deploy script will catch the "already exists"
-- error and continue safely.
ALTER TABLE grocery_lists
    ADD CONSTRAINT fk_grocery_lists_owner
        FOREIGN KEY IF NOT EXISTS (owner_id) REFERENCES users(id) ON DELETE SET NULL;
