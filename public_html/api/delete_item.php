<?php
/**
 * public_html/api/delete_item.php
 *
 * Deletes an item from a grocery list.
 *
 * Method: POST
 * Body (JSON or form): { "list_hash": "<hash>", "item_id": 1 }
 * Response: { "success": true }
 */

require_once __DIR__ . '/../../includes/functions.php';

header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$list_hash = trim($input['list_hash'] ?? $_POST['list_hash'] ?? '');
$item_id   = (int) ($input['item_id'] ?? $_POST['item_id'] ?? 0);

if (!is_valid_hash($list_hash)) {
    json_response(['error' => 'Invalid list hash'], 400);
}

if ($item_id <= 0) {
    json_response(['error' => 'Invalid item_id'], 400);
}

$list = get_list_by_hash($list_hash);
if (!$list) {
    json_response(['error' => 'List not found'], 404);
}

$pdo = get_db();

// Verify the item belongs to this list before deleting.
$stmt = $pdo->prepare('SELECT id FROM list_items WHERE id = ? AND list_id = ?');
$stmt->execute([$item_id, $list['id']]);
if (!$stmt->fetch()) {
    json_response(['error' => 'Item not found'], 404);
}

$pdo->prepare('DELETE FROM list_items WHERE id = ?')->execute([$item_id]);

// Touch last_updated so polling clients detect the change.
$pdo->prepare('UPDATE grocery_lists SET last_updated = NOW() WHERE id = ?')
    ->execute([$list['id']]);

json_response(['success' => true]);
