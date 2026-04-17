<?php
/**
 * includes/functions.php
 *
 * Shared utility functions for the Grocery List application.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

/**
 * Send a JSON response and exit.
 *
 * @param mixed $data        Data to encode as JSON.
 * @param int   $statusCode  HTTP status code (default 200).
 */
function json_response($data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Generate a secure, URL-safe list hash.
 *
 * @return string  Hex string of length HASH_BYTES * 2.
 */
function generate_hash(): string
{
    return bin2hex(random_bytes(HASH_BYTES));
}

/**
 * Validate that a list hash is syntactically correct (hex, correct length).
 *
 * @param string $hash
 * @return bool
 */
function is_valid_hash(string $hash): bool
{
    $expectedLength = HASH_BYTES * 2;
    return preg_match('/^[0-9a-f]{' . $expectedLength . '}$/', $hash) === 1;
}

/**
 * Fetch a list row by its unique hash, or return null if not found.
 *
 * @param string $hash
 * @return array|null
 */
function get_list_by_hash(string $hash): ?array
{
    $pdo  = get_db();
    $stmt = $pdo->prepare('SELECT * FROM grocery_lists WHERE unique_hash = ? LIMIT 1');
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Fetch all items for a given list (by internal list ID), ordered oldest-first.
 *
 * @param int $listId
 * @return array
 */
function get_items(int $listId): array
{
    $pdo  = get_db();
    $stmt = $pdo->prepare(
        'SELECT id, item_name, is_checked, created_at
           FROM list_items
          WHERE list_id = ?
          ORDER BY id ASC'
    );
    $stmt->execute([$listId]);
    return $stmt->fetchAll();
}

/**
 * Redirect to the home page.
 */
function redirect_home(): void
{
    header('Location: ' . APP_BASE_URL . '/index.php');
    exit;
}
