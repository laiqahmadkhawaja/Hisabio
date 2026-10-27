<?php
/**
 * Hisabio - Receive Payment (Raqam Wasool Hui)
 * PRD Section 9.4: Immutable payment record, payment methods, receipt, WhatsApp share
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

$preselected_customer_id = (int)($_GET['customer_id'] ?? 0);

// Fetch all active customers for dropdown
$stmtCust = $pdo->prepare("
    SELECT c.id, c.name, c.mobile, c.customer_code, a.id AS account_id 
    FROM customers c
    JOIN accounts a ON a.customer_id = c.id
    WHERE c.business_id = ? AND c.status = 'active'
    ORDER BY c.name ASC
");
$stmtCust->execute([$biz_id]);
$customers = $stmtCust->fetchAll();

// Pre-calculate balances for each customer
$cust_balances = [];
foreach ($customers as $c) {
    $cust_balances[$c['id']] = calculate_customer_balance((int)$c['account_id'], $pdo);
}

// Fetch Payment Methods
$stmtMethods = $pdo->prepare("SELECT name FROM payment_methods WHERE (business_id IS NULL OR business_id = ?) AND status = 'active'");
$stmtMethods->execute([$biz_id]);
$payment_methods = $stmtMethods->fetchAll(PDO::FETCH_COLUMN) ?: ['Cash', 'Bank Transfer', 'Easypaisa', 'JazzCash', 'Other'];

$default_ref = generate_reference('RCT');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $tx_date = trim($_POST['transaction_date'] ?? date('Y-m-d'));
    $payment_method = trim($_POST['payment_method'] ?? 'Cash');
    $description = trim($_POST['description'] ?? '');
    $reference_number = trim($_POST['reference_number'] ?? '');
    $tx_ref = trim($_POST['transaction_reference'] ?? $default_ref);

    if ($customer_id <= 0) {
        $errors[] = 'Please select a customer.';
    }
    if ($amount <= 0) {
        $errors[] = 'Please enter a valid payment amount greater than zero.';
    }
    if (empty($tx_date)) {
        $errors[] = 'Please select payment date.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmtAcc = $pdo->prepare("SELECT id FROM accounts WHERE customer_id = ? AND business_id = ?");
            $stmtAcc->execute([$customer_id, $biz_id]);
            $account_id = (int)$stmtAcc->fetchColumn();

            if (!$account_id) {
                throw new Exception('Customer ledger account not found.');
            }

            // Insert Payment
            $stmtInsert = $pdo->prepare("
                INSERT INTO transactions 
                (business_id, transaction_reference, account_id, transaction_type, transaction_date, amount, payment_method, description, reference_number, status, created_by, created_at)
                VALUES (?, ?, ?, 'payment_received', ?, ?, ?, ?, ?, 'completed', ?, NOW())
            ");
            $stmtInsert->execute([
                $biz_id,
                $tx_ref,
                $account_id,
                $tx_date,
                $amount,
                $payment_method,
                $description ?: 'Payment received in cash/digital',
                $reference_number ?: null,
                $_SESSION['user_id']
            ]);
            $tx_id = (int)$pdo->lastInsertId();

            create_audit_log($biz_id, $tx_id, 'create_payment_received', null, ['amount' => $amount, 'customer_id' => $customer_id, 'method' => $payment_method], 'Recorded payment received', $_SESSION['user_id']);

            $pdo->commit();

            flash_message('success', 'Payment of ' . format_currency($amount) . ' received and saved to khata!');
            // Redirect to transaction details to display the receipt and WhatsApp sharing options
            header('Location: ' . BASE_URL . 'transaction-details.php?id=' . $tx_id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Error saving payment: ' . $e->getMessage());
            $errors[] = 'Failed to save payment: ' . $e->getMessage();
        }
    }
}

$page_title = __('receive_payment');
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 600px;">
  <div class="page-header">
    <div>
      <h1 class="page-title text-payment">📥 <?= __('receive_payment') ?></h1>
      <p class="page-subtitle">Record cash or digital payment received from a customer</p>
    </div>
    <a href="<?= BASE_URL ?>customers.php" class="btn btn-outline btn-sm">
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
        <label class="form-label" for="customer_id">
          Select Customer <span class="required">*</span>
        </label>
        <select id="customer_id" name="customer_id" class="form-control" required autofocus>
          <option value="">-- Choose Customer --</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $c['id'] == $preselected_customer_id ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['mobile']) ?>) — Current: <?= format_currency($cust_balances[$c['id']]) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label" for="amount">
          Amount Received (PKR) <span class="required">*</span>
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
          <span style="font-size: 12px; color: var(--text-muted); display: block;">Projected New Balance:</span>
          <span id="projected-balance" class="font-bold text-payment" style="font-size: 14px;">Select customer and enter amount</span>
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
          Receipt / Bank / Slip Reference (Optional)
        </label>
        <input 
          type="text" 
          id="reference_number" 
          name="reference_number" 
          class="form-control" 
          placeholder="e.g. Easypaisa TID #390184 or Bank Trx #"
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
          placeholder="e.g. Cleared last week dues"
        >
      </div>

      <div style="display: flex; gap: 10px; margin-top: 20px;">
        <button type="submit" class="btn btn-success" style="flex: 1;" data-loading-text="Receiving Payment...">
          💾 Record Payment & Issue Receipt
        </button>
        <a href="<?= BASE_URL ?>customers.php" class="btn btn-outline" style="min-width: 100px;">
          <?= __('cancel') ?>
        </a>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const balances = <?= json_encode($cust_balances) ?>;
  const selectEl = document.getElementById('customer_id');

  function updateCalculator() {
    const custId = selectEl.value;
    const currentBal = balances[custId] !== undefined ? balances[custId] : 0;
    HisabioLedger.initCalculator(currentBal, true, 'payment');
  }

  selectEl.addEventListener('change', updateCalculator);
  updateCalculator();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
