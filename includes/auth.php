<?php
/**
 * Hisabio - Authentication, Session Security & Permissions
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['business_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        $return_url = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: ' . BASE_URL . 'login.php' . ($return_url ? '?redirect=' . $return_url : ''));
        exit;
    }
}

function current_user(): ?array {
    static $user = null;
    if ($user !== null) {
        return $user;
    }

    if (!is_logged_in()) {
        return null;
    }

    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT id, full_name, email, mobile, status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    return $user;
}

function current_business(): ?array {
    static $business = null;
    if ($business !== null) {
        return $business;
    }

    if (!is_logged_in()) {
        return null;
    }

    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT b.*, r.role_name, r.permissions 
        FROM businesses b
        JOIN business_users bu ON bu.business_id = b.id
        JOIN roles r ON r.id = bu.role_id
        WHERE b.id = ? AND bu.user_id = ? AND b.status = 'active'
    ");
    $stmt->execute([$_SESSION['business_id'], $_SESSION['user_id']]);
    $business = $stmt->fetch() ?: null;
    return $business;
}

function has_permission(string $permission): bool {
    $business = current_business();
    if (!$business) {
        return false;
    }

    $permissions = json_decode($business['permissions'] ?? '{}', true);
    if (!empty($permissions['all'])) {
        return true;
    }

    return !empty($permissions[$permission]);
}

function login_user(int $user_id, int $business_id): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user_id;
    $_SESSION['business_id'] = $business_id;
    $_SESSION['last_login_time'] = time();
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}

function record_login_attempt(string $identifier, string $outcome): void {
    try {
        $pdo = get_db();
        $hash = hash('sha256', strtolower(trim($identifier)));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO login_attempts (identifier_hash, outcome, ip_address, attempted_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$hash, $outcome, $ip]);
    } catch (Exception $e) {
        error_log("Failed to record login attempt: " . $e->getMessage());
    }
}

function is_rate_limited(string $identifier): bool {
    try {
        $pdo = get_db();
        $hash = hash('sha256', strtolower(trim($identifier)));
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_attempts 
            WHERE identifier_hash = ? AND outcome = 'failure' AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->execute([$hash]);
        $count = (int)$stmt->fetchColumn();
        return $count >= 5; // Lock after 5 failed attempts in 15 mins
    } catch (Exception $e) {
        return false;
    }
}
