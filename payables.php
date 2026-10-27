<?php
/**
 * Hisabio - Outstanding Supplier Payables (You Will Give)
 * PRD Section 7.4 & 26: List of all suppliers you owe money to
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$biz = current_business();
$biz_id = (int)$biz['id'];
$pdo = get_db();

$stmt = $pdo->prepare("
    SELECT s.id, s.name, s.mobile, s.supplier_code, s.address, a.id AS account_id
    FROM suppliers s
    JOIN accounts a ON a.supplier_id = s.id
    WHERE s.business_id = ? AND s.status = 'active'
");
$stmt->execute([$biz_id]);
$all_suppliers = $stmt->fetchAll();

$payables = [];
$total_amount = 0.00;

foreach ($all_suppliers as $s) {
    $bal = calculate_supplier_balance((int)$s['account_id'], $pdo);
    if ($bal > 0) {
        $s['balance'] = $bal;
        $total_amount += $bal;
        $payables[] = $s;
    }
}

usort($payables, fn($a, $b) => $b['balance'] <=> $a['balance']);

$page_title = 'Outstanding Payables';
include __DIR__ . '/includes/header.php';
?>

<div class="page-container">
  <div class="page-header">
    <div>
      <h1 class="page-title text-payment">🏢 Outstanding Supplier Payables</h1>
      <p class="page-subtitle">
        You owe <?= count($payables) ?> suppliers a total of <strong><?= format_currency($total_amount) ?></strong>
      </p>
    </div>

    <div style="display: flex; gap: 8px;">
      <a href="<?= BASE_URL ?>reports.php" class="btn btn-outline btn-sm">
        📊 Reports
      </a>
      <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-outline btn-sm">
        ← <?= __('dashboard') ?>
      </a>
    </div>
  </div>

  <?php if (empty($payables)): ?>
    <div class="card" style="text-align: center; padding: 40px 16px;">
      <h3 style="font-size: 18px; color: var(--payment); margin-bottom: 8px;">🎉 Zero Supplier Debt!</h3>
      <p style="font-size: 14px; color: var(--text-muted);">All your supplier invoices are fully cleared.</p>
    </div>
  <?php else: ?>
    <div class="ledger-stack">
      <?php foreach ($payables as $s): ?>
        <div class="ledger-item payment">
          <div class="item-main">
            <a href="<?= BASE_URL ?>supplier-ledger.php?id=<?= $s['id'] ?>" class="item-title" style="display: block;">
              <?= htmlspecialchars($s['name']) ?>
            </a>
            <div class="item-sub">
              📞 <?= htmlspecialchars($s['mobile']) ?> • Code: <?= htmlspecialchars($s['supplier_code']) ?>
              <?php if (!empty($s['address'])): ?>
                • 📍 <?= htmlspecialchars(mb_strimwidth($s['address'], 0, 35, '...')) ?>
              <?php endif; ?>
            </div>
          </div>

          <div class="item-amount">
            <div class="amount-val text-payment"><?= format_currency($s['balance']) ?></div>
            <div class="item-sub">You Owe Supplier</div>
          </div>

          <div style="display: flex; gap: 6px; margin-left: 12px;">
            <a href="<?= BASE_URL ?>supplier-payment.php?supplier_id=<?= $s['id'] ?>" class="btn btn-success btn-sm" style="padding: 6px 12px;">
              Pay Now
            </a>
            <a href="<?= BASE_URL ?>supplier-ledger.php?id=<?= $s['id'] ?>" class="btn btn-outline btn-sm" style="padding: 6px 10px;">
              Khata
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
