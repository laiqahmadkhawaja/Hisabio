<?php
/**
 * Hisabio - Helper Functions & Core Financial Calculations
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lang.php';

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

function format_currency($amount, bool $show_symbol = true, string $currency = 'PKR'): string {
    $val = (float)$amount;
    $formatted = number_format(abs($val), 2, '.', ',');
    $prefix = $show_symbol ? $currency . ' ' : '';
    if ($val < 0) {
        return '-' . $prefix . $formatted;
    }
    return $prefix . $formatted;
}

function format_date(?string $date_str, string $format = 'd M Y'): string {
    if (empty($date_str)) {
        return '—';
    }
    $timestamp = strtotime($date_str);
    return $timestamp ? date($format, $timestamp) : '—';
}

function format_datetime(?string $date_str, string $format = 'd M Y, h:i A'): string {
    if (empty($date_str)) {
        return '—';
    }
    $timestamp = strtotime($date_str);
    return $timestamp ? date($format, $timestamp) : '—';
}

function generate_reference(string $prefix = 'TXN'): string {
    return sprintf('%s-%s-%04d', $prefix, date('ymd'), random_int(1000, 9999));
}

function flash_message(string $type, string $message): void {
    if (!isset($_SESSION['flashes'])) {
        $_SESSION['flashes'] = [];
    }
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

function get_flash_messages(): array {
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $flashes;
}

function json_response(array $data, int $status_code = 200): void {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Authoritative Customer Balance Calculation from Ledger
 * Positive balance = Customer owes business (Receivable)
 * Negative balance = Business owes customer (Advance / Excess)
 */
function calculate_customer_balance(int $account_id, ?PDO $pdo = null): float {
    if (!$pdo) {
        $pdo = get_db();
    }

    // Fetch opening balance and direction
    $stmt = $pdo->prepare("SELECT opening_balance, opening_balance_direction FROM accounts WHERE id = ?");
    $stmt->execute([$account_id]);
    $acc = $stmt->fetch();
    if (!$acc) {
        return 0.00;
    }

    $balance = 0.00;
    if ($acc['opening_balance_direction'] === 'receivable') {
        $balance += (float)$acc['opening_balance'];
    } elseif ($acc['opening_balance_direction'] === 'payable') {
        $balance -= (float)$acc['opening_balance'];
    }

    // Sum transactions:
    // credit_sale => +
    // payment_received => -
    // adjustment => depends on direction or sign
    // reversal => reverses the referenced transaction or has opposing sign
    $stmt = $pdo->prepare("
        SELECT transaction_type, amount, status 
        FROM transactions 
        WHERE account_id = ? AND status = 'completed' AND transaction_type != 'opening_balance'
    ");
    $stmt->execute([$account_id]);
    $txs = $stmt->fetchAll();

    foreach ($txs as $tx) {
        $amt = (float)$tx['amount'];
        if ($tx['transaction_type'] === 'credit_sale') {
            $balance += $amt;
        } elseif ($tx['transaction_type'] === 'payment_received') {
            $balance -= $amt;
        } elseif ($tx['transaction_type'] === 'adjustment') {
            $balance += $amt; // adjustments can be signed
        }
        // 'reversal' transactions are audit counter-markers; the reversed transaction's status is already 'reversed'
    }

    return round($balance, 2);
}

/**
 * Authoritative Supplier Balance Calculation from Ledger
 * Positive balance = Business owes supplier (Payable)
 * Negative balance = Supplier owes business (Advance / Debit)
 */
function calculate_supplier_balance(int $account_id, ?PDO $pdo = null): float {
    if (!$pdo) {
        $pdo = get_db();
    }

    $stmt = $pdo->prepare("SELECT opening_balance, opening_balance_direction FROM accounts WHERE id = ?");
    $stmt->execute([$account_id]);
    $acc = $stmt->fetch();
    if (!$acc) {
        return 0.00;
    }

    $balance = 0.00;
    if ($acc['opening_balance_direction'] === 'payable') {
        $balance += (float)$acc['opening_balance'];
    } elseif ($acc['opening_balance_direction'] === 'receivable') {
        $balance -= (float)$acc['opening_balance'];
    }

    $stmt = $pdo->prepare("
        SELECT transaction_type, amount, status 
        FROM transactions 
        WHERE account_id = ? AND status = 'completed' AND transaction_type != 'opening_balance'
    ");
    $stmt->execute([$account_id]);
    $txs = $stmt->fetchAll();

    foreach ($txs as $tx) {
        $amt = (float)$tx['amount'];
        if ($tx['transaction_type'] === 'supplier_purchase') {
            $balance += $amt; // We bought goods on credit => payable increases
        } elseif ($tx['transaction_type'] === 'supplier_payment') {
            $balance -= $amt; // We paid supplier => payable decreases
        } elseif ($tx['transaction_type'] === 'adjustment') {
            $balance += $amt;
        }
    }

    return round($balance, 2);
}

/**
 * Creates auditable transaction log entry
 */
function create_audit_log(int $business_id, int $tx_id, string $action, ?array $before, ?array $after, ?string $reason, ?int $user_id): void {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        INSERT INTO transaction_audit_logs 
        (business_id, transaction_id, action, before_data, after_data, reason, performed_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $business_id,
        $tx_id,
        $action,
        $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
        $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
        $reason,
        $user_id
    ]);
}

/**
 * Formats Pakistani mobile number for WhatsApp link
 * Handles '0300...', '+92300...', '92300...' formats
 */
function normalize_pakistan_mobile(string $phone): string {
    $cleaned = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($cleaned, '92') && strlen($cleaned) >= 12) {
        return $cleaned;
    }
    if (str_starts_with($cleaned, '03') && strlen($cleaned) === 11) {
        return '92' . substr($cleaned, 1);
    }
    if (str_starts_with($cleaned, '3') && strlen($cleaned) === 10) {
        return '92' . $cleaned;
    }
    return $cleaned;
}

