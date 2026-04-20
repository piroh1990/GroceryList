<?php
/**
 * public_html/api/reset_password.php
 *
 * Resets a user's password using a valid reset token.
 *
 * Method: POST
 * Body:   { "token": "...", "password": "..." }
 * Response: { "success": true } or { "success": false, "error": "..." }
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$input    = json_decode(file_get_contents('php://input'), true);
$token    = trim($input['token'] ?? '');
$password = $input['password'] ?? '';

if ($token === '' || $password === '') {
    json_response(['success' => false, 'error' => 'Token and new password are required.'], 400);
}

$result = reset_password_with_token($token, $password);

if (!$result['success']) {
    json_response($result, 400);
}

json_response(['success' => true, 'message' => 'Password has been reset successfully.']);
