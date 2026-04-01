<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper: Generate a password hash (run this in a PHP shell or script to get a hash for your password)
// echo password_hash('your_new_password', PASSWORD_DEFAULT);

function admin_credentials(): array
{
    $user = getenv('ASIAN3DFRAMES_ADMIN_USER');
    $hash = getenv('ASIAN3DFRAMES_ADMIN_PASS_HASH');
    // Fallbacks
    $defaultUser = 'admin';
    // Default password is 'admin@123' hashed (replace with a real hash for production)
    $defaultHash = '$2y$10$wQw6QwQwQwQwQwQwQwQwQeQwQwQwQwQwQwQwQwQwQwQwQwQw';
    return [
        'username' => is_string($user) && $user !== '' ? $user : $defaultUser,
        'password_hash' => is_string($hash) && $hash !== '' ? $hash : $defaultHash,
    ];
}

function admin_is_authenticated(): bool
{
    return isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
}

function admin_login(string $username, string $password): bool
{
    global $conn; // Use the global database connection from db.php
    $creds = admin_credentials();

    $stmt = $conn->prepare('SELECT id, password_hash FROM admin_users WHERE username = ? LIMIT 1');
    if (!$stmt) {
        // Optional: log error $conn->error
        return false;
    }
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();
        return false; // User not found
    }

    $stmt->bind_result($admin_id, $hash);
    $stmt->fetch();
    $stmt->close();

    $isValid = password_verify($password, $hash);

    // Backward compatibility: accept legacy plain-text stored values once,
    // then migrate them to a secure hash.
    if (!$isValid && hash_equals((string)$hash, $password)) {
        $isValid = true;
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $updateStmt = $conn->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
        if ($updateStmt) {
            $updateStmt->bind_param('si', $newHash, $admin_id);
            $updateStmt->execute();
            $updateStmt->close();
        }
    }

    // Migration helper for the historic placeholder hash used in older builds.
    // If the row has that placeholder and the user logs in with default admin creds,
    // accept once and immediately replace with a real hash.
    if (
        !$isValid
        && hash_equals((string)$hash, (string)$creds['password_hash'])
        && hash_equals($username, (string)$creds['username'])
        && hash_equals($password, 'admin@123')
    ) {
        $isValid = true;
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $updateStmt = $conn->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
        if ($updateStmt) {
            $updateStmt->bind_param('si', $newHash, $admin_id);
            $updateStmt->execute();
            $updateStmt->close();
        }
    }

    if (!$isValid) {
        return false; // Password incorrect
    }

    // Keep hashes up to date with current PHP defaults.
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        $rehash = password_hash($password, PASSWORD_DEFAULT);
        $rehashStmt = $conn->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
        if ($rehashStmt) {
            $rehashStmt->bind_param('si', $rehash, $admin_id);
            $rehashStmt->execute();
            $rehashStmt->close();
        }
    }

    // Regenerate session ID for security
    session_regenerate_id(true);
    $_SESSION['admin_authenticated'] = true;
    $_SESSION['admin_username'] = $username;
    $_SESSION['admin_id'] = $admin_id;
    $_SESSION['admin_login_at'] = time();

    return true;
}

function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
}

function admin_require_auth_json(): void
{
    if (admin_is_authenticated()) {
        return;
    }

    http_response_code(401);
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
    echo json_encode([
        'success' => false,
        'authenticated' => false,
        'message' => 'Authentication required.',
    ]);
    exit;
}
 