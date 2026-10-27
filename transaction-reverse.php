<?php
/**
 * Hisabio - Transaction Reversal & Audited Correction
 * PRD Section 11.3 & 16.1: Immutable audit trail, reason required, database transaction
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$biz = current_business();
$biz_id = (int)$biz['id'];
$tx_id = (int)($_GET['id'] ?? 0);
$pdo = get_db();

$stmt = $pdo->prepare("
    SELECT t.*, a.account_type, 
           c.name AS customer_name, s.name AS supplier_name
    FROM transactions t
    JOIN accounts a ON a.id = t.account_id
    LEFT JOIN customers c ON c.id = a.customer_id
    LEFT JOIN suppliers s ON s.id = a.supplier_id
    WHERE t.id = ? AND t.business_id = ?
");
$stmt->execute([$tx_id, $biz_id]);
$tx = $stmt->fetch();

if (!$tx) {
    flash_message('error', 'Transaction not found.');
    header('Location: ' . BASE_URL . 'transactions.php');
    exit;
}

if ($tx['status'] === 'reversed') {
    flash_message('info', 'This transaction has already been reversed.');
    header('Location: ' . BASE_URL . 'transaction-details.php?id=' . $tx_id);
    exit;
}

$errors = [];
$reason = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $reason = trim($_POST['correction_reason'] ?? '');
    if (empty($reason) || strlen($reason) < 5) {
        $errors[] = 'Please provide a valid correction reason (minimum 5 characters).';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Mark original transaction as reversed
            $stmtUpdate = $pdo->prepare("
                UPDATE transactions 
                SET status = 'reversed', correction_reason = ?, updated_at = NOW() 
                WHERE id = ? AND business_id = ?
            ");
            $stmtUpdate->execute([$reason, $tx_id, $biz_id]);

            // 2. Insert auditable reversal entry
            $rev_ref = generate_reference('REV');
            $rev_amount = (float)$tx['amount'];

            $stmtRev = $pdo->prepare("
                INSERT INTO transactions 
                (business_id, transaction_reference, account_id, transaction_type, transaction_date, amount, payment_method, description, status, created_by, created_at, reversed_transaction_id, correction_reason)
                VALUES (?, ?, ?, 'reversal', CURRENT_DATE, ?, ?, ?, 'completed', ?, NOW(), ?, ?)
            ");
            $desc = 'Reversal of ' . $tx['transaction_reference'] . ': ' . $reason;
            $stmtRev->execute([
                $biz_id,
                $rev_ref,
                $tx['account_id'],
                $rev_amount,
                $tx['payment_method'],
                $desc,
                $_SESSION['user_id'],
                $tx_id,
                $reason
            ]);
            $reversal_id = (int)$pdo->lastInsertId();

            // 3. Log into transaction_audit_logs
            create_audit_log(
                $biz_id,
                $tx_id,
                'reversal',
                ['status' => 'completed', 'amount' => $tx['amount']],
                ['status' => 'reversed', 'reversal_tx_id' => $reversal_id],
                $reason,
                $_SESSION['user_id']
            );

            $pdo->commit();

            flash_message('success', 'Transaction successfully reversed. An auditable reversal record was created.');
            header('Location: ' . BASE_URL . 'transaction-details.php?id=' . $tx_id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Error reversing transaction: ' . $e->getMessage());
            $errors[] = 'Failed to reverse transaction: ' . $e->getMessage();
        }
    }
}

$party_name = $tx['account_type'] === 'customer' ? $tx['customer_name'] : $tx['supplier_name'];
$page_title = 'Reverse Transaction #' . htmlspecialchars($tx['transaction_reference']);
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 580px;">
  <div class="page-header">
    <div>
      <h1 class="page-title text-danger">⚠️ Reversal / Correction</h1>
      <p class="page-subtitle">Voucher #<?= htmlspecialchars($tx['transaction_reference']) ?></p>
    </div>
    <a href="<?= BASE_URL ?>transaction-details.php?id=<?= $tx['id'] ?>" class="btn btn-outline btn-sm">
      ← <?= __('cancel') ?>
    </a>
  </div>

  <div class="card">
    <div class="inline-confirmation warning" style="margin-top: 0; margin-bottom: 20px;">
      <span class="confirmation-title">Strict Financial Integrity Notice</span>
      <p class="confirmation-body">
        In accordance with professional accounting principles, financial records in Hisabio cannot be deleted. Reversing this transaction will nullify its effect on the balance and create an immutable audit record.
      </p>
    </div>

    <div class="card" style="background: #FAFBFD; border: 1px solid var(--border); padding: 14px; margin-bottom: 20px;">
      <div class="flex-between">
        <div>
          <div style="font-size: 12px; color: var(--text-muted);">Party:</div>
          <div style="font-weight: 700; font-size: 15px;"><?= htmlspecialchars($party_name) ?></div>
          <div style="font-size: 12.5px; color: var(--text-muted);"><?= format_date($tx['transaction_date']) ?> • <?= htmlspecialchars($tx['description']) ?></div>
        </div>
        <div style="text-align: right;">
          <div style="font-size: 12px; color: var(--text-muted);">Amount:</div>
          <div style="font-size: 20px; font-weight: 800; color: var(--danger);"><?= format_currency($tx['amount']) ?></div>
        </div>
      </div>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="inline-confirmation" style="margin-top: 0; margin-bottom: 16px;">
        <span class="confirmation-title">Please fix the following:</span>
        <ul style="margin-left: 20px; font-size: 13.5px; color: #7F1D1D;">
          <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" action="" data-loading-state>
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label" for="correction_reason">
          <?= __('correction_reason') ?> <span class="required">*</span>
        </label>
        <textarea 
          id="correction_reason" 
          name="correction_reason" 
          class="form-control" 
          placeholder="e.g. Incorrect amount entered by cashier, duplicate entry, or customer returned full goods"
          rows="3"
          required
          autofocus
        ><?= htmlspecialchars($reason) ?></textarea>
        <div class="form-hint">A mandatory audit reason is required before reversing any posted entry.</div>
      </div>

      <div style="display: flex; gap: 10px; margin-top: 24px;">
        <button type="submit" class="btn btn-danger" style="flex: 1;" data-loading-text="Reversing Transaction...">
          ⚠️ Confirm Reversal
        </button>
        <a href="<?= BASE_URL ?>transaction-details.php?id=<?= $tx['id'] ?>" class="btn btn-outline" style="min-width: 100px;">
          <?= __('cancel') ?>
        </a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
