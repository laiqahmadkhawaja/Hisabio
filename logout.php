<?php
/**
 * Hisabio - Secure Logout Handler
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

logout_user();

session_start();
$_SESSION['flashes'] = [['type' => 'info', 'message' => 'You have been logged out securely.']];

header('Location: ' . BASE_URL . 'login.php');
exit;
