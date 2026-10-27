<?php
/**
 * Hisabio - WhatsApp Payment Reminders & History
 * PRD Section 12: Click-to-chat WhatsApp link, audit history of sent reminders, bilingual templates
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$biz = current_business();
$biz_id = (int)$biz['id'];
$pdo = get_db();

// Fetch customers who owe money
$stmtCust = $pdo->prepare("
    SELECT c.id, c.name, c.mobile, c.customer_code, a.id AS account_id
    FROM customers c
    JOIN accounts a ON a.customer_id = c.id
    WHERE c.business_id = ? AND c.status = 'active'
");
$stmtCust->execute([$biz_id]);
$raw_customers = $stmtCust->fetchAll();

$debtor_customers = [];
foreach ($raw_customers as $c) {
    $bal = calculate_customer_balance((int)$c['account_id'], $pdo);
    if ($bal > 0) {
        $c['balance'] = $bal;
        $debtor_customers[] = $c;
    }
}
usort($debtor_customers, fn($a, $b) => $b['balance'] <=> $a['balance']);

// Handle recording a reminder dispatch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'log_reminder') {
    check_csrf();
    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $balance_snapshot = (float)($_POST['balance_snapshot'] ?? 0);
    $template_type = $_POST['template_type'] ?? 'urdu';

    $stmtCustLookup = $pdo->prepare("SELECT name, mobile FROM customers WHERE id = ? AND business_id = ?");
    $stmtCustLookup->execute([$customer_id, $biz_id]);
    $target_cust = $stmtCustLookup->fetch();

    if ($target_cust) {
        if ($template_type === 'urdu') {
            $msg = sprintf(
                "السلام علیکم %s صاحب! %s کی طرف سے آپ کے کھاتے کی بقایا رقم %s ہے۔ براہ کرم جلد ادائیگی فرما دیں۔ جزاک اللہ خیراً۔",
                $target_cust['name'],
                $biz['business_name'],
                format_currency($balance_snapshot)
            );
        } else {
            $msg = sprintf(
                "Assalam-o-Alaikum %s sahib! This is a payment reminder from %s. Your outstanding balance is %s. Thank you! JazakAllah khair.",
                $target_cust['name'],
                $biz['business_name'],
                format_currency($balance_snapshot)
            );
        }

        // Record reminder log
        $stmtLog = $pdo->prepare("
            INSERT INTO reminders 
            (business_id, customer_id, reminder_type, balance_snapshot, message, initiated_by, initiated_at, delivery_status)
            VALUES (?, ?, 'whatsapp', ?, ?, ?, NOW(), 'opened_in_whatsapp')
        ");
        $stmtLog->execute([$biz_id, $customer_id, $balance_snapshot, $msg, $_SESSION['user_id']]);

        // Redirect to WhatsApp web/app
        $wa_url = build_whatsapp_link($target_cust['mobile'], $msg);
        header('Location: ' . $wa_url);
        exit;
    }
}

// Fetch Reminder History
$stmtHistory = $pdo->prepare("
    SELECT r.*, c.name AS customer_name, c.mobile AS customer_mobile, u.full_name AS sender_name
    FROM reminders r
    JOIN customers c ON c.id = r.customer_id
    LEFT JOIN users u ON u.id = r.initiated_by
    WHERE r.business_id = ?
    ORDER BY r.initiated_at DESC
    LIMIT 50
");
$stmtHistory->execute([$biz_id]);
$reminder_history = $stmtHistory->fetchAll();

$page_title = __('reminders');
include __DIR__ . '/includes/header.php';
?>

<div class="page-container">
  <div class="page-header">
    <div>
      <h1 class="page-title">💬 <?= __('reminders') ?></h1>
      <p class="page-subtitle">Send polite 1-tap balance reminders to customers via WhatsApp</p>
    </div>

    <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-outline btn-sm">
      ← <?= __('dashboard') ?>
    </a>
  </div>

  <!-- Send New Reminder Card -->
  <div class="card mb-4">
    <div class="card-header">
      <h2 class="card-title">Send WhatsApp Reminder</h2>
    </div>

    <?php if (empty($debtor_customers)): ?>
      <p style="font-size: 14px; color: var(--text-muted); padding: 10px 0;">
        All customer accounts are clear! No pending balances to remind.
      </p>
    <?php else: ?>
      <form method="POST" action="" target="_blank" data-loading-state>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="log_reminder">

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="customer_id">Select Customer Owing Money</label>
            <select id="customer_id" name="customer_id" class="form-control" required onchange="updateBalanceSnap(this)">
              <option value="">-- Choose Customer --</option>
              <?php foreach ($debtor_customers as $dc): ?>
                <option value="<?= $dc['id'] ?>" data-balance="<?= $dc['balance'] ?>">
                  <?= htmlspecialchars($dc['name']) ?> (<?= htmlspecialchars($dc['mobile']) ?>) — Owes: <?= format_currency($dc['balance']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="template_type">Language Template</label>
            <select id="template_type" name="template_type" class="form-control">
              <option value="urdu">اردو (Urdu Message)</option>
              <option value="en">English (English Message)</option>
            </select>
          </div>
        </div>

        <input type="hidden" id="balance_snapshot" name="balance_snapshot" value="0">

        <button type="submit" class="btn btn-whatsapp" style="width: 100%; font-size: 15px; padding: 12px;">
          💬 Open WhatsApp & Send Message
        </button>
      </form>
    <?php endif; ?>
  </div>

  <!-- Reminder Audit History -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Reminder History Log</h2>
      <span style="font-size: 12px; color: var(--text-muted);"><?= count($reminder_history) ?> recorded reminders</span>
    </div>

    <?php if (empty($reminder_history)): ?>
      <p style="font-size: 14px; color: var(--text-muted); text-align: center; padding: 24px 0;">
        No WhatsApp reminders sent yet.
      </p>
    <?php else: ?>
      <div class="ledger-stack">
        <?php foreach ($reminder_history as $rem): ?>
          <div class="ledger-item">
            <div class="item-main">
              <div class="item-title">
                <?= htmlspecialchars($rem['customer_name']) ?>
                <span class="badge badge-neutral" style="font-size: 11px;">
                  <?= htmlspecialchars($rem['customer_mobile']) ?>
                </span>
              </div>
              <div class="item-sub" style="margin-top: 4px;">
                📅 <?= format_datetime($rem['initiated_at']) ?>
                • Sent by <?= htmlspecialchars($rem['sender_name'] ?? 'Staff') ?>
                • Status: <code><?= htmlspecialchars($rem['delivery_status']) ?></code>
              </div>
              <div style="font-size: 12.5px; color: var(--text); background: #FAFBFD; padding: 8px 12px; border-radius: 6px; margin-top: 8px; border: 1px solid var(--border);">
                "<?= htmlspecialchars($rem['message']) ?>"
              </div>
            </div>

            <div class="item-amount">
              <div class="amount-val text-credit"><?= format_currency($rem['balance_snapshot']) ?></div>
              <div class="item-sub">Snapshot Balance</div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
function updateBalanceSnap(select) {
  const selected = select.options[select.selectedIndex];
  const bal = selected.getAttribute('data-balance') || 0;
  document.getElementById('balance_snapshot').value = bal;
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
