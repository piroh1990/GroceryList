<?php
/**
 * public_html/api/update_item.php
 *
 * Toggles the checked state of a list item.
 *
 * Method: POST
 * Body (JSON or form): { "list_hash": "<hash>", "item_id": 1, "is_checked": 1 }
 * Response: { "success": true }
 */

require_once __DIR__ . '/../../includes/functions.php';

header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$input      = json_decode(file_get_contents('php://input'), true) ?? [];
$list_hash  = trim($input['list_hash']  ?? $_POST['list_hash']  ?? '');
$item_id    = (int) ($input['item_id']   ?? $_POST['item_id']   ?? 0);
$is_checked = (int) ($input['is_checked'] ?? $_POST['is_checked'] ?? 0);

if (!is_valid_hash($list_hash)) {
    json_response(['error' => 'Invalid list hash'], 400);
}

if ($item_id <= 0) {
    json_response(['error' => 'Invalid item_id'], 400);
}

$is_checked = $is_checked ? 1 : 0;

$list = get_list_by_hash($list_hash);
if (!$list) {
    json_response(['error' => 'List not found'], 404);
}

$pdo = get_db();

// Verify the item actually belongs to this list.
$stmt = $pdo->prepare('SELECT id FROM list_items WHERE id = ? AND list_id = ?');
$stmt->execute([$item_id, $list['id']]);
if (!$stmt->fetch()) {
    json_response(['error' => 'Item not found'], 404);
}

$pdo->prepare('UPDATE list_items SET is_checked = ? WHERE id = ?')
    ->execute([$is_checked, $item_id]);

// Touch last_updated so polling clients detect the change.
$pdo->prepare('UPDATE grocery_lists SET last_updated = NOW() WHERE id = ?')
    ->execute([$list['id']]);

json_response(['success' => true]);
