<?php
/**
 * public_html/index.php
 *
 * Single entry-point for the Grocery List application.
 *
 * Routes:
 *   ?list=<hash>  →  List view (renders the list with that hash)
 *   (no param)    →  Home / Create screen
 */

require_once __DIR__ . '/../includes/functions.php';

// Determine whether we are in list-view or home-view.
$listHash  = trim($_GET['list'] ?? '');
$listData  = null;
$initItems = [];

if ($listHash !== '') {
    if (!is_valid_hash($listHash)) {
        redirect_home();
    }

    $listData = get_list_by_hash($listHash);
    if (!$listData) {
        redirect_home();
    }

    $initItems = get_items((int) $listData['id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="A no-frills shared grocery list – no accounts required." />
    <title><?= $listData ? htmlspecialchars($listData['list_name']) . ' – ' : '' ?>Grocery List</title>
    <link rel="stylesheet" href="assets/css/style.css" />
    <!-- Preload the share icon font so it renders instantly -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
</head>
<body>

<!-- ── Header ──────────────────────────────────────────────────────────────── -->
<header class="app-header">
    <a href="index.php" class="logo" aria-label="Go to home">
        <!-- Cart icon (inline SVG – no external dependency) -->
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
             aria-hidden="true">
            <circle cx="9"  cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
        </svg>
        Grocery List
    </a>

    <button id="open-sidebar-btn" class="btn btn-outline btn-sm" aria-label="Recent lists">
        ☰ My Lists
    </button>
</header>

<!-- ── Main ───────────────────────────────────────────────────────────────── -->
<main class="container">

    <!-- ════════════════════════════════════════════════════════════════
         Home screen – visible when no ?list= param is present
    ═════════════════════════════════════════════════════════════════ -->
    <section id="home-screen"<?= $listData ? ' style="display:none"' : '' ?>>
        <div class="card">
            <h1>Welcome to Grocery&nbsp;List</h1>
            <p class="subtitle">Create a list and share the link – no account needed.</p>

            <form id="create-form" class="form-row" novalidate>
                <input type="text" id="new-list-name"
                       placeholder="List name (e.g. Home, Office…)"
                       maxlength="100"
                       autocomplete="off" />
                <button type="submit" class="btn btn-primary">
                    + Create List
                </button>
            </form>
        </div>
    </section>

    <!-- ════════════════════════════════════════════════════════════════
         List screen – visible when ?list=<hash> is present
    ═════════════════════════════════════════════════════════════════ -->
    <section id="list-screen"<?= $listData ? '' : ' style="display:none"' ?>>
        <div class="card">

            <!-- Title + rename -->
            <div class="list-header">
                <input id="list-title"
                       type="text"
                       value="<?= $listData ? htmlspecialchars($listData['list_name']) : '' ?>"
                       maxlength="100"
                       aria-label="List name"
                       title="Click to rename" />

                <span id="sync-indicator">
                    <span id="sync-dot"  class="sync-dot"></span>
                    <span id="sync-label"></span>
                </span>
            </div>

            <!-- Add item -->
            <form id="add-item-form" class="add-item-bar" novalidate>
                <input type="text" id="add-item-input"
                       placeholder="Add an item…"
                       maxlength="255"
                       autocomplete="off"
                       autofocus />
                <button type="submit" class="btn btn-primary">Add</button>
            </form>

            <!-- Item list (server-rendered for fast initial paint) -->
            <ul id="item-list">
<?php if ($listData): ?>
<?php   if (empty($initItems)): ?>
                <li style="color:var(--color-muted);padding:.5rem 0;font-size:.9rem;">
                    No items yet – add one above!
                </li>
<?php   else: ?>
<?php   foreach ($initItems as $item): ?>
                <li data-item-id="<?= (int) $item['id'] ?>"
                    <?= $item['is_checked'] ? 'class="checked"' : '' ?>>
                    <input type="checkbox"
                           <?= $item['is_checked'] ? 'checked' : '' ?>
                           aria-label="Mark <?= htmlspecialchars($item['item_name']) ?> as done" />
                    <span class="item-name"><?= htmlspecialchars($item['item_name']) ?></span>
                    <button class="btn-delete-item" title="Delete item">&times;</button>
                </li>
<?php   endforeach; ?>
<?php   endif; ?>
<?php endif; ?>
            </ul>

            <!-- Share -->
            <div class="share-bar">
                <input id="share-url"
                       type="text"
                       class="share-url"
                       readonly
                       aria-label="Share URL" />
                <button id="copy-share-btn" class="btn btn-outline btn-sm">
                    📋 Copy link
                </button>
            </div>

        </div>
    </section>

</main>

<!-- ── Sidebar ─────────────────────────────────────────────────────────────── -->
<div id="sidebar-overlay" aria-hidden="true"></div>
<aside id="sidebar" aria-label="Recent lists">
    <div class="sidebar-header">
        <span>My Recent Lists</span>
        <button id="close-sidebar-btn" aria-label="Close sidebar">&times;</button>
    </div>
    <ul id="recent-lists"></ul>
</aside>

<!-- ── Toast ──────────────────────────────────────────────────────────────── -->
<div id="toast" role="status" aria-live="polite"></div>

<!-- ── Scripts ────────────────────────────────────────────────────────────── -->
<!-- Pass server-rendered data to JS without an extra API call on page load -->
<script>
    window.__APP_DATA__ = {
        listHash:      <?= $listData ? json_encode($listData['unique_hash']) : 'null' ?>,
        lastUpdated:   <?= $listData ? json_encode($listData['last_updated']) : json_encode('2000-01-01 00:00:00') ?>,
    };
</script>
<script src="assets/js/app.js"></script>

</body>
</html>
