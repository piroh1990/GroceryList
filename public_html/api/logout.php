<?php
/**
 * public_html/api/logout.php
 *
 * Logs out the current user (destroys the session).
 *
 * Method: POST
 * Response: { "success": true }
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

logout_user();

json_response(['success' => true]);
