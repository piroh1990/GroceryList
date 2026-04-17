<?php
/**
 * public_html/api/rename_list.php
 *
 * Renames a grocery list.
 *
 * Method: POST
 * Body (JSON or form): { "list_hash": "<hash>", "list_name": "New Name" }
 * Response: { "success": true }
 */

require_once __DIR__ . '/../../includes/functions.php';

header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$list_hash = trim($input['list_hash'] ?? $_POST['list_hash'] ?? '');
$list_name = trim($input['list_name'] ?? $_POST['list_name'] ?? '');

if (!is_valid_hash($list_hash)) {
    json_response(['error' => 'Invalid list hash'], 400);
}

if ($list_name === '') {
    json_response(['error' => 'list_name is required'], 400);
}

$list_name = mb_substr($list_name, 0, 100);

$list = get_list_by_hash($list_hash);
if (!$list) {
    json_response(['error' => 'List not found'], 404);
}

$pdo = get_db();
$pdo->prepare('UPDATE grocery_lists SET list_name = ?, last_updated = NOW() WHERE id = ?')
    ->execute([$list_name, $list['id']]);

json_response(['success' => true]);
