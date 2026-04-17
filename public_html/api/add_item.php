<?php
/**
 * public_html/api/add_item.php
 *
 * Adds an item to a grocery list.
 *
 * Method: POST
 * Body (JSON or form): { "list_hash": "<hash>", "item_name": "Milk" }
 * Response: { "success": true, "item": { "id": 1, "item_name": "Milk", "is_checked": 0 } }
 */

require_once __DIR__ . '/../../includes/functions.php';

header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$list_hash = trim($input['list_hash'] ?? $_POST['list_hash'] ?? '');
$item_name = trim($input['item_name'] ?? $_POST['item_name'] ?? '');

if (!is_valid_hash($list_hash)) {
    json_response(['error' => 'Invalid list hash'], 400);
}

if ($item_name === '') {
    json_response(['error' => 'item_name is required'], 400);
}

$item_name = mb_substr($item_name, 0, 255);

$list = get_list_by_hash($list_hash);
if (!$list) {
    json_response(['error' => 'List not found'], 404);
}

$pdo  = get_db();
$stmt = $pdo->prepare('INSERT INTO list_items (list_id, item_name) VALUES (?, ?)');
$stmt->execute([$list['id'], $item_name]);
$newId = (int) $pdo->lastInsertId();

// Touch last_updated on the parent list so polling detects the change.
$pdo->prepare('UPDATE grocery_lists SET last_updated = NOW() WHERE id = ?')
    ->execute([$list['id']]);

json_response([
    'success' => true,
    'item'    => [
        'id'         => $newId,
        'item_name'  => $item_name,
        'is_checked' => 0,
    ],
]);
