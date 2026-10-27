<?php
/**
 * Hisabio - Supplier Khata & Ledger
 * PRD Section 10.2: Chronological ledger, purchases on credit, payments, running payable balance
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$biz = current_business();
$biz_id = (int)$biz['id'];
$supplier_id = (int)($_GET['id'] ?? 0);
$pdo = get_db();

// Fetch Supplier & Account
$stmt = $pdo->prepare("
    SELECT s.*, a.id AS account_id, a.opening_balance, a.opening_balance_direction
    FROM suppliers s
    JOIN accounts a ON a.supplier_id = s.id
    WHERE s.id = ? AND s.business_id = ?
");
$stmt->execute([$supplier_id, $biz_id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    flash_message('error', 'Supplier not found.');
    header('Location: ' . BASE_URL . 'suppliers.php');
    exit;
}

$account_id = (int)$supplier['account_id'];

// Fetch all transactions ASC for accurate running balance
$stmtTx = $pdo->prepare("
    SELECT t.*, u.full_name AS creator_name
    FROM transactions t
    LEFT JOIN users u ON u.id = t.created_by
    WHERE t.account_id = ?
    ORDER BY t.transaction_date ASC, t.id ASC
");
$stmtTx->execute([$account_id]);
$all_txs = $stmtTx->fetchAll();

$running = 0.00;
if ($supplier['opening_balance_direction'] === 'payable') {
    $running += (float)$supplier['opening_balance'];
} elseif ($supplier['opening_balance_direction'] === 'receivable') {
    $running -= (float)$supplier['opening_balance'];
}

$computed_entries = [];
foreach ($all_txs as $tx) {
    $amt = (float)$tx['amount'];
    $is_opening = ($tx['transaction_type'] === 'opening_balance');
    $is_reversed = ($tx['status'] === 'reversed');

    if (!$is_opening && !$is_reversed) {
        if ($tx['transaction_type'] === 'supplier_purchase') {
            $running += $amt; // Purchases increase our payable
        } elseif ($tx['transaction_type'] === 'supplier_payment') {
            $running -= $amt; // Payments decrease our payable
        } elseif ($tx['transaction_type'] === 'adjustment' || $tx['transaction_type'] === 'reversal') {
            $running += $amt;
        }
    }

    $tx['computed_balance'] = $running;
    $computed_entries[] = $tx;
}

$current_balance = $running;
$display_entries = array_reverse($computed_entries);

$page_title = 'Supplier Khata: ' . htmlspecialchars($supplier['name']);
include __DIR__ . '/includes/header.php';
?>

<div class="page-container">
  <div class="page-header">
    <div>
      <h1 class="page-title">
        🏢 <?= htmlspecialchars($supplier['name']) ?>
      </h1>
      <p class="page-subtitle">
        <?= htmlspecialchars($supplier['mobile']) ?> • <?= htmlspecialchars($supplier['supplier_code']) ?>
      </p>
    </div>

    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
      <a href="<?= BASE_URL ?>supplier-profile.php?id=<?= $supplier['id'] ?>" class="btn btn-outline btn-sm">
        👤 Profile
      </a>
      <a href="<?= BASE_URL ?>supplier-statement.php?id=<?= $supplier['id'] ?>" class="btn btn-outline btn-sm">
        🖨️ Statement
      </a>
      <a href="tel:<?= htmlspecialchars($supplier['mobile']) ?>" class="btn btn-outline btn-sm">
        📞 Call Supplier
      </a>
    </div>
  </div>

  <!-- Big Outstanding Payable Banner -->
  <div class="card" style="border-left: 6px solid <?= $current_balance > 0 ? 'var(--payment)' : ($current_balance < 0 ? 'var(--credit)' : 'var(--border)') ?>; margin-bottom: 16px;">
    <div class="flex-between">
      <div>
        <div style="font-size: 13px; font-weight: 500; color: var(--text-muted);">Current Payable Balance</div>
        <div style="font-size: 30px; font-weight: 800; color: <?= $current_balance > 0 ? 'var(--payment)' : ($current_balance < 0 ? 'var(--credit)' : 'var(--text)') ?>;">
          <?= format_currency($current_balance) ?>
        </div>
        <div style="font-size: 13px; color: var(--text-muted); margin-top: 2px;">
          <?= $current_balance > 0 ? 'You owe this supplier' : ($current_balance < 0 ? 'Supplier owes you (Advance)' : 'Account is completely settled') ?>
        </div>
      </div>

      <div style="display: flex; gap: 8px;">
        <a href="<?= BASE_URL ?>supplier-purchase.php?supplier_id=<?= $supplier['id'] ?>" class="btn btn-primary" style="background: var(--credit); border-color: var(--credit); padding: 10px 16px;">
          + Buy on Credit
        </a>
        <a href="<?= BASE_URL ?>supplier-payment.php?supplier_id=<?= $supplier['id'] ?>" class="btn btn-success" style="padding: 10px 16px;">
          + Pay Supplier
        </a>
      </div>
    </div>
  </div>

  <!-- Supplier Transactions Stack -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title"><?= __('supplier_khata') ?> Statement</h2>
      <span style="font-size: 12px; color: var(--text-muted);"><?= count($display_entries) ?> total records</span>
    </div>

    <?php if (empty($display_entries)): ?>
      <div style="text-align: center; padding: 40px 16px; color: var(--text-muted);">
        <p style="font-size: 15px; margin-bottom: 12px;">No transactions recorded for this supplier yet.</p>
        <div style="display: flex; justify-content: center; gap: 10px;">
          <a href="<?= BASE_URL ?>supplier-purchase.php?supplier_id=<?= $supplier['id'] ?>" class="btn btn-primary btn-sm">
            Record Purchase
          </a>
          <a href="<?= BASE_URL ?>supplier-payment.php?supplier_id=<?= $supplier['id'] ?>" class="btn btn-success btn-sm">
            Make Payment
          </a>
        </div>
      </div>
    <?php else: ?>
      <div class="ledger-stack">
        <?php foreach ($display_entries as $tx): ?>
          <?php
            $is_purchase = ($tx['transaction_type'] === 'supplier_purchase');
            $is_payment = ($tx['transaction_type'] === 'supplier_payment');
            $is_opening = ($tx['transaction_type'] === 'opening_balance');
            $is_reversal = ($tx['transaction_type'] === 'reversal');
            $is_void = ($tx['status'] === 'reversed');

            $item_class = $is_void ? 'reversal' : ($is_purchase ? 'credit' : ($is_payment ? 'payment' : ''));
          ?>
          <div class="ledger-item <?= $item_class ?>">
            <div class="item-main">
              <div class="flex-between" style="justify-content: flex-start; gap: 8px;">
                <a href="<?= BASE_URL ?>transaction-details.php?id=<?= $tx['id'] ?>" class="item-title" style="text-decoration: none;">
                  <?= htmlspecialchars($tx['description'] ?: 'Transaction ' . $tx['transaction_reference']) ?>
                </a>

                <?php if ($is_purchase): ?>
                  <span class="badge badge-credit">Credit Purchase</span>
                <?php elseif ($is_payment): ?>
                  <span class="badge badge-payment">Supplier Payment</span>
                <?php elseif ($is_opening): ?>
                  <span class="badge badge-neutral">Opening Balance</span>
                <?php elseif ($is_reversal): ?>
                  <span class="badge badge-danger">Reversal</span>
                <?php endif; ?>

                <?php if ($is_void): ?>
                  <span class="badge badge-danger">REVERSED</span>
                <?php endif; ?>
              </div>

              <div class="item-sub" style="margin-top: 4px;">
                📅 <?= format_date($tx['transaction_date']) ?>
                • Ref: <code><?= htmlspecialchars($tx['transaction_reference']) ?></code>
                <?php if (!empty($tx['payment_method'])): ?>
                  • <?= htmlspecialchars($tx['payment_method']) ?>
                <?php endif; ?>
                <?php if (!empty($tx['reference_number'])): ?>
                  • Invoice #<?= htmlspecialchars($tx['reference_number']) ?>
                <?php endif; ?>
              </div>
            </div>

            <div class="item-amount">
              <div class="amount-val <?= $is_purchase ? 'text-credit' : ($is_payment ? 'text-payment' : '') ?>" style="<?= $is_void ? 'text-decoration: line-through; color: var(--text-muted);' : '' ?>">
                <?= $is_purchase ? '+' : ($is_payment ? '-' : '') ?><?= format_currency($tx['amount']) ?>
              </div>
              <div class="balance-val">
                Payable: <strong><?= format_currency($tx['computed_balance']) ?></strong>
              </div>
            </div>

            <div style="margin-left: 10px;">
              <a href="<?= BASE_URL ?>transaction-details.php?id=<?= $tx['id'] ?>" class="btn btn-outline btn-sm" title="Details / Receipt" style="padding: 4px 8px; font-size: 11px;">
                👁️
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
