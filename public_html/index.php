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
require_once __DIR__ . '/../includes/auth.php';

start_auth_session();
$currentUser = get_current_user_row();

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <meta name="description" content="A no-frills shared grocery list – no accounts required." />
    <meta name="theme-color" content="#D95030" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
    <title><?= $listData ? htmlspecialchars($listData['list_name']) . ' – ' : '' ?>Grocery List</title>
    <link rel="stylesheet" href="assets/css/style.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800;900&display=swap" rel="stylesheet" />
</head>
<body>

<!-- ── Header ──────────────────────────────────────────────────────────────── -->
<header class="app-header">
    <a href="index.php" class="logo" aria-label="Go to home">
        <!-- Cart icon (inline SVG – no external dependency) -->
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
             aria-hidden="true">
            <circle cx="9"  cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
        </svg>
        Grocery List
    </a>

    <div class="header-actions">
<?php if ($currentUser): ?>
        <span id="user-greeting" class="user-greeting">👤 <?= htmlspecialchars($currentUser['username']) ?></span>
        <button id="open-sidebar-btn" class="btn btn-outline btn-sm" aria-label="Recent lists">
            ☰ My Lists
        </button>
        <button id="logout-btn" class="btn btn-outline btn-sm" aria-label="Log out">
            Log out
        </button>
<?php else: ?>
        <button id="open-sidebar-btn" class="btn btn-outline btn-sm" aria-label="Recent lists">
            ☰ My Lists
        </button>
        <button id="open-auth-btn" class="btn btn-outline btn-sm" aria-label="Sign in or register">
            Sign in
        </button>
<?php endif; ?>
    </div>
</header>

<!-- ── Main ───────────────────────────────────────────────────────────────── -->
<main class="container">

    <!-- ════════════════════════════════════════════════════════════════
         Home screen – visible when no ?list= param is present
    ═════════════════════════════════════════════════════════════════ -->
    <section id="home-screen"<?= $listData ? ' style="display:none"' : '' ?>>
        <div class="card">
            <h1>Your <span class="accent">grocery</span> run,<br>simplified.</h1>
            <p class="subtitle">Create a shared list in seconds. No sign-up, no fuss&mdash;just a link.</p>

            <form id="create-form" class="form-row" novalidate>
                <input type="text" id="new-list-name"
                       placeholder="Name your list…"
                       maxlength="100"
                       autocomplete="off" />
                <button type="submit" class="btn btn-primary">
                    Create List →
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

            <!-- Item count -->
            <div id="item-count" class="item-count"></div>

            <!-- Item list (server-rendered for fast initial paint) -->
            <ul id="item-list">
<?php if ($listData): ?>
<?php   if (empty($initItems)): ?>
                <li class="empty-list-state">
                    <span class="empty-icon" aria-hidden="true">🛒</span>
                    <p>Your list is empty.<br>Add your first item above!</p>
                </li>
<?php   else: ?>
<?php   foreach ($initItems as $item): ?>
                <li data-item-id="<?= (int) $item['id'] ?>"
                    <?= $item['is_checked'] ? 'class="checked"' : '' ?>>
                    <input type="checkbox"
                           <?= $item['is_checked'] ? 'checked' : '' ?>
                           aria-label="Mark <?= htmlspecialchars($item['item_name']) ?> as done" />
                    <span class="item-name"><?= htmlspecialchars($item['item_name']) ?></span>
                    <button class="btn-delete-item" title="Delete item" aria-label="Delete <?= htmlspecialchars($item['item_name']) ?>">&times;</button>
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

<!-- ── Auth Modal ───────────────────────────────────────────────────────────── -->
<div id="auth-overlay" class="modal-overlay" aria-hidden="true"></div>
<div id="auth-modal" class="modal" role="dialog" aria-label="Sign in or register" aria-hidden="true">
    <div class="modal-header">
        <div class="modal-tabs">
            <button id="tab-login" class="modal-tab active" data-tab="login">Sign in</button>
            <button id="tab-register" class="modal-tab" data-tab="register">Register</button>
        </div>
        <button id="close-auth-btn" class="modal-close" aria-label="Close">&times;</button>
    </div>

    <!-- Login form -->
    <form id="login-form" class="auth-form" novalidate>
        <div class="form-group">
            <label for="login-input">Email or username</label>
            <input type="text" id="login-input" required autocomplete="username" />
        </div>
        <div class="form-group">
            <label for="login-password">Password</label>
            <input type="password" id="login-password" required autocomplete="current-password" />
        </div>
        <div id="login-error" class="auth-error" role="alert"></div>
        <button type="submit" class="btn btn-primary btn-block">Sign in</button>
    </form>

    <!-- Register form -->
    <form id="register-form" class="auth-form" style="display:none" novalidate>
        <div class="form-group">
            <label for="reg-username">Username</label>
            <input type="text" id="reg-username" required minlength="3" maxlength="50"
                   pattern="[a-zA-Z0-9_]+" autocomplete="username" />
        </div>
        <div class="form-group">
            <label for="reg-email">Email</label>
            <input type="email" id="reg-email" required autocomplete="email" />
        </div>
        <div class="form-group">
            <label for="reg-password">Password</label>
            <input type="password" id="reg-password" required minlength="6" autocomplete="new-password" />
        </div>
        <div id="register-error" class="auth-error" role="alert"></div>
        <button type="submit" class="btn btn-primary btn-block">Create account</button>
    </form>
</div>

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
        user:          <?= $currentUser ? json_encode(['id' => $currentUser['id'], 'username' => $currentUser['username'], 'email' => $currentUser['email']]) : 'null' ?>,
    };
</script>
<script src="assets/js/app.js"></script>

</body>
</html>
