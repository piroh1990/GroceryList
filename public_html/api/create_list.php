<?php
/**
 * public_html/api/create_list.php
 *
 * Creates a new grocery list and returns the unique hash.
 * If the user is logged in, the list is associated with their account.
 *
 * Method: POST
 * Body (JSON or form): { "list_name": "My List" }   (optional)
 * Response: { "success": true, "hash": "<16-char hex>", "list_name": "..." }
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

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

// Associate with logged-in user if available.
start_auth_session();
$user    = get_current_user_row();
$ownerId = $user ? $user['id'] : null;

$stmt = $pdo->prepare(
    'INSERT INTO grocery_lists (unique_hash, list_name, owner_id) VALUES (?, ?, ?)'
);
$stmt->execute([$hash, $list_name, $ownerId]);

json_response([
    'success'   => true,
    'hash'      => $hash,
    'list_name' => $list_name,
]);
