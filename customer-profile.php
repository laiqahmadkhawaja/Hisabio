<?php
/**
 * Hisabio - Customer Profile
 * PRD Section 8.3: Customer summary, statistics, quick actions, inline archive confirmation
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$biz = current_business();
$biz_id = (int)$biz['id'];
$customer_id = (int)($_GET['id'] ?? 0);
$pdo = get_db();

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
$balance = calculate_customer_balance($account_id, $pdo);

// Handle Archive / Restore inline confirmation POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    check_csrf();
    if ($_POST['action'] === 'archive') {
        $stmtArch = $pdo->prepare("UPDATE customers SET status = 'archived', archived_at = NOW() WHERE id = ? AND business_id = ?");
        $stmtArch->execute([$customer_id, $biz_id]);
        flash_message('info', 'Customer archived successfully.');
    } elseif ($_POST['action'] === 'restore') {
        $stmtRest = $pdo->prepare("UPDATE customers SET status = 'active', archived_at = NULL WHERE id = ? AND business_id = ?");
        $stmtRest->execute([$customer_id, $biz_id]);
        flash_message('success', 'Customer restored to active khata.');
    }
    header('Location: ' . BASE_URL . 'customer-profile.php?id=' . $customer_id);
    exit;
}

// Financial Statistics
$stmtStats = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_txs,
        SUM(CASE WHEN transaction_type = 'credit_sale' AND status = 'completed' THEN amount ELSE 0 END) AS total_credit_given,
        SUM(CASE WHEN transaction_type = 'payment_received' AND status = 'completed' THEN amount ELSE 0 END) AS total_payments_received,
        MAX(transaction_date) AS last_tx_date
    FROM transactions
    WHERE account_id = ?
");
$stmtStats->execute([$account_id]);
$stats = $stmtStats->fetch();

$whatsapp_msg = sprintf(
    "Assalam-o-Alaikum %s sahib! This is a reminder from %s. Your outstanding balance is %s. Thank you for your business. JazakAllah khair.",
    $customer['name'],
    $biz['business_name'],
    format_currency($balance)
);
$whatsapp_link = build_whatsapp_link($customer['mobile'], $whatsapp_msg);

$page_title = htmlspecialchars($customer['name']);
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 720px;">
  <div class="page-header">
    <div>
      <h1 class="page-title">
        <?= htmlspecialchars($customer['name']) ?>
        <?php if ($customer['status'] === 'archived'): ?>
          <span class="badge badge-danger">Archived</span>
        <?php endif; ?>
      </h1>
      <p class="page-subtitle">
        <?= htmlspecialchars($customer['customer_code']) ?> • Added <?= format_date($customer['created_at']) ?>
      </p>
    </div>
    
    <div style="display: flex; gap: 8px;">
      <a href="<?= BASE_URL ?>customer-edit.php?id=<?= $customer['id'] ?>" class="btn btn-outline btn-sm">
        ✏️ <?= __('edit') ?>
      </a>
      <a href="<?= BASE_URL ?>customers.php" class="btn btn-outline btn-sm">
        ← <?= __('customers') ?>
      </a>
    </div>
  </div>

  <!-- Outstanding Balance Card -->
  <div class="card" style="border-left: 6px solid <?= $balance > 0 ? 'var(--credit)' : ($balance < 0 ? 'var(--payment)' : 'var(--border)') ?>;">
    <div class="flex-between">
      <div>
        <div style="font-size: 13px; font-weight: 500; color: var(--text-muted);"><?= __('running_balance') ?></div>
        <div style="font-size: 28px; font-weight: 800; color: <?= $balance > 0 ? 'var(--credit)' : ($balance < 0 ? 'var(--payment)' : 'var(--text)') ?>;">
          <?= format_currency($balance) ?>
        </div>
        <div style="font-size: 12.5px; color: var(--text-muted); margin-top: 2px;">
          <?= $balance > 0 ? 'Customer owes you this amount' : ($balance < 0 ? 'You owe customer (Advance credit)' : 'All accounts settled') ?>
        </div>
      </div>

      <div>
        <a href="<?= $whatsapp_link ?>" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp" style="padding: 10px 16px;">
          💬 WhatsApp
        </a>
      </div>
    </div>
  </div>

  <!-- Quick Action Buttons for Customer -->
  <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 16px;">
    <a href="<?= BASE_URL ?>give-credit.php?customer_id=<?= $customer['id'] ?>" class="btn btn-primary" style="background: var(--credit); border-color: var(--credit);">
      📤 <?= __('give_credit') ?>
    </a>
    <a href="<?= BASE_URL ?>receive-payment.php?customer_id=<?= $customer['id'] ?>" class="btn btn-success">
      📥 <?= __('receive_payment') ?>
    </a>
  </div>

  <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 20px;">
    <a href="<?= BASE_URL ?>customer-ledger.php?id=<?= $customer['id'] ?>" class="btn btn-outline" style="font-weight: 600;">
      📑 View Full Khata
    </a>
    <a href="<?= BASE_URL ?>customer-statement.php?id=<?= $customer['id'] ?>" class="btn btn-outline" style="font-weight: 600;">
      🖨️ Statement
    </a>
  </div>

  <!-- Customer Ledger Stats & Details -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Account Summary</h2>
      <span style="font-size: 12px; color: var(--text-muted);"><?= $stats['total_txs'] ?> total entries</span>
    </div>

    <div class="kpi-grid" style="grid-template-columns: repeat(2, 1fr); margin-bottom: 12px;">
      <div class="kpi-card" style="padding: 10px;">
        <span class="kpi-label">Total Credit Given</span>
        <span class="kpi-value text-credit" style="font-size: 17px;"><?= format_currency($stats['total_credit_given']) ?></span>
      </div>
      <div class="kpi-card" style="padding: 10px;">
        <span class="kpi-label">Total Cash Received</span>
        <span class="kpi-value text-payment" style="font-size: 17px;"><?= format_currency($stats['total_payments_received']) ?></span>
      </div>
    </div>

    <table style="width: 100%; border-collapse: collapse; font-size: 13.5px;">
      <tr>
        <td style="padding: 8px 0; color: var(--text-muted); width: 140px;">Phone:</td>
        <td style="padding: 8px 0; font-weight: 600;"><a href="tel:<?= htmlspecialchars($customer['mobile']) ?>"><?= htmlspecialchars($customer['mobile']) ?></a></td>
      </tr>
      <?php if (!empty($customer['alternate_mobile'])): ?>
        <tr>
          <td style="padding: 8px 0; color: var(--text-muted);">Alternate Phone:</td>
          <td style="padding: 8px 0;"><?= htmlspecialchars($customer['alternate_mobile']) ?></td>
        </tr>
      <?php endif; ?>
      <tr>
        <td style="padding: 8px 0; color: var(--text-muted);">Address:</td>
        <td style="padding: 8px 0;"><?= htmlspecialchars($customer['address'] ?: 'Not provided') ?></td>
      </tr>
      <tr>
        <td style="padding: 8px 0; color: var(--text-muted);">Opening Balance:</td>
        <td style="padding: 8px 0;"><?= format_currency($customer['opening_balance']) ?> (<?= ucfirst($customer['opening_balance_direction']) ?>)</td>
      </tr>
      <tr>
        <td style="padding: 8px 0; color: var(--text-muted);">Last Transaction:</td>
        <td style="padding: 8px 0;"><?= format_date($stats['last_tx_date']) ?></td>
      </tr>
      <?php if (!empty($customer['notes'])): ?>
        <tr>
          <td style="padding: 8px 0; color: var(--text-muted);">Notes:</td>
          <td style="padding: 8px 0;"><?= htmlspecialchars($customer['notes']) ?></td>
        </tr>
      <?php endif; ?>
    </table>
  </div>

  <!-- Strict No-Modal Inline Archive / Restore Confirmation Section (PRD 16.1) -->
  <div class="card" style="border-color: #F3F4F6;">
    <div class="card-header">
      <h3 class="card-title" style="font-size: 14px;">Account Status & Actions</h3>
    </div>

    <?php if ($customer['status'] === 'active'): ?>
      <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">
        Archiving removes this customer from the active listing without deleting past ledger entries.
      </p>

      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="archive">
        
        <details class="inline-confirmation" style="margin: 0;">
          <summary style="font-weight: 600; cursor: pointer; color: var(--danger);">
            ⚠️ Archive this customer account...
          </summary>
          <div style="margin-top: 10px;">
            <p class="confirmation-body mb-3">
              Are you sure you want to archive <strong><?= htmlspecialchars($customer['name']) ?></strong>? All previous khata records will be safely retained in your reports.
            </p>
            <button type="submit" class="btn btn-danger btn-sm">
              Confirm Archive Customer
            </button>
          </div>
        </details>
      </form>
    <?php else: ?>
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="restore">
        <button type="submit" class="btn btn-success btn-sm">
          🔄 Restore Customer to Active List
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
