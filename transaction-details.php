<?php
/**
 * Hisabio - Transaction Details & Printable Digital Receipt
 * PRD Section 11.2: Dedicated details page, receipt format, WhatsApp share, audit trail
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$biz = current_business();
$biz_id = (int)$biz['id'];
$tx_id = (int)($_GET['id'] ?? 0);
$pdo = get_db();

$stmt = $pdo->prepare("
    SELECT t.*, 
           a.id AS account_id, a.account_type, a.opening_balance, a.opening_balance_direction,
           c.id AS customer_id, c.name AS customer_name, c.mobile AS customer_mobile, c.address AS customer_address,
           s.id AS supplier_id, s.name AS supplier_name, s.mobile AS supplier_mobile, s.address AS supplier_address,
           u.full_name AS creator_name,
           rev_tx.transaction_reference AS rev_ref
    FROM transactions t
    JOIN accounts a ON a.id = t.account_id
    LEFT JOIN customers c ON c.id = a.customer_id
    LEFT JOIN suppliers s ON s.id = a.supplier_id
    LEFT JOIN users u ON u.id = t.created_by
    LEFT JOIN transactions rev_tx ON rev_tx.id = t.reversed_transaction_id
    WHERE t.id = ? AND t.business_id = ?
");
$stmt->execute([$tx_id, $biz_id]);
$tx = $stmt->fetch();

if (!$tx) {
    flash_message('error', 'Transaction not found.');
    header('Location: ' . BASE_URL . 'transactions.php');
    exit;
}

$is_customer = ($tx['account_type'] === 'customer');
$party_name = $is_customer ? ($tx['customer_name'] ?? 'Customer') : ($tx['supplier_name'] ?? 'Supplier');
$party_mobile = $is_customer ? ($tx['customer_mobile'] ?? '') : ($tx['supplier_mobile'] ?? '');
$party_address = $is_customer ? ($tx['customer_address'] ?? '') : ($tx['supplier_address'] ?? '');

// Current balance of the party
$current_balance = $is_customer ? calculate_customer_balance((int)$tx['account_id'], $pdo) : calculate_supplier_balance((int)$tx['account_id'], $pdo);

// Compose WhatsApp Digital Receipt Message
$receipt_msg = sprintf(
    "*%s - DIGITAL RECEIPT*\nRef: %s\nDate: %s\nParty: %s\nType: %s\nAmount: %s\nMethod: %s\nRemaining Balance: %s\nThank you! Har Hisab Saaf, Har Khata Aasaan.",
    $biz['business_name'],
    $tx['transaction_reference'],
    format_date($tx['transaction_date']),
    $party_name,
    strtoupper(str_replace('_', ' ', $tx['transaction_type'])),
    format_currency($tx['amount']),
    $tx['payment_method'] ?: 'Cash',
    format_currency($current_balance)
);
$whatsapp_link = build_whatsapp_link($party_mobile, $receipt_msg);

$is_credit_type = in_array($tx['transaction_type'], ['credit_sale', 'supplier_purchase']);
$page_title = 'Receipt #' . htmlspecialchars($tx['transaction_reference']);
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 680px;">
  <!-- Screen Navigation Bar -->
  <div class="page-header no-print">
    <div>
      <h1 class="page-title">
        🧾 Transaction Details
      </h1>
      <p class="page-subtitle">Ref: <?= htmlspecialchars($tx['transaction_reference']) ?></p>
    </div>

    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
      <button type="button" onclick="window.print()" class="btn btn-outline btn-sm">
        🖨️ Print Receipt
      </button>
      <?php if (!empty($party_mobile)): ?>
        <a href="<?= $whatsapp_link ?>" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp btn-sm">
          💬 Send on WhatsApp
        </a>
      <?php endif; ?>
      <?php if ($is_customer): ?>
        <a href="<?= BASE_URL ?>customer-ledger.php?id=<?= $tx['customer_id'] ?>" class="btn btn-outline btn-sm">
          ← Customer Khata
        </a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>supplier-ledger.php?id=<?= $tx['supplier_id'] ?>" class="btn btn-outline btn-sm">
          ← Supplier Khata
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Reversal Warning Notice if Already Reversed -->
  <?php if ($tx['status'] === 'reversed'): ?>
    <div class="inline-confirmation" style="margin-top: 0; margin-bottom: 20px;">
      <span class="confirmation-title">⚠️ This transaction was reversed / cancelled</span>
      <p class="confirmation-body">
        <strong>Reason:</strong> <?= htmlspecialchars($tx['correction_reason'] ?: 'Transaction correction') ?><br>
        This transaction is preserved for auditing purposes, but its financial amount has been nullified.
      </p>
    </div>
  <?php endif; ?>

  <!-- Printable Receipt Card -->
  <div class="card" style="padding: 24px; border: 1.5px solid var(--border); box-shadow: var(--shadow-md);">
    <!-- Business Header -->
    <div class="print-header" style="text-align: center; border-bottom: 2px dashed var(--border); padding-bottom: 16px; margin-bottom: 20px;">
      <h2 style="font-size: 22px; font-weight: 800; color: var(--primary);">
        <?= htmlspecialchars($biz['business_name']) ?>
      </h2>
      <div style="font-size: 13px; color: var(--text-muted);">
        <?= htmlspecialchars($biz['address'] ?: 'Pakistan') ?>
      </div>
      <div style="font-size: 12.5px; color: var(--text-muted);">
        Phone: <?= htmlspecialchars($biz['phone'] ?: 'N/A') ?>
      </div>
      <div style="display: inline-block; margin-top: 8px; font-size: 11.5px; font-weight: 700; letter-spacing: 1px; background: #F3F4F6; padding: 4px 10px; border-radius: 4px;">
        OFFICIAL TRANSACTION VOUCHER
      </div>
    </div>

    <!-- Voucher Key Financial Amount -->
    <div style="text-align: center; margin: 20px 0;">
      <div style="font-size: 13px; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">
        <?= strtoupper(str_replace('_', ' ', $tx['transaction_type'])) ?>
      </div>
      <div style="font-size: 34px; font-weight: 800; color: <?= $is_credit_type ? 'var(--credit)' : 'var(--payment)' ?>; margin-top: 2px;">
        <?= format_currency($tx['amount']) ?>
      </div>
      <div style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
        Method: <strong><?= htmlspecialchars($tx['payment_method'] ?: 'Cash') ?></strong>
      </div>
    </div>

    <!-- Detailed Ledger Voucher Fields -->
    <table style="width: 100%; border-collapse: collapse; font-size: 14px; margin-bottom: 20px;">
      <tr style="border-bottom: 1px solid #F3F4F6;">
        <td style="padding: 8px 0; color: var(--text-muted); width: 140px;">Voucher Ref:</td>
        <td style="padding: 8px 0; font-family: monospace; font-weight: 700;"><?= htmlspecialchars($tx['transaction_reference']) ?></td>
      </tr>
      <tr style="border-bottom: 1px solid #F3F4F6;">
        <td style="padding: 8px 0; color: var(--text-muted);">Transaction Date:</td>
        <td style="padding: 8px 0; font-weight: 600;"><?= format_date($tx['transaction_date']) ?></td>
      </tr>
      <tr style="border-bottom: 1px solid #F3F4F6;">
        <td style="padding: 8px 0; color: var(--text-muted);"><?= $is_customer ? 'Customer' : 'Supplier' ?>:</td>
        <td style="padding: 8px 0; font-weight: 700; color: var(--text);">
          <?= htmlspecialchars($party_name) ?> (<?= htmlspecialchars($party_mobile) ?>)
        </td>
      </tr>
      <?php if (!empty($party_address)): ?>
        <tr style="border-bottom: 1px solid #F3F4F6;">
          <td style="padding: 8px 0; color: var(--text-muted);">Address:</td>
          <td style="padding: 8px 0;"><?= htmlspecialchars($party_address) ?></td>
        </tr>
      <?php endif; ?>
      <?php if (!empty($tx['reference_number'])): ?>
        <tr style="border-bottom: 1px solid #F3F4F6;">
          <td style="padding: 8px 0; color: var(--text-muted);">External Slip / Bill #:</td>
          <td style="padding: 8px 0;"><?= htmlspecialchars($tx['reference_number']) ?></td>
        </tr>
      <?php endif; ?>
      <?php if (!empty($tx['description'])): ?>
        <tr style="border-bottom: 1px solid #F3F4F6;">
          <td style="padding: 8px 0; color: var(--text-muted);">Particulars:</td>
          <td style="padding: 8px 0;"><?= htmlspecialchars($tx['description']) ?></td>
        </tr>
      <?php endif; ?>
      <?php if (!empty($tx['due_date'])): ?>
        <tr style="border-bottom: 1px solid #F3F4F6;">
          <td style="padding: 8px 0; color: var(--text-muted);">Due Date:</td>
          <td style="padding: 8px 0; color: var(--danger); font-weight: 600;"><?= format_date($tx['due_date']) ?></td>
        </tr>
      <?php endif; ?>
      <tr style="border-bottom: 1px solid #F3F4F6;">
        <td style="padding: 8px 0; color: var(--text-muted);">Recorded By:</td>
        <td style="padding: 8px 0;"><?= htmlspecialchars($tx['creator_name'] ?? 'Staff') ?> (<?= format_datetime($tx['created_at']) ?>)</td>
      </tr>
      <tr style="border-top: 2px solid var(--border); background: #FAFBFD;">
        <td style="padding: 10px 0; font-weight: 700; color: var(--text);">
          Current Balance:
        </td>
        <td style="padding: 10px 0; font-size: 16px; font-weight: 800; color: <?= $current_balance > 0 ? 'var(--credit)' : 'var(--payment)' ?>;">
          <?= format_currency($current_balance) ?>
          <span style="font-size: 12px; font-weight: normal; color: var(--text-muted);">
            (<?= $is_customer ? ($current_balance > 0 ? 'Customer owes' : 'Advance') : ($current_balance > 0 ? 'You owe' : 'Advance') ?>)
          </span>
        </td>
      </tr>
    </table>

    <div class="print-footer" style="text-align: center; border-top: 1px dashed var(--border); padding-top: 14px; font-size: 12px; color: var(--text-muted);">
      <?= htmlspecialchars($biz['receipt_footer'] ?: 'Shukriya! JazakAllah khair for your business. Har Hisab Saaf, Har Khata Aasaan.') ?>
    </div>
  </div>

  <!-- Reversal Action Link (Strict No-Modal Policy: PRD 11.3 & 16) -->
  <?php if ($tx['status'] === 'completed' && $tx['transaction_type'] !== 'reversal'): ?>
    <div class="card no-print mt-3" style="border-color: #FEE2E2;">
      <div class="flex-between">
        <div>
          <span style="font-weight: 600; font-size: 14px; color: var(--danger);">Wrong entry or mistake?</span>
          <p style="font-size: 12.5px; color: var(--text-muted); margin-top: 2px;">
            Reverse this entry with a mandatory audit reason. The original entry is never deleted.
          </p>
        </div>
        <a href="<?= BASE_URL ?>transaction-reverse.php?id=<?= $tx['id'] ?>" class="btn btn-outline btn-sm" style="color: var(--danger); border-color: #FCA5A5;">
          ⚠️ Reverse Entry
        </a>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
