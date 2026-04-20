<?php
/**
 * public_html/api/forgot_password.php
 *
 * Creates a password-reset token for the given email address.
 * In a production app this would send an email; here we return the token
 * directly so the user can proceed with the reset flow in the UI.
 *
 * Method: POST
 * Body:   { "email": "..." }
 * Response: { "success": true, "message": "..." }
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if ($email === '') {
    json_response(['success' => false, 'error' => 'Email is required.'], 400);
}

$result = create_password_reset_token($email);

if (!$result['success']) {
    json_response($result, 400);
}

// Always return a generic success message to not reveal whether the email exists.
// Include the token so the client-side reset flow can proceed (since we have no email service).
json_response([
    'success' => true,
    'message' => 'If an account with that email exists, a reset token has been generated.',
    'token'   => $result['token'] ?? null,
]);
