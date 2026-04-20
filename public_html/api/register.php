<?php
/**
 * public_html/api/register.php
 *
 * Registers a new user account.
 *
 * Method: POST
 * Body (JSON): { "username": "...", "email": "...", "password": "..." }
 * Response: { "success": true, "user": { "id": ..., "username": "...", "email": "..." } }
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$username = $input['username'] ?? '';
$email    = $input['email']    ?? '';
$password = $input['password'] ?? '';

$result = register_user($username, $email, $password);

if (!$result['success']) {
    json_response(['success' => false, 'error' => $result['error']], 400);
}

json_response([
    'success' => true,
    'user'    => $result['user'],
]);
