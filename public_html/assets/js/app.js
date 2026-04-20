/**
 * public_html/assets/js/app.js
 *
 * Grocery List – vanilla ES6+ front-end.
 *
 * Responsibilities:
 *  • Home screen  – create a list, redirect to list view
 *  • List screen  – render items, add / check / delete via fetch API
 *  • Short polling – ask the server for updates every POLLING_INTERVAL_MS
 *  • localStorage  – remember "Recent Lists" for quick navigation
 *  • Sidebar       – show / hide recent lists panel
 *  • Micro-interactions – item animations, swipe-to-delete, item counts
 */

'use strict';

// ── Configuration ─────────────────────────────────────────────────────────────
// Change this value to adjust how often the client checks for updates.
const POLLING_INTERVAL_MS = 10000;

// Delay (ms) before reloading the page after auth actions (login/register/logout).
const AUTH_RELOAD_DELAY = 600;

// ── Derived API base URL ──────────────────────────────────────────────────────
// Works whether the page is served from the web root or a subdirectory.
const API_BASE = (() => {
    const parts = location.pathname.split('/');
    parts.pop(); // remove index.php (or empty string for trailing slash)
    return location.origin + parts.join('/') + '/api';
})();

// ── State ─────────────────────────────────────────────────────────────────────
let currentHash        = null;
let lastKnownUpdate    = '2000-01-01 00:00:00';
let pollingTimer       = null;
let currentUser        = null;

// ── DOM refs (populated in init) ──────────────────────────────────────────────
let homeScreen, listScreen;
let createForm, newListNameInput;
let listTitleInput, itemList, itemCountEl;
let addItemForm, addItemInput;
let shareUrlInput, copyShareBtn;
let syncDot, syncLabel;
let sidebarOverlay, sidebar, recentListsEl;
let toastEl;
let authOverlay, authModal;
let loginForm, registerForm;
let loginError, registerError;

