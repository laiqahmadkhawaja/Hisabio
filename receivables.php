<?php
/**
 * Hisabio - Outstanding Receivables (You Will Get)
 * PRD Section 7.4 & 26: Complete list of all customers owing money
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
    SELECT c.id, c.name, c.mobile, c.customer_code, c.address, a.id AS account_id
    FROM customers c
    JOIN accounts a ON a.customer_id = c.id
    WHERE c.business_id = ? AND c.status = 'active'
");
$stmt->execute([$biz_id]);
$all_customers = $stmt->fetchAll();

$receivables = [];
$total_amount = 0.00;

foreach ($all_customers as $c) {
    $bal = calculate_customer_balance((int)$c['account_id'], $pdo);
    if ($bal > 0) {
        $c['balance'] = $bal;
        $total_amount += $bal;
        $receivables[] = $c;
    }
}

usort($receivables, fn($a, $b) => $b['balance'] <=> $a['balance']);

$page_title = 'Outstanding Receivables';
include __DIR__ . '/includes/header.php';
?>

<div class="page-container">
  <div class="page-header">
    <div>
      <h1 class="page-title text-credit">💰 Outstanding Receivables</h1>
      <p class="page-subtitle">
        <?= count($receivables) ?> customers owe a total of <strong><?= format_currency($total_amount) ?></strong>
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

  <?php if (empty($receivables)): ?>
    <div class="card" style="text-align: center; padding: 40px 16px;">
      <h3 style="font-size: 18px; color: var(--payment); margin-bottom: 8px;">🎉 Zero Outstanding Udhaar!</h3>
      <p style="font-size: 14px; color: var(--text-muted);">All your customers have completely cleared their dues.</p>
    </div>
  <?php else: ?>
    <div class="ledger-stack">
      <?php foreach ($receivables as $c): ?>
        <?php
          $msg = sprintf(
            "Assalam-o-Alaikum %s sahib! Friendly reminder from %s. Your outstanding balance is %s. Thank you! JazakAllah khair.",
            $c['name'],
            $biz['business_name'],
            format_currency($c['balance'])
          );
        ?>
        <div class="ledger-item credit">
          <div class="item-main">
            <a href="<?= BASE_URL ?>customer-ledger.php?id=<?= $c['id'] ?>" class="item-title" style="display: block;">
              <?= htmlspecialchars($c['name']) ?>
            </a>
            <div class="item-sub">
              📞 <?= htmlspecialchars($c['mobile']) ?> • Code: <?= htmlspecialchars($c['customer_code']) ?>
              <?php if (!empty($c['address'])): ?>
                • 📍 <?= htmlspecialchars(mb_strimwidth($c['address'], 0, 35, '...')) ?>
              <?php endif; ?>
            </div>
          </div>

          <div class="item-amount">
            <div class="amount-val text-credit"><?= format_currency($c['balance']) ?></div>
            <div class="item-sub">Pending Receivable</div>
          </div>

          <div style="display: flex; gap: 6px; margin-left: 12px;">
            <a href="<?= build_whatsapp_link($c['mobile'], $msg) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp btn-sm" title="WhatsApp Reminder" style="padding: 6px 10px;">
              💬 Reminder
            </a>
            <a href="<?= BASE_URL ?>receive-payment.php?customer_id=<?= $c['id'] ?>" class="btn btn-success btn-sm" style="padding: 6px 10px;">
              +Wasool
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
