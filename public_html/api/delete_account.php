<?php
/**
 * public_html/api/delete_account.php
 *
 * Permanently deletes the currently logged-in user's account.
 *
 * Method: POST
 * Body:   { "current_password": "..." }
 * Response: { "success": true } or { "success": false, "error": "..." }
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

start_auth_session();
$user = get_current_user_row();

if (!$user) {
    json_response(['success' => false, 'error' => 'Not authenticated.'], 401);
}

$input           = json_decode(file_get_contents('php://input'), true);
$currentPassword = $input['current_password'] ?? '';

if ($currentPassword === '') {
    json_response(['success' => false, 'error' => 'Current password is required.'], 400);
}

$result = delete_user_account((int) $user['id'], $currentPassword);

if (!$result['success']) {
    json_response($result, 400);
}

json_response(['success' => true, 'message' => 'Account deleted successfully.']);
