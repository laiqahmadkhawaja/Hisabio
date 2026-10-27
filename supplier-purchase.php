<?php
/**
 * Hisabio - Record Supplier Purchase (Credit Purchase)
 * PRD Section 10: Track purchases on credit, live payable calculator, dedicated page
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

$default_ref = generate_reference('PUR');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $tx_date = trim($_POST['transaction_date'] ?? date('Y-m-d'));
    $description = trim($_POST['description'] ?? '');
    $reference_number = trim($_POST['reference_number'] ?? '');
    $tx_ref = trim($_POST['transaction_reference'] ?? $default_ref);

    if ($supplier_id <= 0) {
        $errors[] = 'Please select a supplier.';
    }
    if ($amount <= 0) {
        $errors[] = 'Please enter a valid purchase amount.';
    }
    if (empty($tx_date)) {
        $errors[] = 'Please select transaction date.';
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
                VALUES (?, ?, ?, 'supplier_purchase', ?, ?, 'Credit', ?, ?, 'completed', ?, NOW())
            ");
            $stmtInsert->execute([
                $biz_id,
                $tx_ref,
                $account_id,
                $tx_date,
                $amount,
                $description ?: 'Credit Purchase from Supplier',
                $reference_number ?: null,
                $_SESSION['user_id']
            ]);
            $tx_id = (int)$pdo->lastInsertId();

            create_audit_log($biz_id, $tx_id, 'create_supplier_purchase', null, ['amount' => $amount, 'supplier_id' => $supplier_id], 'Recorded credit purchase', $_SESSION['user_id']);

            $pdo->commit();

            flash_message('success', 'Credit purchase of ' . format_currency($amount) . ' recorded successfully.');
            header('Location: ' . BASE_URL . 'supplier-ledger.php?id=' . $supplier_id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Error recording purchase: ' . $e->getMessage());
            $errors[] = 'Failed to save purchase: ' . $e->getMessage();
        }
    }
}

$page_title = __('record_purchase');
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 600px;">
  <div class="page-header">
    <div>
      <h1 class="page-title text-credit">📦 <?= __('record_purchase') ?></h1>
      <p class="page-subtitle">Record stock/goods bought on credit from a supplier</p>
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
        <div class="form-hint">
          Need a new supplier? <a href="<?= BASE_URL ?>supplier-add.php">+ Add New Supplier First</a>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="amount">
          Purchase Amount (PKR) <span class="required">*</span>
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
          <span style="font-size: 12px; color: var(--text-muted); display: block;">Projected Payable Balance:</span>
          <span id="projected-balance" class="font-bold text-credit" style="font-size: 14px;">Select supplier and enter amount</span>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="transaction_date">
            Purchase Date <span class="required">*</span>
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
          <label class="form-label" for="reference_number">
            Supplier Bill / Delivery Challan #
          </label>
          <input 
            type="text" 
            id="reference_number" 
            name="reference_number" 
            class="form-control" 
            placeholder="e.g. INV-99042"
          >
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="description">
          Items Restocked / Notes
        </label>
        <textarea 
          id="description" 
          name="description" 
          class="form-control" 
          placeholder="e.g. 10 cartons cooking oil, 5 sacks sugar, beverages crates"
          rows="2"
        ></textarea>
      </div>

      <div style="display: flex; gap: 10px; margin-top: 20px;">
        <button type="submit" class="btn btn-primary" style="flex: 1; background: var(--credit); border-color: var(--credit);" data-loading-text="Recording Purchase...">
          💾 Save Credit Purchase
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
    HisabioLedger.initCalculator(currentBal, false, 'purchase');
  }

  selectEl.addEventListener('change', updateCalculator);
  updateCalculator();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
