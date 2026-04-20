<?php
/**
 * public_html/api/update_profile.php
 *
 * Updates the currently logged-in user's email or password.
 *
 * Method: POST
 * Body:   { "action": "email"|"password", "current_password": "...", ... }
 *   - For "email":    { "action": "email", "current_password": "...", "new_email": "..." }
 *   - For "password": { "action": "password", "current_password": "...", "new_password": "..." }
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
$action          = $input['action'] ?? '';
$currentPassword = $input['current_password'] ?? '';

if ($currentPassword === '') {
    json_response(['success' => false, 'error' => 'Current password is required.'], 400);
}

switch ($action) {
    case 'email':
        $newEmail = trim($input['new_email'] ?? '');
        if ($newEmail === '') {
            json_response(['success' => false, 'error' => 'New email is required.'], 400);
        }
        $result = update_user_email((int) $user['id'], $newEmail, $currentPassword);
        break;

    case 'password':
        $newPassword = $input['new_password'] ?? '';
        if ($newPassword === '') {
            json_response(['success' => false, 'error' => 'New password is required.'], 400);
        }
        $result = update_user_password((int) $user['id'], $currentPassword, $newPassword);
        break;

    default:
        json_response(['success' => false, 'error' => 'Invalid action. Use "email" or "password".'], 400);
}

if (!$result['success']) {
    json_response($result, 400);
}

json_response(['success' => true]);
