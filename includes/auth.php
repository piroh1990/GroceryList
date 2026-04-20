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

/**
 * Create a password-reset token for a user identified by email.
 * Token is valid for 1 hour.
 *
 * @param string $email
 * @return array{success: bool, error?: string, token?: string}
 */
function create_password_reset_token(string $email): array
{
    $email = trim($email);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid email address.'];
    }

    $pdo  = get_db();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Don't reveal whether the email exists.
        return ['success' => true, 'token' => null];
    }

    $token     = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

    $stmt = $pdo->prepare(
        'INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)'
    );
    $stmt->execute([$user['id'], $token, $expiresAt]);

    return ['success' => true, 'token' => $token];
}

/**
 * Reset password using a valid token.
 *
 * @param string $token
 * @param string $newPassword
 * @return array{success: bool, error?: string}
 */
function reset_password_with_token(string $token, string $newPassword): array
{
    $token = trim($token);

    if ($token === '') {
        return ['success' => false, 'error' => 'Token is required.'];
    }

    if (strlen($newPassword) < 6) {
        return ['success' => false, 'error' => 'Password must be at least 6 characters.'];
    }

    $pdo  = get_db();
    $stmt = $pdo->prepare(
        'SELECT id, user_id FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1'
    );
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if (!$reset) {
        return ['success' => false, 'error' => 'Invalid or expired reset token.'];
    }

    // Update password
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->execute([$hash, $reset['user_id']]);

    // Mark token as used
    $stmt = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = ?');
    $stmt->execute([$reset['id']]);

    return ['success' => true];
}

/**
 * Update the email of the currently logged-in user.
 *
 * @param int    $userId
 * @param string $newEmail
 * @param string $currentPassword  Required for verification.
 * @return array{success: bool, error?: string}
 */
function update_user_email(int $userId, string $newEmail, string $currentPassword): array
{
    $newEmail = trim($newEmail);

    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid email address.'];
    }

    $pdo  = get_db();

    // Verify current password
    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($currentPassword, $user['password'])) {
        return ['success' => false, 'error' => 'Current password is incorrect.'];
    }

    // Check if email is already taken by someone else
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
    $stmt->execute([$newEmail, $userId]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Email is already in use.'];
    }

    $stmt = $pdo->prepare('UPDATE users SET email = ? WHERE id = ?');
    $stmt->execute([$newEmail, $userId]);

    return ['success' => true];
}

/**
 * Update the password of the currently logged-in user.
 *
 * @param int    $userId
 * @param string $currentPassword
 * @param string $newPassword
 * @return array{success: bool, error?: string}
 */
function update_user_password(int $userId, string $currentPassword, string $newPassword): array
{
    if (strlen($newPassword) < 6) {
        return ['success' => false, 'error' => 'New password must be at least 6 characters.'];
    }

    $pdo  = get_db();

    // Verify current password
    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($currentPassword, $user['password'])) {
        return ['success' => false, 'error' => 'Current password is incorrect.'];
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->execute([$hash, $userId]);

    return ['success' => true];
}

/**
 * Delete the currently logged-in user's account.
 *
 * @param int    $userId
 * @param string $currentPassword  Required for verification.
 * @return array{success: bool, error?: string}
 */
function delete_user_account(int $userId, string $currentPassword): array
{
    $pdo  = get_db();

    // Verify current password
    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($currentPassword, $user['password'])) {
        return ['success' => false, 'error' => 'Current password is incorrect.'];
    }

    // Delete user (cascade will handle password_resets; grocery_lists owner_id set NULL)
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$userId]);

    // Destroy session
    logout_user();

    return ['success' => true];
}
