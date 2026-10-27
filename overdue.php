<?php
/**
 * Hisabio - Overdue Accounts Tracker
 * PRD Section 7.1, 7.4 & 26: Balances past their promised due date
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$biz = current_business();
$biz_id = (int)$biz['id'];
$pdo = get_db();

// Find credit transactions that are past due date
$stmt = $pdo->prepare("
    SELECT t.*, 
           c.id AS customer_id, c.name AS customer_name, c.mobile AS customer_mobile, c.customer_code,
           DATEDIFF(CURRENT_DATE, t.due_date) AS days_overdue
    FROM transactions t
    JOIN accounts a ON a.id = t.account_id
    JOIN customers c ON c.id = a.customer_id
    WHERE t.business_id = ? 
      AND t.transaction_type = 'credit_sale' 
      AND t.status = 'completed'
      AND t.due_date IS NOT NULL 
      AND t.due_date < CURRENT_DATE
    ORDER BY t.due_date ASC
");
$stmt->execute([$biz_id]);
$overdue_entries = $stmt->fetchAll();

$total_overdue = 0.00;
foreach ($overdue_entries as $item) {
    $total_overdue += (float)$item['amount'];
}

$page_title = 'Overdue Accounts';
include __DIR__ . '/includes/header.php';
?>

<div class="page-container">
  <div class="page-header">
    <div>
      <h1 class="page-title text-danger">⏰ Overdue Accounts</h1>
      <p class="page-subtitle">
        <?= count($overdue_entries) ?> overdue entries • Total Overdue: <strong><?= format_currency($total_overdue) ?></strong>
      </p>
    </div>

    <div style="display: flex; gap: 8px;">
      <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-outline btn-sm">
        ← <?= __('dashboard') ?>
      </a>
    </div>
  </div>

  <?php if (empty($overdue_entries)): ?>
    <div class="card" style="text-align: center; padding: 40px 16px;">
      <h3 style="font-size: 18px; color: var(--payment); margin-bottom: 8px;">✨ No Overdue Accounts!</h3>
      <p style="font-size: 14px; color: var(--text-muted);">None of your customers have crossed their agreed payment due dates.</p>
    </div>
  <?php else: ?>
    <div class="ledger-stack">
      <?php foreach ($overdue_entries as $entry): ?>
        <?php
          $msg = sprintf(
            "Assalam-o-Alaikum %s sahib! This is a reminder from %s regarding invoice %s of %s which was due on %s (%d days ago). Please arrange payment at your earliest convenience. JazakAllah khair.",
            $entry['customer_name'],
            $biz['business_name'],
            $entry['transaction_reference'],
            format_currency($entry['amount']),
            format_date($entry['due_date']),
            $entry['days_overdue']
          );
        ?>
        <div class="ledger-item credit" style="border-left-color: var(--danger);">
          <div class="item-main">
            <div class="flex-between" style="justify-content: flex-start; gap: 8px;">
              <a href="<?= BASE_URL ?>customer-ledger.php?id=<?= $entry['customer_id'] ?>" class="item-title" style="display: block;">
                <?= htmlspecialchars($entry['customer_name']) ?>
              </a>
              <span class="badge badge-danger" style="font-size: 11px;">
                <?= $entry['days_overdue'] ?> days overdue
              </span>
            </div>
            
            <div class="item-sub" style="margin-top: 4px;">
              Due Date: <strong style="color: var(--danger);"><?= format_date($entry['due_date']) ?></strong>
              • Ref: <code><?= htmlspecialchars($entry['transaction_reference']) ?></code>
              • 📞 <?= htmlspecialchars($entry['customer_mobile']) ?>
            </div>
          </div>

          <div class="item-amount">
            <div class="amount-val text-danger"><?= format_currency($entry['amount']) ?></div>
            <div class="item-sub">Due Amount</div>
          </div>

          <div style="display: flex; gap: 6px; margin-left: 12px;">
            <a href="<?= build_whatsapp_link($entry['customer_mobile'], $msg) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp btn-sm" title="WhatsApp Reminder" style="padding: 6px 10px;">
              💬 Remind
            </a>
            <a href="<?= BASE_URL ?>receive-payment.php?customer_id=<?= $entry['customer_id'] ?>" class="btn btn-success btn-sm" style="padding: 6px 10px;">
              Wasool
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
