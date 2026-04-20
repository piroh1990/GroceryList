<?php
/**
 * public_html/api/me.php
 *
 * Returns the currently authenticated user, or null if not logged in.
 *
 * Method: GET
 * Response: { "logged_in": true, "user": { ... } } or { "logged_in": false }
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Method not allowed'], 405);
}

start_auth_session();
$user = get_current_user_row();

if ($user) {
    json_response([
        'logged_in' => true,
        'user'      => $user,
    ]);
} else {
    json_response(['logged_in' => false]);
}
