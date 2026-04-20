<?php
/**
 * public_html/api/my_lists.php
 *
 * Returns all grocery lists owned by the currently authenticated user.
 *
 * Method: GET
 * Response: { "success": true, "lists": [ { "unique_hash": "...", "list_name": "...", ... }, ... ] }
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Method not allowed'], 405);
}

start_auth_session();
$user = get_current_user_row();

if (!$user) {
    json_response(['success' => false, 'error' => 'Not authenticated'], 401);
}

$pdo  = get_db();
$stmt = $pdo->prepare(
    'SELECT unique_hash, list_name, created_at, last_updated
       FROM grocery_lists
      WHERE owner_id = ?
      ORDER BY last_updated DESC'
);
$stmt->execute([$user['id']]);
$lists = $stmt->fetchAll();

json_response([
    'success' => true,
    'lists'   => $lists,
]);