// ── Initialization ────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    homeScreen      = document.getElementById('home-screen');
    listScreen      = document.getElementById('list-screen');
    createForm      = document.getElementById('create-form');
    newListNameInput = document.getElementById('new-list-name');
    listTitleInput  = document.getElementById('list-title');
    itemList        = document.getElementById('item-list');
    itemCountEl     = document.getElementById('item-count');
    addItemForm     = document.getElementById('add-item-form');
    addItemInput    = document.getElementById('add-item-input');
    shareUrlInput   = document.getElementById('share-url');
    copyShareBtn    = document.getElementById('copy-share-btn');
    syncDot         = document.getElementById('sync-dot');
    syncLabel       = document.getElementById('sync-label');
    sidebarOverlay  = document.getElementById('sidebar-overlay');
    sidebar         = document.getElementById('sidebar');
    recentListsEl   = document.getElementById('recent-lists');
    toastEl         = document.getElementById('toast');
    authOverlay     = document.getElementById('auth-overlay');
    authModal       = document.getElementById('auth-modal');
    loginForm       = document.getElementById('login-form');
    registerForm    = document.getElementById('register-form');
    loginError      = document.getElementById('login-error');
    registerError   = document.getElementById('register-error');

    // Track logged-in user from server-rendered data.
    currentUser = (window.__APP_DATA__ || {}).user || null;

    // Bind events.
    createForm.addEventListener('submit', handleCreateList);
    addItemForm.addEventListener('submit', handleAddItem);
    copyShareBtn.addEventListener('click', handleCopyShare);
    listTitleInput.addEventListener('change', handleRenameList);

    document.getElementById('open-sidebar-btn').addEventListener('click', openSidebar);
    document.getElementById('close-sidebar-btn').addEventListener('click', closeSidebar);
    sidebarOverlay.addEventListener('click', closeSidebar);

    // Auth event bindings.
    const openAuthBtn = document.getElementById('open-auth-btn');
    const logoutBtn   = document.getElementById('logout-btn');
    if (openAuthBtn) openAuthBtn.addEventListener('click', openAuthModal);
    if (logoutBtn)   logoutBtn.addEventListener('click', handleLogout);

    // Profile button binding.
    const openProfileBtn = document.getElementById('open-profile-btn');
    if (openProfileBtn) openProfileBtn.addEventListener('click', openProfileModal);

    if (authModal) {
        document.getElementById('close-auth-btn').addEventListener('click', closeAuthModal);
        authOverlay.addEventListener('click', closeAuthModal);
        document.getElementById('tab-login').addEventListener('click', () => switchAuthTab('login'));
        document.getElementById('tab-register').addEventListener('click', () => switchAuthTab('register'));
        loginForm.addEventListener('submit', handleLogin);
        registerForm.addEventListener('submit', handleRegister);

        // Forgot password flow bindings
        const forgotLink = document.getElementById('forgot-password-link');
        if (forgotLink) forgotLink.addEventListener('click', (e) => { e.preventDefault(); showForgotForm(); });

        const backToLoginLink = document.getElementById('back-to-login-link');
        if (backToLoginLink) backToLoginLink.addEventListener('click', (e) => { e.preventDefault(); backToLogin(); });

        const backToLoginLink2 = document.getElementById('back-to-login-link-2');
        if (backToLoginLink2) backToLoginLink2.addEventListener('click', (e) => { e.preventDefault(); backToLogin(); });

        const forgotForm = document.getElementById('forgot-form');
        if (forgotForm) forgotForm.addEventListener('submit', handleForgotPassword);

        const resetForm = document.getElementById('reset-form');
        if (resetForm) resetForm.addEventListener('submit', handleResetPassword);
    }

    // Profile modal bindings
    const profileModal   = document.getElementById('profile-modal');
    const profileOverlay = document.getElementById('profile-overlay');
    if (profileModal) {
        document.getElementById('close-profile-btn').addEventListener('click', closeProfileModal);
        profileOverlay.addEventListener('click', closeProfileModal);
        document.getElementById('change-email-form').addEventListener('submit', handleChangeEmail);
        document.getElementById('change-password-form').addEventListener('submit', handleChangePassword);
        document.getElementById('delete-account-form').addEventListener('submit', handleDeleteAccount);
    }

    // Close sidebar, auth modal, or profile modal on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (sidebar.classList.contains('open')) closeSidebar();
            if (authModal && !authModal.getAttribute('aria-hidden')?.includes('true')) closeAuthModal();
            const profileModal = document.getElementById('profile-modal');
            if (profileModal && !profileModal.getAttribute('aria-hidden')?.includes('true')) closeProfileModal();
        }
    });

    // Use server-injected data if available (avoids redundant initial API call).
    const appData = window.__APP_DATA__ || {};

    // Route based on URL.
    const params = new URLSearchParams(location.search);
    currentHash  = appData.listHash || params.get('list');

    if (currentHash) {
        // Page was server-rendered with fresh data; start polling from that timestamp.
        if (appData.lastUpdated) {
            lastKnownUpdate = appData.lastUpdated;
        }
        showListScreen(currentHash, /* skipInitialPoll */ !!appData.lastUpdated);
    } else {
        showHomeScreen();
    }
});

// ── Screen management ─────────────────────────────────────────────────────────
function showHomeScreen() {
    homeScreen.style.display = 'flex';
    listScreen.style.display = 'none';
    stopPolling();
    renderRecentLists();
}

async function showListScreen(hash, skipInitialPoll = false) {
    homeScreen.style.display = 'none';
    listScreen.style.display = 'block';

    // Set share URL.
    shareUrlInput.value = location.origin + location.pathname + '?list=' + hash;

    if (!skipInitialPoll) {
        // Load initial data (force first poll by resetting timestamp).
        lastKnownUpdate = '2000-01-01 00:00:00';
        await pollForUpdates();
    } else {
        setSyncState('synced');
        // Wire up event listeners for server-rendered items.
        wireExistingItems();
        updateItemCount();
    }
    startPolling();
}

// ── Wire event listeners onto server-rendered items ───────────────────────────
function wireExistingItems() {
    itemList.querySelectorAll('li[data-item-id]').forEach(li => {
        const itemId   = parseInt(li.dataset.itemId, 10);
        const checkbox = li.querySelector('input[type="checkbox"]');
        const delBtn   = li.querySelector('.btn-delete-item');

        if (checkbox) {
            checkbox.addEventListener('change', () => {
                li.classList.toggle('checked', checkbox.checked);
                handleToggleItem(itemId, checkbox.checked);
                updateItemCount();
            });
        }
        if (delBtn) {
            delBtn.addEventListener('click', () => handleDeleteItem(itemId));
        }

        // Swipe-to-delete on touch devices
        setupSwipeToDelete(li, itemId);
    });
}

