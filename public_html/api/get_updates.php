<?php
/**
 * public_html/api/get_updates.php
 *
 * Short-polling endpoint. Clients call this every POLLING_INTERVAL_MS
 * (10 s) to check whether the list has changed since their last known
 * timestamp.
 *
 * Method: GET
 * Query params:
 *   list_hash  - the list's unique hash
 *   since      - ISO-8601 / MySQL DATETIME string of the client's last known update
 *
 * Responses:
 *   { "changed": false }                          – nothing new; ~0 CPU cost
 *   { "changed": true, "timestamp": "...",
 *     "list_name": "...", "items": [...] }        – full fresh item list
 *   HTTP 400 on bad input
 *   HTTP 404 if list not found
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Access-Control-Allow-Origin: *');
// Tell browsers and proxies not to cache this endpoint.
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Method not allowed'], 405);
}

$list_hash = trim($_GET['list_hash'] ?? '');
$since     = trim($_GET['since']     ?? '');

if (!is_valid_hash($list_hash)) {
    json_response(['error' => 'Invalid list hash'], 400);
}

$list = get_list_by_hash($list_hash);
if (!$list) {
    json_response(['error' => 'List not found'], 404);
}

// ── Core optimisation: compare timestamps before touching list_items ──────────
// If the client already has the latest state, return early with almost no work.
if ($since !== '' && strtotime($list['last_updated']) <= strtotime($since)) {
    json_response(['changed' => false]);
}

// Something changed – return the full fresh item list.
$items = get_items((int) $list['id']);

// Normalize is_checked to boolean for the JS layer.
foreach ($items as &$item) {
    $item['is_checked'] = (bool) $item['is_checked'];
}
unset($item);

// Determine if current user owns this list.
start_auth_session();
$currentUser = get_current_user_row();
$isOwner = $currentUser && isset($list['owner_id']) && (int) $list['owner_id'] === (int) $currentUser['id'];

json_response([
    'changed'   => true,
    'timestamp' => $list['last_updated'],
    'list_name' => $list['list_name'],
    'items'     => $items,
    'is_owner'  => $isOwner,
]);
