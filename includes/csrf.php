<?php
/**
 * Hisabio - CSRF Protection Helper
 */

require_once __DIR__ . '/config.php';

function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_token(): string {
    return generate_csrf_token();
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function check_csrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verify_csrf_token($token)) {
        http_response_code(403);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'))) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid or expired security token. Please refresh the page and try again.']);
            exit;
        }
        $_SESSION['flash_error'] = 'Invalid or expired security token. Please try again.';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL . 'dashboard.php'));
        exit;
    }
}
