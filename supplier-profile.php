<?php
/**
 * Hisabio - Supplier Profile
 * PRD Section 10: Supplier overview, payables KPI, quick actions, inline archive confirmation
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$biz = current_business();
$biz_id = (int)$biz['id'];
$supplier_id = (int)($_GET['id'] ?? 0);
$pdo = get_db();

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
$balance = calculate_supplier_balance($account_id, $pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    check_csrf();
    if ($_POST['action'] === 'archive') {
        $stmtArch = $pdo->prepare("UPDATE suppliers SET status = 'archived', archived_at = NOW() WHERE id = ? AND business_id = ?");
        $stmtArch->execute([$supplier_id, $biz_id]);
        flash_message('info', 'Supplier archived successfully.');
    } elseif ($_POST['action'] === 'restore') {
        $stmtRest = $pdo->prepare("UPDATE suppliers SET status = 'active', archived_at = NULL WHERE id = ? AND business_id = ?");
        $stmtRest->execute([$supplier_id, $biz_id]);
        flash_message('success', 'Supplier restored to active records.');
    }
    header('Location: ' . BASE_URL . 'supplier-profile.php?id=' . $supplier_id);
    exit;
}

// Supplier Financial Statistics
$stmtStats = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_txs,
        SUM(CASE WHEN transaction_type = 'supplier_purchase' AND status = 'completed' THEN amount ELSE 0 END) AS total_purchases,
        SUM(CASE WHEN transaction_type = 'supplier_payment' AND status = 'completed' THEN amount ELSE 0 END) AS total_payments,
        MAX(transaction_date) AS last_tx_date
    FROM transactions
    WHERE account_id = ?
");
$stmtStats->execute([$account_id]);
$stats = $stmtStats->fetch();

$page_title = htmlspecialchars($supplier['name']);
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 720px;">
  <div class="page-header">
    <div>
      <h1 class="page-title">
        <?= htmlspecialchars($supplier['name']) ?>
        <?php if ($supplier['status'] === 'archived'): ?>
          <span class="badge badge-danger">Archived</span>
        <?php endif; ?>
      </h1>
      <p class="page-subtitle">
        <?= htmlspecialchars($supplier['supplier_code']) ?> • Added <?= format_date($supplier['created_at']) ?>
      </p>
    </div>
    
    <div style="display: flex; gap: 8px;">
      <a href="<?= BASE_URL ?>supplier-edit.php?id=<?= $supplier['id'] ?>" class="btn btn-outline btn-sm">
        ✏️ <?= __('edit') ?>
      </a>
      <a href="<?= BASE_URL ?>suppliers.php" class="btn btn-outline btn-sm">
        ← <?= __('suppliers') ?>
      </a>
    </div>
  </div>

  <!-- Outstanding Payable Balance Card -->
  <div class="card" style="border-left: 6px solid <?= $balance > 0 ? 'var(--payment)' : ($balance < 0 ? 'var(--credit)' : 'var(--border)') ?>;">
    <div class="flex-between">
      <div>
        <div style="font-size: 13px; font-weight: 500; color: var(--text-muted);">Current Payable Balance</div>
        <div style="font-size: 28px; font-weight: 800; color: <?= $balance > 0 ? 'var(--payment)' : ($balance < 0 ? 'var(--credit)' : 'var(--text)') ?>;">
          <?= format_currency($balance) ?>
        </div>
        <div style="font-size: 12.5px; color: var(--text-muted); margin-top: 2px;">
          <?= $balance > 0 ? 'You owe this supplier' : ($balance < 0 ? 'Supplier owes you (Advance paid)' : 'Balance is completely settled') ?>
        </div>
      </div>

      <div>
        <a href="tel:<?= htmlspecialchars($supplier['mobile']) ?>" class="btn btn-outline" style="padding: 10px 16px;">
          📞 Call
        </a>
      </div>
    </div>
  </div>

  <!-- Quick Actions for Supplier -->
  <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 16px;">
    <a href="<?= BASE_URL ?>supplier-purchase.php?supplier_id=<?= $supplier['id'] ?>" class="btn btn-primary" style="background: var(--credit); border-color: var(--credit);">
      📦 <?= __('record_purchase') ?>
    </a>
    <a href="<?= BASE_URL ?>supplier-payment.php?supplier_id=<?= $supplier['id'] ?>" class="btn btn-success">
      💳 <?= __('pay_supplier') ?>
    </a>
  </div>

  <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 20px;">
    <a href="<?= BASE_URL ?>supplier-ledger.php?id=<?= $supplier['id'] ?>" class="btn btn-outline" style="font-weight: 600;">
      📑 View Khata History
    </a>
    <a href="<?= BASE_URL ?>supplier-statement.php?id=<?= $supplier['id'] ?>" class="btn btn-outline" style="font-weight: 600;">
      🖨️ Statement
    </a>
  </div>

  <!-- Supplier Stats & Details -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Supplier Details</h2>
      <span style="font-size: 12px; color: var(--text-muted);"><?= $stats['total_txs'] ?> total transactions</span>
    </div>

    <div class="kpi-grid" style="grid-template-columns: repeat(2, 1fr); margin-bottom: 12px;">
      <div class="kpi-card" style="padding: 10px;">
        <span class="kpi-label">Total Purchases on Credit</span>
        <span class="kpi-value text-credit" style="font-size: 17px;"><?= format_currency($stats['total_purchases']) ?></span>
      </div>
      <div class="kpi-card" style="padding: 10px;">
        <span class="kpi-label">Total Payments Made</span>
        <span class="kpi-value text-payment" style="font-size: 17px;"><?= format_currency($stats['total_payments']) ?></span>
      </div>
    </div>

    <table style="width: 100%; border-collapse: collapse; font-size: 13.5px;">
      <tr>
        <td style="padding: 8px 0; color: var(--text-muted); width: 140px;">Phone:</td>
        <td style="padding: 8px 0; font-weight: 600;"><a href="tel:<?= htmlspecialchars($supplier['mobile']) ?>"><?= htmlspecialchars($supplier['mobile']) ?></a></td>
      </tr>
      <?php if (!empty($supplier['alternate_contact'])): ?>
        <tr>
          <td style="padding: 8px 0; color: var(--text-muted);">Contact Rep:</td>
          <td style="padding: 8px 0;"><?= htmlspecialchars($supplier['alternate_contact']) ?></td>
        </tr>
      <?php endif; ?>
      <tr>
        <td style="padding: 8px 0; color: var(--text-muted);">Address:</td>
        <td style="padding: 8px 0;"><?= htmlspecialchars($supplier['address'] ?: 'Not provided') ?></td>
      </tr>
      <tr>
        <td style="padding: 8px 0; color: var(--text-muted);">Opening Balance:</td>
        <td style="padding: 8px 0;"><?= format_currency($supplier['opening_balance']) ?> (<?= ucfirst($supplier['opening_balance_direction']) ?>)</td>
      </tr>
      <tr>
        <td style="padding: 8px 0; color: var(--text-muted);">Last Transaction:</td>
        <td style="padding: 8px 0;"><?= format_date($stats['last_tx_date']) ?></td>
      </tr>
      <?php if (!empty($supplier['notes'])): ?>
        <tr>
          <td style="padding: 8px 0; color: var(--text-muted);">Notes:</td>
          <td style="padding: 8px 0;"><?= htmlspecialchars($supplier['notes']) ?></td>
        </tr>
      <?php endif; ?>
    </table>
  </div>

  <!-- Inline Archive Confirmation -->
  <div class="card" style="border-color: #F3F4F6;">
    <div class="card-header">
      <h3 class="card-title" style="font-size: 14px;">Status Actions</h3>
    </div>

    <?php if ($supplier['status'] === 'active'): ?>
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="archive">
        
        <details class="inline-confirmation" style="margin: 0;">
          <summary style="font-weight: 600; cursor: pointer; color: var(--danger);">
            ⚠️ Archive this supplier...
          </summary>
          <div style="margin-top: 10px;">
            <p class="confirmation-body mb-3">
              Are you sure you want to archive <strong><?= htmlspecialchars($supplier['name']) ?></strong>? All previous purchase and payment records are preserved in reports.
            </p>
            <button type="submit" class="btn btn-danger btn-sm">
              Confirm Archive Supplier
            </button>
          </div>
        </details>
      </form>
    <?php else: ?>
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="restore">
        <button type="submit" class="btn btn-success btn-sm">
          🔄 Restore Supplier to Active List
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
