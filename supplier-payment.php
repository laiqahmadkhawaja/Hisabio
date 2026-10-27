<?php
/**
 * Hisabio - Pay Supplier (Dedicated Page, No Modal)
 * PRD Section 10.3: Record supplier payments, methods, receipt, update balance immediately
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

$preselected_supplier_id = (int)($_GET['supplier_id'] ?? 0);

// Fetch active suppliers
$stmtSupp = $pdo->prepare("
    SELECT s.id, s.name, s.mobile, s.supplier_code, a.id AS account_id 
    FROM suppliers s
    JOIN accounts a ON a.supplier_id = s.id
    WHERE s.business_id = ? AND s.status = 'active'
    ORDER BY s.name ASC
");
$stmtSupp->execute([$biz_id]);
$suppliers = $stmtSupp->fetchAll();

$supp_balances = [];
foreach ($suppliers as $s) {
    $supp_balances[$s['id']] = calculate_supplier_balance((int)$s['account_id'], $pdo);
}

// Payment Methods
$stmtMethods = $pdo->prepare("SELECT name FROM payment_methods WHERE (business_id IS NULL OR business_id = ?) AND status = 'active'");
$stmtMethods->execute([$biz_id]);
$payment_methods = $stmtMethods->fetchAll(PDO::FETCH_COLUMN) ?: ['Cash', 'Bank Transfer', 'Easypaisa', 'JazzCash', 'Other'];

$default_ref = generate_reference('SPAY');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $tx_date = trim($_POST['transaction_date'] ?? date('Y-m-d'));
    $payment_method = trim($_POST['payment_method'] ?? 'Cash');
    $description = trim($_POST['description'] ?? '');
    $reference_number = trim($_POST['reference_number'] ?? '');
    $tx_ref = trim($_POST['transaction_reference'] ?? $default_ref);

    if ($supplier_id <= 0) {
        $errors[] = 'Please select a supplier.';
    }
    if ($amount <= 0) {
        $errors[] = 'Please enter a valid payment amount.';
    }
    if (empty($tx_date)) {
        $errors[] = 'Please select payment date.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmtAcc = $pdo->prepare("SELECT id FROM accounts WHERE supplier_id = ? AND business_id = ?");
            $stmtAcc->execute([$supplier_id, $biz_id]);
            $account_id = (int)$stmtAcc->fetchColumn();

            if (!$account_id) {
                throw new Exception('Supplier account not found.');
            }

            $stmtInsert = $pdo->prepare("
                INSERT INTO transactions 
                (business_id, transaction_reference, account_id, transaction_type, transaction_date, amount, payment_method, description, reference_number, status, created_by, created_at)
                VALUES (?, ?, ?, 'supplier_payment', ?, ?, ?, ?, ?, 'completed', ?, NOW())
            ");
            $stmtInsert->execute([
                $biz_id,
                $tx_ref,
                $account_id,
                $tx_date,
                $amount,
                $payment_method,
                $description ?: 'Payment made to supplier',
                $reference_number ?: null,
                $_SESSION['user_id']
            ]);
            $tx_id = (int)$pdo->lastInsertId();

            create_audit_log($biz_id, $tx_id, 'create_supplier_payment', null, ['amount' => $amount, 'supplier_id' => $supplier_id, 'method' => $payment_method], 'Recorded payment to supplier', $_SESSION['user_id']);

            $pdo->commit();

            flash_message('success', 'Payment of ' . format_currency($amount) . ' to supplier recorded successfully.');
            header('Location: ' . BASE_URL . 'transaction-details.php?id=' . $tx_id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Error saving supplier payment: ' . $e->getMessage());
            $errors[] = 'Failed to save payment: ' . $e->getMessage();
        }
    }
}

$page_title = __('pay_supplier');
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 600px;">
  <div class="page-header">
    <div>
      <h1 class="page-title text-payment">💳 <?= __('pay_supplier') ?></h1>
      <p class="page-subtitle">Record payment voucher to a supplier</p>
    </div>
    <a href="<?= BASE_URL ?>suppliers.php" class="btn btn-outline btn-sm">
      ← <?= __('back') ?>
    </a>
  </div>

  <div class="card">
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
      <input type="hidden" name="transaction_reference" value="<?= htmlspecialchars($default_ref) ?>">

      <div class="form-group">
        <label class="form-label" for="supplier_id">
          Select Supplier <span class="required">*</span>
        </label>
        <select id="supplier_id" name="supplier_id" class="form-control" required autofocus>
          <option value="">-- Choose Supplier --</option>
          <?php foreach ($suppliers as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $s['id'] == $preselected_supplier_id ? 'selected' : '' ?>>
              <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['mobile']) ?>) — Current Payable: <?= format_currency($supp_balances[$s['id']]) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label" for="amount">
          Payment Amount (PKR) <span class="required">*</span>
        </label>
        <input 
          type="number" 
          step="0.01" 
          id="amount" 
          name="amount" 
          class="form-control" 
          placeholder="0.00" 
          style="font-size: 22px; font-weight: 700; color: var(--payment);"
          required
        >
        <!-- Dynamic Live Balance Preview Box -->
        <div class="card mt-2 mb-0" style="background: var(--payment-bg); border-color: #86EFAC; padding: 10px 14px;">
          <span style="font-size: 12px; color: var(--text-muted); display: block;">Projected Remaining Payable:</span>
          <span id="projected-balance" class="font-bold text-payment" style="font-size: 14px;">Select supplier and enter amount</span>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="transaction_date">
            Payment Date <span class="required">*</span>
          </label>
          <input 
            type="date" 
            id="transaction_date" 
            name="transaction_date" 
            class="form-control" 
            value="<?= date('Y-m-d') ?>" 
            required
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="payment_method">
            Payment Method <span class="required">*</span>
          </label>
          <select id="payment_method" name="payment_method" class="form-control">
            <?php foreach ($payment_methods as $method): ?>
              <option value="<?= htmlspecialchars($method) ?>"><?= htmlspecialchars($method) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="reference_number">
          Check / Voucher / Slip Reference
        </label>
        <input 
          type="text" 
          id="reference_number" 
          name="reference_number" 
          class="form-control" 
          placeholder="e.g. Cheque #49019 or Cash Voucher #12"
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="description">
          Notes / Remarks
        </label>
        <input 
          type="text" 
          id="description" 
          name="description" 
          class="form-control" 
          placeholder="e.g. Settled weekly supplies invoice"
        >
      </div>

      <div style="display: flex; gap: 10px; margin-top: 20px;">
        <button type="submit" class="btn btn-success" style="flex: 1;" data-loading-text="Recording Payment...">
          💾 Record Payment Voucher
        </button>
        <a href="<?= BASE_URL ?>suppliers.php" class="btn btn-outline" style="min-width: 100px;">
          <?= __('cancel') ?>
        </a>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const balances = <?= json_encode($supp_balances) ?>;
  const selectEl = document.getElementById('supplier_id');

  function updateCalculator() {
    const suppId = selectEl.value;
    const currentBal = balances[suppId] !== undefined ? balances[suppId] : 0;
    HisabioLedger.initCalculator(currentBal, false, 'payment');
  }

  selectEl.addEventListener('change', updateCalculator);
  updateCalculator();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
