<?php
/**
 * scripts/deploy.php
 *
 * Run this script from the command line or via a browser (if you temporarily
 * allow web access) after every schema change to keep the database in sync.
 *
 * Usage:
 *   php scripts/deploy.php
 *
 * What it does:
 *   1. Reads config/config.php for DB credentials.
 *   2. Reads scripts/db.schema.sql and executes each statement.
 *   3. Reports success or failure for each statement.
 *
 * NOTE: The schema uses CREATE DATABASE IF NOT EXISTS and
 *       CREATE TABLE IF NOT EXISTS, so it is safe to run repeatedly.
 */

// ── Bootstrap ─────────────────────────────────────────────────────────────────
define('RUNNING_DEPLOY', true);

$configFile = __DIR__ . '/../config/config.php';
$schemaFile = __DIR__ . '/db.schema.sql';

if (!file_exists($configFile)) {
    die("ERROR: config/config.php not found. Copy config/config.example.php to config/config.php and fill in your credentials.\n");
}

if (!file_exists($schemaFile)) {
    die("ERROR: scripts/db.schema.sql not found.\n");
}

require_once $configFile;

// ── Connect (without selecting a specific DB so we can CREATE it) ─────────────
$dsn = sprintf('mysql:host=%s;port=%d;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    die("ERROR: Could not connect to MySQL server.\n" . $e->getMessage() . "\n");
}

// ── Parse and execute the schema ──────────────────────────────────────────────
$sql = file_get_contents($schemaFile);

// Strip SQL comments (-- ... and /* ... */) so they are not treated as statements.
$sql = preg_replace('/--[^\n]*\n/', "\n", $sql);
$sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

// Split on semicolons.
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    fn(string $s) => $s !== ''
);

$success = 0;
$errors  = 0;

echo "Running deploy.php …\n\n";

foreach ($statements as $statement) {
    try {
        $pdo->exec($statement);
        // Print a short summary of the statement (first 80 chars).
        $preview = substr(preg_replace('/\s+/', ' ', $statement), 0, 80);
        echo "  OK  » {$preview}\n";
        $success++;
    } catch (PDOException $e) {
        $preview = substr(preg_replace('/\s+/', ' ', $statement), 0, 80);
        echo "  ERR » {$preview}\n       " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\nDone. {$success} statement(s) applied, {$errors} error(s).\n";