// ── Swipe-to-delete ───────────────────────────────────────────────────────────
function setupSwipeToDelete(li, itemId) {
    let startX = 0;
    let currentX = 0;
    let swiping = false;

    li.addEventListener('touchstart', (e) => {
        startX = e.touches[0].clientX;
        currentX = startX;
        swiping = true;
        li.style.transition = 'none';
    }, { passive: true });

    li.addEventListener('touchmove', (e) => {
        if (!swiping) return;
        currentX = e.touches[0].clientX;
        const dx = currentX - startX;
        // Only allow left swipe
        if (dx < 0) {
            const capped = Math.max(dx, -120);
            li.style.transform = `translateX(${capped}px)`;
            li.style.opacity = String(1 - Math.abs(capped) / 200);
        }
    }, { passive: true });

    li.addEventListener('touchend', () => {
        if (!swiping) return;
        swiping = false;
        const dx = currentX - startX;
        li.style.transition = '';

        if (dx < -80) {
            // Threshold passed – delete
            handleDeleteItem(itemId);
        } else {
            // Snap back
            li.style.transform = '';
            li.style.opacity = '';
        }
    }, { passive: true });
}

// ── Create list ───────────────────────────────────────────────────────────────
async function handleCreateList(e) {
    e.preventDefault();
    const name = newListNameInput.value.trim() || 'My Grocery List';

    try {
        const res  = await apiFetch('create_list.php', 'POST', { list_name: name });
        const data = await res.json();

        if (!data.success) throw new Error(data.error || 'Unknown error');

        saveRecentList(data.hash, data.list_name);
        location.href = location.pathname + '?list=' + data.hash;
    } catch (err) {
        showToast('Could not create list: ' + err.message);
    }
}

// ── Add item ──────────────────────────────────────────────────────────────────
async function handleAddItem(e) {
    e.preventDefault();
    const name = addItemInput.value.trim();
    if (!name) return;

    try {
        const res  = await apiFetch('add_item.php', 'POST', { list_hash: currentHash, item_name: name });
        const data = await res.json();

        if (!data.success) throw new Error(data.error || 'Unknown error');

        addItemInput.value = '';

        // Remove empty state if present
        const emptyState = itemList.querySelector('.empty-list-state');
        if (emptyState) emptyState.remove();

        appendItem(data.item);
        updateItemCount();
        // Touch the local timestamp so the next poll does not overwrite
        // the optimistic update before the server echoes the change.
        lastKnownUpdate = new Date().toISOString();
    } catch (err) {
        showToast('Could not add item: ' + err.message);
    }
}

