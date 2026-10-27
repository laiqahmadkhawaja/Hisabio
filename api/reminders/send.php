<?php
/**
 * Hisabio API - WhatsApp Reminder Dispatch Logger
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}

check_csrf();

$biz = current_business();
$biz_id = (int)$biz['id'];
$customer_id = (int)($_POST['customer_id'] ?? 0);
$pdo = get_db();

$stmtCust = $pdo->prepare("SELECT name, mobile FROM customers WHERE id = ? AND business_id = ?");
$stmtCust->execute([$customer_id, $biz_id]);
$cust = $stmtCust->fetch();

if (!$cust) {
    json_response(['success' => false, 'message' => 'Customer not found.'], 404);
}

$stmtAcc = $pdo->prepare("SELECT id FROM accounts WHERE customer_id = ? AND business_id = ?");
$stmtAcc->execute([$customer_id, $biz_id]);
$account_id = (int)$stmtAcc->fetchColumn();

$balance = calculate_customer_balance($account_id, $pdo);

$msg = sprintf(
    "Assalam-o-Alaikum %s sahib! Friendly reminder from %s. Your outstanding balance is %s. Thank you! JazakAllah khair.",
    $cust['name'],
    $biz['business_name'],
    format_currency($balance)
);

$stmtLog = $pdo->prepare("
    INSERT INTO reminders 
    (business_id, customer_id, reminder_type, balance_snapshot, message, initiated_by, initiated_at, delivery_status)
    VALUES (?, ?, 'whatsapp', ?, ?, ?, NOW(), 'opened_in_whatsapp')
");
$stmtLog->execute([$biz_id, $customer_id, $balance, $msg, $_SESSION['user_id']]);

$wa_url = build_whatsapp_link($cust['mobile'], $msg);

json_response([
    'success' => true,
    'whatsapp_url' => $wa_url,
    'message' => 'Reminder registered.'
]);
