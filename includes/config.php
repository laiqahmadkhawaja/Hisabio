<?php
/**
 * Hisabio - Smart Digital Khata & Business Ledger System
 * Configuration & Environment Setup
 */

// Strict error reporting in development, safe in production
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Timezone
date_default_timezone_set('Asia/Karachi');

// Database Configuration
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'hisabio');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Details
define('APP_NAME', 'Hisabio');
define('APP_TAGLINE', 'Har Hisab Saaf, Har Khata Aasaan');
define('APP_VERSION', '1.0.0');

// Base URL calculation
$script_name = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$base_url = rtrim($script_name, '/') . '/';
define('BASE_URL', $base_url === '//' ? '/' : $base_url);

// Session Security Configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}