function build_whatsapp_link(string $phone, string $message): string {
    $normalized = normalize_pakistan_mobile($phone);
    return 'https://wa.me/' . $normalized . '?text=' . urlencode($message);
}

/**
 * Dashboard KPIs & Financial Summaries
 */
function get_business_stats(int $business_id, string $filter = 'all', ?string $custom_start = null, ?string $custom_end = null): array {
    $pdo = get_db();

    // 1. Total Customer Receivables (sum of all positive customer balances)
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE business_id = ? AND account_type = 'customer'");
    $stmt->execute([$business_id]);
    $customer_accounts = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $total_receivables = 0.00;
    foreach ($customer_accounts as $acc_id) {
        $bal = calculate_customer_balance((int)$acc_id, $pdo);
        if ($bal > 0) {
            $total_receivables += $bal;
        }
    }

    // 2. Total Supplier Payables (sum of all positive supplier balances)
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE business_id = ? AND account_type = 'supplier'");
    $stmt->execute([$business_id]);
    $supplier_accounts = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $total_payables = 0.00;
    foreach ($supplier_accounts as $acc_id) {
        $bal = calculate_supplier_balance((int)$acc_id, $pdo);
        if ($bal > 0) {
            $total_payables += $bal;
        }
    }

    // 3. Active Counts
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE business_id = ? AND status = 'active'");
    $stmt->execute([$business_id]);
    $active_customers = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM suppliers WHERE business_id = ? AND status = 'active'");
    $stmt->execute([$business_id]);
    $active_suppliers = (int)$stmt->fetchColumn();

    // 4. Today's figures
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN transaction_type = 'credit_sale' THEN amount ELSE 0 END) AS today_credit,
            SUM(CASE WHEN transaction_type = 'payment_received' THEN amount ELSE 0 END) AS today_received,
            SUM(CASE WHEN transaction_type = 'supplier_payment' THEN amount ELSE 0 END) AS today_paid
        FROM transactions 
        WHERE business_id = ? AND transaction_date = ? AND status = 'completed'
    ");
    $stmt->execute([$business_id, $today]);
    $today_metrics = $stmt->fetch() ?: ['today_credit' => 0, 'today_received' => 0, 'today_paid' => 0];

    // 5. Overdue receivables
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) 
        FROM transactions 
        WHERE business_id = ? 
          AND transaction_type = 'credit_sale' 
          AND due_date IS NOT NULL 
          AND due_date < CURRENT_DATE 
          AND status = 'completed'
    ");
    $stmt->execute([$business_id]);
    $overdue_amount = (float)$stmt->fetchColumn();

    return [
        'total_receivables' => round($total_receivables, 2),
        'total_payables' => round($total_payables, 2),
        'active_customers' => $active_customers,
        'active_suppliers' => $active_suppliers,
        'today_credit' => round((float)($today_metrics['today_credit'] ?? 0), 2),
        'today_received' => round((float)($today_metrics['today_received'] ?? 0), 2),
        'today_paid' => round((float)($today_metrics['today_paid'] ?? 0), 2),
        'overdue_amount' => round($overdue_amount, 2)
    ];
}
