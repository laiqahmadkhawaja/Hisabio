<?php
/**
 * Hisabio - Customer Khata & Ledger
 * PRD Section 9: Chronological entries, running balance, mobile stacked cards
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$biz = current_business();
$biz_id = (int)$biz['id'];
$customer_id = (int)($_GET['id'] ?? 0);
$pdo = get_db();

// Fetch Customer & Account
$stmt = $pdo->prepare("
    SELECT c.*, a.id AS account_id, a.opening_balance, a.opening_balance_direction
    FROM customers c
    JOIN accounts a ON a.customer_id = c.id
    WHERE c.id = ? AND c.business_id = ?
");
$stmt->execute([$customer_id, $biz_id]);
$customer = $stmt->fetch();

if (!$customer) {
    flash_message('error', 'Customer not found.');
    header('Location: ' . BASE_URL . 'customers.php');
    exit;
}

$account_id = (int)$customer['account_id'];

// Fetch all transactions in chronological order (ASC) to compute running balance accurately
$stmtTx = $pdo->prepare("
    SELECT t.*, u.full_name AS creator_name
    FROM transactions t
    LEFT JOIN users u ON u.id = t.created_by
    WHERE t.account_id = ?
    ORDER BY t.transaction_date ASC, t.id ASC
");
$stmtTx->execute([$account_id]);
$all_txs = $stmtTx->fetchAll();

// Calculate running balance per entry
$running = 0.00;
if ($customer['opening_balance_direction'] === 'receivable') {
    $running += (float)$customer['opening_balance'];
} elseif ($customer['opening_balance_direction'] === 'payable') {
    $running -= (float)$customer['opening_balance'];
}

$computed_entries = [];
$total_credit = 0.00;
$total_payment = 0.00;

foreach ($all_txs as $tx) {
    $amt = (float)$tx['amount'];
    $is_opening = ($tx['transaction_type'] === 'opening_balance');
    $is_reversed = ($tx['status'] === 'reversed');

    if (!$is_opening && !$is_reversed) {
        if ($tx['transaction_type'] === 'credit_sale') {
            $running += $amt;
            $total_credit += $amt;
        } elseif ($tx['transaction_type'] === 'payment_received') {
            $running -= $amt;
            $total_payment += $amt;
        } elseif ($tx['transaction_type'] === 'adjustment' || $tx['transaction_type'] === 'reversal') {
            $running += $amt;
        }
    }

    $tx['computed_balance'] = $running;
    $computed_entries[] = $tx;
}

$current_balance = $running;

// Reverse order for display so newest appears on top, while preserving exact historical running balance
$display_entries = array_reverse($computed_entries);

$whatsapp_msg = sprintf(
    "Assalam-o-Alaikum %s sahib! This is your current khata update from %s. Your outstanding balance is %s. Thank you! JazakAllah.",
    $customer['name'],
    $biz['business_name'],
    format_currency($current_balance)
);
$whatsapp_link = build_whatsapp_link($customer['mobile'], $whatsapp_msg);

$page_title = 'Khata: ' . htmlspecialchars($customer['name']);
include __DIR__ . '/includes/header.php';
?>

<div class="page-container">
  <!-- Top Navigation & Profile Link -->
  <div class="page-header">
    <div>
      <h1 class="page-title">
        📖 <?= htmlspecialchars($customer['name']) ?>
      </h1>
      <p class="page-subtitle">
        <?= htmlspecialchars($customer['mobile']) ?> • <?= htmlspecialchars($customer['customer_code']) ?>
      </p>
    </div>

    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
      <a href="<?= BASE_URL ?>customer-profile.php?id=<?= $customer['id'] ?>" class="btn btn-outline btn-sm">
        👤 Profile
      </a>
      <a href="<?= BASE_URL ?>customer-statement.php?id=<?= $customer['id'] ?>" class="btn btn-outline btn-sm">
        🖨️ Statement
      </a>
      <a href="<?= $whatsapp_link ?>" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp btn-sm">
        💬 Send Reminder
      </a>
    </div>
  </div>

  <!-- Big Outstanding Balance Banner -->
  <div class="card" style="border-left: 6px solid <?= $current_balance > 0 ? 'var(--credit)' : ($current_balance < 0 ? 'var(--payment)' : 'var(--border)') ?>; margin-bottom: 16px;">
    <div class="flex-between">
      <div>
        <div style="font-size: 13px; font-weight: 500; color: var(--text-muted);"><?= __('running_balance') ?></div>
        <div style="font-size: 30px; font-weight: 800; color: <?= $current_balance > 0 ? 'var(--credit)' : ($current_balance < 0 ? 'var(--payment)' : 'var(--text)') ?>;">
          <?= format_currency($current_balance) ?>
        </div>
        <div style="font-size: 13px; color: var(--text-muted); margin-top: 2px;">
          <?= $current_balance > 0 ? 'Customer owes you' : ($current_balance < 0 ? 'Advance / Excess credit' : 'Account Settled') ?>
        </div>
      </div>

      <!-- Quick Action Buttons for Fast Entry (PRD Section 6.1) -->
      <div style="display: flex; gap: 8px;">
        <a href="<?= BASE_URL ?>give-credit.php?customer_id=<?= $customer['id'] ?>" class="btn btn-primary" style="background: var(--credit); border-color: var(--credit); padding: 10px 16px;">
          + Udhaar Diya
        </a>
        <a href="<?= BASE_URL ?>receive-payment.php?customer_id=<?= $customer['id'] ?>" class="btn btn-success" style="padding: 10px 16px;">
          + Raqam Wasool
        </a>
      </div>
    </div>
  </div>

  <!-- Chronological Ledger Entries (Mobile Stacked Cards, Desktop Table Friendly) -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title"><?= __('customer_khata') ?> Statement</h2>
      <span style="font-size: 12px; color: var(--text-muted);"><?= count($display_entries) ?> total records</span>
    </div>

    <?php if (empty($display_entries)): ?>
      <div style="text-align: center; padding: 40px 16px; color: var(--text-muted);">
        <p style="font-size: 15px; margin-bottom: 12px;">No transactions yet for this customer.</p>
        <div style="display: flex; justify-content: center; gap: 10px;">
          <a href="<?= BASE_URL ?>give-credit.php?customer_id=<?= $customer['id'] ?>" class="btn btn-primary btn-sm">
            Give First Credit
          </a>
          <a href="<?= BASE_URL ?>receive-payment.php?customer_id=<?= $customer['id'] ?>" class="btn btn-success btn-sm">
            Record Payment
          </a>
        </div>
      </div>
    <?php else: ?>
      <div class="ledger-stack">
        <?php foreach ($display_entries as $tx): ?>
          <?php
            $is_credit = ($tx['transaction_type'] === 'credit_sale');
            $is_payment = ($tx['transaction_type'] === 'payment_received');
            $is_opening = ($tx['transaction_type'] === 'opening_balance');
            $is_reversal = ($tx['transaction_type'] === 'reversal');
            $is_void = ($tx['status'] === 'reversed');

            $item_class = $is_void ? 'reversal' : ($is_credit ? 'credit' : ($is_payment ? 'payment' : ''));
          ?>
          <div class="ledger-item <?= $item_class ?>">
            <div class="item-main">
              <div class="flex-between" style="justify-content: flex-start; gap: 8px;">
                <a href="<?= BASE_URL ?>transaction-details.php?id=<?= $tx['id'] ?>" class="item-title" style="text-decoration: none;">
                  <?= htmlspecialchars($tx['description'] ?: 'Transaction ' . $tx['transaction_reference']) ?>
                </a>

                <?php if ($is_credit): ?>
                  <span class="badge badge-credit">Udhaar Diya</span>
                <?php elseif ($is_payment): ?>
                  <span class="badge badge-payment">Raqam Wasool</span>
                <?php elseif ($is_opening): ?>
                  <span class="badge badge-neutral">Opening Balance</span>
                <?php elseif ($is_reversal): ?>
                  <span class="badge badge-danger">Reversal</span>
                <?php endif; ?>

                <?php if ($is_void): ?>
                  <span class="badge badge-danger">REVERSED / CANCELLED</span>
                <?php endif; ?>
              </div>

              <div class="item-sub" style="margin-top: 4px;">
                📅 <?= format_date($tx['transaction_date']) ?>
                • Ref: <code><?= htmlspecialchars($tx['transaction_reference']) ?></code>
                <?php if (!empty($tx['payment_method'])): ?>
                  • <?= htmlspecialchars($tx['payment_method']) ?>
                <?php endif; ?>
                <?php if (!empty($tx['due_date'])): ?>
                  • <span style="color: var(--danger);">Due: <?= format_date($tx['due_date']) ?></span>
                <?php endif; ?>
              </div>
            </div>

            <div class="item-amount">
              <div class="amount-val <?= $is_credit ? 'text-credit' : ($is_payment ? 'text-payment' : '') ?>" style="<?= $is_void ? 'text-decoration: line-through; color: var(--text-muted);' : '' ?>">
                <?= $is_credit ? '+' : ($is_payment ? '-' : '') ?><?= format_currency($tx['amount']) ?>
              </div>
              <div class="balance-val">
                Bal: <strong><?= format_currency($tx['computed_balance']) ?></strong>
              </div>
            </div>

            <div style="margin-left: 10px;">
              <a href="<?= BASE_URL ?>transaction-details.php?id=<?= $tx['id'] ?>" class="btn btn-outline btn-sm" title="Details / Receipt" aria-label="View transaction details and receipt" style="padding: 4px 8px; font-size: 12px; font-weight: 600;">
                Details
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
