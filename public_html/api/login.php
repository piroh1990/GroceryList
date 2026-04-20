<?php
/**
 * public_html/api/login.php
 *
 * Authenticates a user and starts a session.
 *
 * Method: POST
 * Body (JSON): { "login": "email_or_username", "password": "..." }
 * Response: { "success": true, "user": { "id": ..., "username": "...", "email": "..." } }
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$login    = $input['login']    ?? '';
$password = $input['password'] ?? '';

$result = login_user($login, $password);

if (!$result['success']) {
    json_response(['success' => false, 'error' => $result['error']], 401);
}

json_response([
    'success' => true,
    'user'    => $result['user'],
]);
