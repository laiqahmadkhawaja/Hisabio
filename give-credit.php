<?php
/**
 * Hisabio - Give Credit (Udhaar Diya)
 * PRD Section 9.3: Dedicated page, fast entry, accurate validation, live balance calculator
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

// Pre-calculate balances for each customer to power client preview
$cust_balances = [];
$current_selected_balance = 0.00;
foreach ($customers as $c) {
    $bal = calculate_customer_balance((int)$c['account_id'], $pdo);
    $cust_balances[$c['id']] = $bal;
    if ($c['id'] == $preselected_customer_id) {
        $current_selected_balance = $bal;
    }
}

$default_ref = generate_reference('CRD');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $tx_date = trim($_POST['transaction_date'] ?? date('Y-m-d'));
    $due_date = !empty($_POST['due_date']) ? trim($_POST['due_date']) : null;
    $description = trim($_POST['description'] ?? '');
    $reference_number = trim($_POST['reference_number'] ?? '');
    $tx_ref = trim($_POST['transaction_reference'] ?? $default_ref);

    // Validation
    if ($customer_id <= 0) {
        $errors[] = 'Please select a customer.';
    }
    if ($amount <= 0) {
        $errors[] = 'Please enter a valid credit amount greater than zero.';
    }
    if (empty($tx_date)) {
        $errors[] = 'Please select a transaction date.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Find customer account
            $stmtAcc = $pdo->prepare("SELECT id FROM accounts WHERE customer_id = ? AND business_id = ?");
            $stmtAcc->execute([$customer_id, $biz_id]);
            $account_id = (int)$stmtAcc->fetchColumn();

            if (!$account_id) {
                throw new Exception('Customer ledger account not found.');
            }

            // Insert transaction
            $stmtInsert = $pdo->prepare("
                INSERT INTO transactions 
                (business_id, transaction_reference, account_id, transaction_type, transaction_date, amount, payment_method, description, due_date, reference_number, status, created_by, created_at)
                VALUES (?, ?, ?, 'credit_sale', ?, ?, 'Cash', ?, ?, ?, 'completed', ?, NOW())
            ");
            $stmtInsert->execute([
                $biz_id,
                $tx_ref,
                $account_id,
                $tx_date,
                $amount,
                $description ?: 'Udhaar Sale',
                $due_date,
                $reference_number ?: null,
                $_SESSION['user_id']
            ]);
            $tx_id = (int)$pdo->lastInsertId();

            // Audit Log
            create_audit_log($biz_id, $tx_id, 'create_credit_sale', null, ['amount' => $amount, 'customer_id' => $customer_id], 'Recorded credit sale', $_SESSION['user_id']);

            $pdo->commit();

            flash_message('success', 'Credit sale (Udhaar) of ' . format_currency($amount) . ' recorded successfully.');
            header('Location: ' . BASE_URL . 'customer-ledger.php?id=' . $customer_id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Error saving credit sale: ' . $e->getMessage());
            $errors[] = 'Failed to save transaction: ' . $e->getMessage();
        }
    }
}

$page_title = __('give_credit');
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 600px;">
  <div class="page-header">
    <div>
      <h1 class="page-title text-credit">📤 <?= __('give_credit') ?></h1>
      <p class="page-subtitle">Record udhaar given to a customer</p>
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

      <!-- Customer Selector -->
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
        <div class="form-hint">
          Don't see the customer? <a href="<?= BASE_URL ?>customer-add.php">+ Add New Customer First</a>
        </div>
      </div>

      <!-- Amount Input with Live Projected Balance Display -->
      <div class="form-group">
        <label class="form-label" for="amount">
          Udhaar Amount (PKR) <span class="required">*</span>
        </label>
        <input 
          type="number" 
          step="0.01" 
          id="amount" 
          name="amount" 
          class="form-control" 
          placeholder="0.00" 
          style="font-size: 22px; font-weight: 700; color: var(--credit);"
          required
        >
        <!-- Dynamic Live Balance Preview Box -->
        <div class="card mt-2 mb-0" style="background: #FFFBEB; border-color: #FDE68A; padding: 10px 14px;">
          <span style="font-size: 12px; color: var(--text-muted); display: block;">Projected New Balance:</span>
          <span id="projected-balance" class="font-bold text-credit" style="font-size: 14px;">Select customer and enter amount</span>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="transaction_date">
            Transaction Date <span class="required">*</span>
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
          <label class="form-label" for="due_date">
            Payment Due Date <span style="font-weight: normal; color: var(--text-muted);">(Optional)</span>
          </label>
          <input 
            type="date" 
            id="due_date" 
            name="due_date" 
            class="form-control"
            value="<?= date('Y-m-d', strtotime('+15 days')) ?>"
          >
          <div class="form-hint">For overdue tracking</div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="description">
          Items Bought / Description
        </label>
        <textarea 
          id="description" 
          name="description" 
          class="form-control" 
          placeholder="e.g. Flour 20kg, Cooking oil 5L, Spices"
          rows="2"
        ></textarea>
      </div>

      <div class="form-group">
        <label class="form-label" for="reference_number">
          Bill / Slip Number (Optional)
        </label>
        <input 
          type="text" 
          id="reference_number" 
          name="reference_number" 
          class="form-control" 
          placeholder="e.g. INV-1049"
        >
      </div>

      <div style="display: flex; gap: 10px; margin-top: 20px;">
        <button type="submit" class="btn btn-primary" style="flex: 1; background: var(--credit); border-color: var(--credit);" data-loading-text="Recording Udhaar...">
          💾 Save Udhaar Entry
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
    HisabioLedger.initCalculator(currentBal, true, 'credit');
  }

  selectEl.addEventListener('change', updateCalculator);
  updateCalculator();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
