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
    $creds = admin_credentials();
    if (!hash_equals($creds['username'], $username)) {
        return false;
    }
    if (!password_verify($password, $creds['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['admin_authenticated'] = true;
    $_SESSION['admin_username'] = $username;
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
 