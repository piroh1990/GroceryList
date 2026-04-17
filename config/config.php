<?php
/**
 * config/config.php
 *
 * Central application configuration.
 * Copy this file or edit in place before running deploy.php.
 */

// ── Database ──────────────────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'grocery_app');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_CHARSET', 'utf8mb4');

// ── Application ───────────────────────────────────────────────────────────────
// Base URL (no trailing slash). Used for redirects and asset paths.
define('APP_BASE_URL', 'http://yourdomain.com/public_html');

// How many bytes of randomness to use when generating a list hash.
// 8 bytes → 16 hex chars, which gives 2^64 possible hashes.
define('HASH_BYTES', 8);

// ── Error reporting ───────────────────────────────────────────────────────────
// Set to false in production.
define('DEBUG_MODE', false);

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
