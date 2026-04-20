<?php
/**
 * includes/auth.php
 *
 * Session-based authentication helpers.
 * Call start_auth_session() at the top of any script that needs auth awareness.
 */

require_once __DIR__ . '/db.php';

/**
 * Start (or resume) the PHP session used for authentication.
 * Safe to call multiple times.
 */
function start_auth_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Return the currently logged-in user row, or null if not logged in.
 *
 * @return array|null  User row (id, username, email, created_at) or null.
 */
function get_current_user_row(): ?array
{
    start_auth_session();

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $pdo  = get_db();
    $stmt = $pdo->prepare('SELECT id, username, email, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        // Session references a user that no longer exists – clean up.
        unset($_SESSION['user_id']);
        return null;
    }

    return $user;
}

/**
 * Is the current visitor logged in?
 */
function is_logged_in(): bool
{
    return get_current_user_row() !== null;
}

/**
 * Register a new user. Returns the new user row on success.
 *
 * @param string $username
 * @param string $email
 * @param string $password  Plain-text password (will be hashed).
 * @return array{success: bool, error?: string, user?: array}
 */
function register_user(string $username, string $email, string $password): array
{
    $username = trim($username);
    $email    = trim($email);

    // ── Validation ────────────────────────────────────────────────────────────
    if ($username === '' || strlen($username) < 3 || strlen($username) > 50) {
        return ['success' => false, 'error' => 'Username must be 3–50 characters.'];
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return ['success' => false, 'error' => 'Username may only contain letters, numbers, and underscores.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid email address.'];
    }

    if (strlen($password) < 6) {
        return ['success' => false, 'error' => 'Password must be at least 6 characters.'];
    }

    // ── Check uniqueness ──────────────────────────────────────────────────────
    $pdo = get_db();

    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Username is already taken.'];
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Email is already registered.'];
    }

    // ── Insert ────────────────────────────────────────────────────────────────
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
    $stmt->execute([$username, $email, $hash]);
    $userId = (int) $pdo->lastInsertId();

    // Auto-login after registration.
    start_auth_session();
    $_SESSION['user_id'] = $userId;

    return [
        'success' => true,
        'user'    => [
            'id'       => $userId,
            'username' => $username,
            'email'    => $email,
        ],
    ];
}

/**
 * Log in with email/username + password. Returns user row on success.
 *
 * @param string $login     Email or username.
 * @param string $password  Plain-text password.
 * @return array{success: bool, error?: string, user?: array}
 */
function login_user(string $login, string $password): array
{
    $login = trim($login);

    if ($login === '' || $password === '') {
        return ['success' => false, 'error' => 'Email/username and password are required.'];
    }

    $pdo = get_db();

    // Allow login by email OR username.
    $stmt = $pdo->prepare(
        'SELECT id, username, email, password FROM users WHERE email = ? OR username = ? LIMIT 1'
    );
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return ['success' => false, 'error' => 'Invalid credentials.'];
    }

    start_auth_session();
    $_SESSION['user_id'] = (int) $user['id'];

    return [
        'success' => true,
        'user'    => [
            'id'       => (int) $user['id'],
            'username' => $user['username'],
            'email'    => $user['email'],
        ],
    ];
}

/**
 * Log out the current user by destroying the session.
 */
function logout_user(): void
{
    start_auth_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']
        );
    }

    session_destroy();
}
