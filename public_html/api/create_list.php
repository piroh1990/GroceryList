<?php
/**
 * public_html/api/create_list.php
 *
 * Creates a new grocery list and returns the unique hash.
 *
 * Method: POST
 * Body (JSON or form): { "list_name": "My List" }   (optional)
 * Response: { "success": true, "hash": "<16-char hex>", "list_name": "..." }
 */

require_once __DIR__ . '/../../includes/functions.php';

header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

// Accept JSON body or form data.
$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$list_name = trim($input['list_name'] ?? $_POST['list_name'] ?? 'My Grocery List');
$list_name = mb_substr($list_name, 0, 100);

if ($list_name === '') {
    $list_name = 'My Grocery List';
}

$hash = generate_hash();
$pdo  = get_db();

$stmt = $pdo->prepare(
    'INSERT INTO grocery_lists (unique_hash, list_name) VALUES (?, ?)'
);
$stmt->execute([$hash, $list_name]);

json_response([
    'success'   => true,
    'hash'      => $hash,
    'list_name' => $list_name,
]);