// ── Toggle item ───────────────────────────────────────────────────────────────
async function handleToggleItem(itemId, isChecked) {
    try {
        const res  = await apiFetch('update_item.php', 'POST', {
            list_hash:  currentHash,
            item_id:    itemId,
            is_checked: isChecked ? 1 : 0,
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Unknown error');
    } catch (err) {
        showToast('Could not update item: ' + err.message);
    }
}

// ── Delete item ───────────────────────────────────────────────────────────────
async function handleDeleteItem(itemId) {
    try {
        const res  = await apiFetch('delete_item.php', 'POST', {
            list_hash: currentHash,
            item_id:   itemId,
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Unknown error');

        const li = document.querySelector(`[data-item-id="${itemId}"]`);
        if (li) {
            // Animate removal
            li.classList.add('removing');
            li.addEventListener('transitionend', () => {
                li.remove();
                updateItemCount();
                showEmptyStateIfNeeded();
            }, { once: true });
            // Fallback in case transitionend doesn't fire
            setTimeout(() => {
                if (li.parentNode) {
                    li.remove();
                    updateItemCount();
                    showEmptyStateIfNeeded();
                }
            }, 600);
        }
    } catch (err) {
        showToast('Could not delete item: ' + err.message);
    }
}

// ── Rename list ───────────────────────────────────────────────────────────────
async function handleRenameList() {
    const newName = listTitleInput.value.trim();
    if (!newName) return;

    try {
        const res  = await apiFetch('rename_list.php', 'POST', {
            list_hash: currentHash,
            list_name: newName,
        });
        const data = await res.json();
        if (data.success) {
            updateRecentListName(currentHash, newName);
            showToast('List renamed.');
        }
    } catch {
        // Non-critical – silently ignore rename errors.
    }
}

// ── Copy share URL ────────────────────────────────────────────────────────────
function handleCopyShare() {
    navigator.clipboard.writeText(shareUrlInput.value)
        .then(() => showToast('Link copied to clipboard!'))
        .catch(() => {
            shareUrlInput.select();
            showToast('Press Ctrl+C to copy the link.');
        });
}

// ── Short-polling ─────────────────────────────────────────────────────────────
function startPolling() {
    stopPolling();
    pollingTimer = setInterval(pollForUpdates, POLLING_INTERVAL_MS);
}

function stopPolling() {
    if (pollingTimer !== null) {
        clearInterval(pollingTimer);
        pollingTimer = null;
    }
}

async function pollForUpdates() {
    setSyncState('syncing');
    try {
        const url = `${API_BASE}/get_updates.php?list_hash=${encodeURIComponent(currentHash)}&since=${encodeURIComponent(lastKnownUpdate)}`;
        const res  = await fetch(url, { cache: 'no-store' });

        if (!res.ok) {
            if (res.status === 404) {
                // List was deleted – go home.
                stopPolling();
                location.href = location.pathname;
            }
            throw new Error('HTTP ' + res.status);
        }

        const data = await res.json();

        if (data.changed) {
            lastKnownUpdate = data.timestamp;
            listTitleInput.value = data.list_name;
            renderItems(data.items);
            saveRecentList(currentHash, data.list_name);
        }

        setSyncState('synced');
    } catch (err) {
        setSyncState('error');
    }
}

// ── DOM rendering ─────────────────────────────────────────────────────────────
function renderItems(items) {
    itemList.innerHTML = '';
    if (items.length === 0) {
        showEmptyState();
        updateItemCount();
        return;
    }
    items.forEach(appendItem);
    updateItemCount();
}

function appendItem(item) {
    const li = document.createElement('li');
    li.dataset.itemId = item.id;
    if (item.is_checked) li.classList.add('checked');

    const checkbox = document.createElement('input');
    checkbox.type    = 'checkbox';
    checkbox.checked = !!item.is_checked;
    checkbox.setAttribute('aria-label', 'Mark ' + item.item_name + ' as done');
    checkbox.addEventListener('change', () => {
        li.classList.toggle('checked', checkbox.checked);
        handleToggleItem(item.id, checkbox.checked);
        updateItemCount();
    });

    const nameSpan = document.createElement('span');
    nameSpan.className   = 'item-name';
    nameSpan.textContent = item.item_name;

    const delBtn = document.createElement('button');
    delBtn.className = 'btn-delete-item';
    delBtn.title     = 'Delete item';
    delBtn.setAttribute('aria-label', 'Delete ' + item.item_name);
    delBtn.innerHTML = '&times;';
    delBtn.addEventListener('click', () => handleDeleteItem(item.id));

    li.append(checkbox, nameSpan, delBtn);
    itemList.appendChild(li);

    // Setup swipe-to-delete
    setupSwipeToDelete(li, item.id);
}

// ── Item count display ────────────────────────────────────────────────────────
function updateItemCount() {
    if (!itemCountEl) return;
    const allItems = itemList.querySelectorAll('li[data-item-id]');
    const total = allItems.length;
    const checked = itemList.querySelectorAll('li[data-item-id].checked').length;

    if (total === 0) {
        itemCountEl.textContent = '';
        return;
    }

    const remaining = total - checked;
    if (checked === total) {
        itemCountEl.textContent = `All ${total} item${total !== 1 ? 's' : ''} done ✓`;
    } else {
        itemCountEl.textContent = `${remaining} remaining · ${checked} done · ${total} total`;
    }
}

// ── Empty state helpers ───────────────────────────────────────────────────────
function showEmptyState() {
    itemList.innerHTML = '';
    const li = document.createElement('li');
    li.className = 'empty-list-state';
    li.innerHTML = '<span class="empty-icon" aria-hidden="true">🛒</span>' +
                   '<p>Your list is empty.<br>Add your first item above!</p>';
    itemList.appendChild(li);
}

function showEmptyStateIfNeeded() {
    const items = itemList.querySelectorAll('li[data-item-id]');
    if (items.length === 0) {
        showEmptyState();
    }
}

// ── Sync indicator ────────────────────────────────────────────────────────────
function setSyncState(state) {
    syncDot.className = 'sync-dot ' + state;
    const labels = { syncing: 'Syncing…', synced: 'Up to date', error: 'Sync error' };
    syncLabel.textContent = labels[state] ?? '';
}

// ── Sidebar – Recent Lists ────────────────────────────────────────────────────
function openSidebar() {
    renderRecentLists();
    sidebar.classList.add('open');
    sidebarOverlay.style.display = 'block';
    // Trigger reflow for animation
    requestAnimationFrame(() => {
        sidebarOverlay.classList.add('visible');
    });
}

function closeSidebar() {
    sidebar.classList.remove('open');
    sidebarOverlay.classList.remove('visible');
    setTimeout(() => {
        sidebarOverlay.style.display = 'none';
    }, 250);
}

function renderRecentLists() {
    const lists = getRecentLists();
    recentListsEl.innerHTML = '';

    if (lists.length === 0) {
        recentListsEl.innerHTML = '<li class="empty-state">No recent lists yet.<br>Create one to get started!</li>';
        return;
    }

    [...lists].reverse().forEach(({ hash, name }) => {
        const li     = document.createElement('li');
        const span1  = document.createElement('span');
        const span2  = document.createElement('span');

        span1.className   = 'list-item-name';
        span1.textContent = name;
        span2.className   = 'list-item-hash';
        span2.textContent = hash;

        li.append(span1, span2);
        li.addEventListener('click', () => {
            closeSidebar();
            location.href = location.pathname + '?list=' + hash;
        });

        recentListsEl.appendChild(li);
    });
}

// ── localStorage helpers ──────────────────────────────────────────────────────
const LS_KEY = 'my_grocery_lists';

function getRecentLists() {
    try {
        return JSON.parse(localStorage.getItem(LS_KEY)) || [];
    } catch {
        return [];
    }
}

function saveRecentList(hash, name) {
    let lists = getRecentLists();
    const idx = lists.findIndex(l => l.hash === hash);
    if (idx !== -1) {
        lists[idx].name = name; // update name if it changed
    } else {
        lists.push({ hash, name });
    }
    localStorage.setItem(LS_KEY, JSON.stringify(lists));
}

function updateRecentListName(hash, name) {
    saveRecentList(hash, name);
}

// ── Toast ─────────────────────────────────────────────────────────────────────
let toastTimer = null;

function showToast(message, duration = 3000) {
    toastEl.textContent = message;
    toastEl.classList.add('visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toastEl.classList.remove('visible'), duration);
}

// ── Generic fetch helper ──────────────────────────────────────────────────────
function apiFetch(endpoint, method = 'GET', body = null) {
    const opts = {
        method,
        headers: { 'Content-Type': 'application/json' },
    };
    if (body) opts.body = JSON.stringify(body);
    return fetch(`${API_BASE}/${endpoint}`, opts);
}

// ── Auth Modal ────────────────────────────────────────────────────────────────
function openAuthModal() {
    if (!authModal) return;
    authModal.setAttribute('aria-hidden', 'false');
    authOverlay.style.display = 'block';
    requestAnimationFrame(() => {
        authOverlay.classList.add('visible');
        authModal.classList.add('open');
    });
    // Focus first input
    const firstInput = loginForm.querySelector('input');
    if (firstInput) setTimeout(() => firstInput.focus(), 100);
}

function closeAuthModal() {
    if (!authModal) return;
    authModal.classList.remove('open');
    authOverlay.classList.remove('visible');
    setTimeout(() => {
        authOverlay.style.display = 'none';
        authModal.setAttribute('aria-hidden', 'true');
    }, 250);
    // Clear errors
    if (loginError)    loginError.textContent = '';
    if (registerError) registerError.textContent = '';
}

function switchAuthTab(tab) {
    const tabLogin    = document.getElementById('tab-login');
    const tabRegister = document.getElementById('tab-register');

    if (tab === 'login') {
        tabLogin.classList.add('active');
        tabRegister.classList.remove('active');
        loginForm.style.display    = '';
        registerForm.style.display = 'none';
    } else {
        tabRegister.classList.add('active');
        tabLogin.classList.remove('active');
        registerForm.style.display = '';
        loginForm.style.display    = 'none';
    }
    // Clear errors on tab switch
    if (loginError)    loginError.textContent = '';
    if (registerError) registerError.textContent = '';
}

async function handleLogin(e) {
    e.preventDefault();
    loginError.textContent = '';

    const login    = document.getElementById('login-input').value.trim();
    const password = document.getElementById('login-password').value;

    if (!login || !password) {
        loginError.textContent = 'Please fill in all fields.';
        return;
    }

    try {
        const res  = await apiFetch('login.php', 'POST', { login, password });
        const data = await res.json();

        if (!data.success) {
            loginError.textContent = data.error || 'Login failed.';
            return;
        }

        showToast('Welcome back, ' + data.user.username + '!');
        // Reload to reflect logged-in state in header
        setTimeout(() => location.reload(), AUTH_RELOAD_DELAY);
    } catch (err) {
        loginError.textContent = 'Network error. Please try again.';
    }
}

async function handleRegister(e) {
    e.preventDefault();
    registerError.textContent = '';

    const username = document.getElementById('reg-username').value.trim();
    const email    = document.getElementById('reg-email').value.trim();
    const password = document.getElementById('reg-password').value;

    if (!username || !email || !password) {
        registerError.textContent = 'Please fill in all fields.';
        return;
    }

    try {
        const res  = await apiFetch('register.php', 'POST', { username, email, password });
        const data = await res.json();

        if (!data.success) {
            registerError.textContent = data.error || 'Registration failed.';
            return;
        }

        showToast('Account created! Welcome, ' + data.user.username + '!');
        // Reload to reflect logged-in state in header
        setTimeout(() => location.reload(), AUTH_RELOAD_DELAY);
    } catch (err) {
        registerError.textContent = 'Network error. Please try again.';
    }
}

async function handleLogout() {
    try {
        await apiFetch('logout.php', 'POST');
        showToast('Logged out.');
        setTimeout(() => location.reload(), AUTH_RELOAD_DELAY);
    } catch {
        location.reload();
    }
}

// ── Forgot Password Flow ──────────────────────────────────────────────────────
function showForgotForm() {
    loginForm.style.display    = 'none';
    registerForm.style.display = 'none';
    document.getElementById('forgot-form').style.display = '';
    document.getElementById('reset-form').style.display  = 'none';
    // Hide tabs
    document.querySelector('.modal-tabs').style.display = 'none';
}

function showResetForm(token) {
    loginForm.style.display    = 'none';
    registerForm.style.display = 'none';
    document.getElementById('forgot-form').style.display = 'none';
    document.getElementById('reset-form').style.display  = '';
    document.getElementById('reset-token').value = token;
    document.querySelector('.modal-tabs').style.display = 'none';
}

function backToLogin() {
    document.getElementById('forgot-form').style.display = 'none';
    document.getElementById('reset-form').style.display  = 'none';
    loginForm.style.display = '';
    document.querySelector('.modal-tabs').style.display = '';
    switchAuthTab('login');
}

async function handleForgotPassword(e) {
    e.preventDefault();
    const errorEl   = document.getElementById('forgot-error');
    const successEl = document.getElementById('forgot-success');
    errorEl.textContent   = '';
    successEl.textContent = '';

    const email = document.getElementById('forgot-email').value.trim();
    if (!email) {
        errorEl.textContent = 'Please enter your email.';
        return;
    }

    try {
        const res  = await apiFetch('forgot_password.php', 'POST', { email });
        const data = await res.json();

        if (!data.success) {
            errorEl.textContent = data.error || 'Request failed.';
            return;
        }

        if (data.token) {
            // Show the reset form with the token pre-filled
            successEl.textContent = 'Reset token generated!';
            setTimeout(() => showResetForm(data.token), 600);
        } else {
            successEl.textContent = 'If an account with that email exists, check your reset options.';
        }
    } catch {
        errorEl.textContent = 'Network error. Please try again.';
    }
}

async function handleResetPassword(e) {
    e.preventDefault();
    const errorEl   = document.getElementById('reset-error');
    const successEl = document.getElementById('reset-success');
    errorEl.textContent   = '';
    successEl.textContent = '';

    const token    = document.getElementById('reset-token').value.trim();
    const password = document.getElementById('reset-password').value;

    if (!token || !password) {
        errorEl.textContent = 'Please fill in all fields.';
        return;
    }

    try {
        const res  = await apiFetch('reset_password.php', 'POST', { token, password });
        const data = await res.json();

        if (!data.success) {
            errorEl.textContent = data.error || 'Reset failed.';
            return;
        }

        successEl.textContent = 'Password reset successfully! You can now sign in.';
        setTimeout(() => backToLogin(), 1500);
    } catch {
        errorEl.textContent = 'Network error. Please try again.';
    }
}

// ── Profile Modal ─────────────────────────────────────────────────────────────
function openProfileModal() {
    const modal   = document.getElementById('profile-modal');
    const overlay = document.getElementById('profile-overlay');
    if (!modal) return;
    modal.setAttribute('aria-hidden', 'false');
    overlay.style.display = 'block';
    requestAnimationFrame(() => {
        overlay.classList.add('visible');
        modal.classList.add('open');
    });
}

function closeProfileModal() {
    const modal   = document.getElementById('profile-modal');
    const overlay = document.getElementById('profile-overlay');
    if (!modal) return;
    modal.classList.remove('open');
    overlay.classList.remove('visible');
    setTimeout(() => {
        overlay.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
    }, 250);
    // Clear all messages
    ['email-change-error', 'email-change-success', 'password-change-error',
     'password-change-success', 'delete-account-error'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = '';
    });
}

async function handleChangeEmail(e) {
    e.preventDefault();
    const errorEl   = document.getElementById('email-change-error');
    const successEl = document.getElementById('email-change-success');
    errorEl.textContent   = '';
    successEl.textContent = '';

    const newEmail        = document.getElementById('profile-new-email').value.trim();
    const currentPassword = document.getElementById('profile-email-password').value;

    if (!newEmail || !currentPassword) {
        errorEl.textContent = 'Please fill in all fields.';
        return;
    }

    try {
        const res  = await apiFetch('update_profile.php', 'POST', {
            action: 'email',
            current_password: currentPassword,
            new_email: newEmail,
        });
        const data = await res.json();

        if (!data.success) {
            errorEl.textContent = data.error || 'Update failed.';
            return;
        }

        successEl.textContent = 'Email updated successfully!';
        document.getElementById('profile-email-password').value = '';
        setTimeout(() => location.reload(), 1500);
    } catch {
        errorEl.textContent = 'Network error. Please try again.';
    }
}

async function handleChangePassword(e) {
    e.preventDefault();
    const errorEl   = document.getElementById('password-change-error');
    const successEl = document.getElementById('password-change-success');
    errorEl.textContent   = '';
    successEl.textContent = '';

    const currentPassword = document.getElementById('profile-current-password').value;
    const newPassword     = document.getElementById('profile-new-password').value;

    if (!currentPassword || !newPassword) {
        errorEl.textContent = 'Please fill in all fields.';
        return;
    }

    try {
        const res  = await apiFetch('update_profile.php', 'POST', {
            action: 'password',
            current_password: currentPassword,
            new_password: newPassword,
        });
        const data = await res.json();

        if (!data.success) {
            errorEl.textContent = data.error || 'Update failed.';
            return;
        }

        successEl.textContent = 'Password updated successfully!';
        document.getElementById('profile-current-password').value = '';
        document.getElementById('profile-new-password').value = '';
    } catch {
        errorEl.textContent = 'Network error. Please try again.';
    }
}

async function handleDeleteAccount(e) {
    e.preventDefault();
    const errorEl = document.getElementById('delete-account-error');
    errorEl.textContent = '';

    const currentPassword = document.getElementById('profile-delete-password').value;

    if (!currentPassword) {
        errorEl.textContent = 'Please enter your password to confirm.';
        return;
    }

    if (!confirm('Are you sure you want to delete your account? This cannot be undone.')) {
        return;
    }

    try {
        const res  = await apiFetch('delete_account.php', 'POST', {
            current_password: currentPassword,
        });
        const data = await res.json();

        if (!data.success) {
            errorEl.textContent = data.error || 'Deletion failed.';
            return;
        }

        showToast('Account deleted. Goodbye!');
        setTimeout(() => location.reload(), AUTH_RELOAD_DELAY);
    } catch {
        errorEl.textContent = 'Network error. Please try again.';
    }
}
