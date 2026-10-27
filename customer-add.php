<?php
/**
 * Hisabio - Add Customer (Dedicated Page, No Modal)
 * PRD Section 8.2: Opening balance classification & auditable ledger entry
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

// Auto-generate customer code
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE business_id = ?");
$stmtCount->execute([$biz_id]);
$next_num = ((int)$stmtCount->fetchColumn()) + 1;
$default_code = sprintf('CUST-%03d', $next_num);

$errors = [];
$form = [
    'name' => '',
    'mobile' => '',
    'alternate_mobile' => '',
    'address' => '',
    'customer_code' => $default_code,
    'opening_balance' => '0.00',
    'opening_balance_direction' => 'receivable', // 'receivable' (customer owes) or 'payable' (business owes)
    'notes' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $form['name'] = trim($_POST['name'] ?? '');
    $form['mobile'] = trim($_POST['mobile'] ?? '');
    $form['alternate_mobile'] = trim($_POST['alternate_mobile'] ?? '');
    $form['address'] = trim($_POST['address'] ?? '');
    $form['customer_code'] = trim($_POST['customer_code'] ?? $default_code);
    $form['opening_balance'] = trim($_POST['opening_balance'] ?? '0.00');
    $form['opening_balance_direction'] = in_array($_POST['opening_balance_direction'] ?? '', ['receivable', 'payable', 'zero'], true) ? $_POST['opening_balance_direction'] : 'receivable';
    $form['notes'] = trim($_POST['notes'] ?? '');

    if (empty($form['name'])) {
        $errors[] = 'Customer name is required.';
    }
    if (empty($form['mobile'])) {
        $errors[] = 'Mobile number is required.';
    }
    $op_balance = (float)$form['opening_balance'];
    if ($op_balance < 0) {
        $errors[] = 'Opening balance must be zero or a positive number. Choose the direction below.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Insert Customer
            $stmt = $pdo->prepare("
                INSERT INTO customers 
                (business_id, customer_code, name, mobile, alternate_mobile, address, notes, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
            ");
            $stmt->execute([
                $biz_id,
                $form['customer_code'],
                $form['name'],
                $form['mobile'],
                !empty($form['alternate_mobile']) ? $form['alternate_mobile'] : null,
                !empty($form['address']) ? $form['address'] : null,
                !empty($form['notes']) ? $form['notes'] : null,
                $_SESSION['user_id']
            ]);
            $customer_id = (int)$pdo->lastInsertId();

            // 2. Create Linked Account
            $direction = $op_balance > 0 ? $form['opening_balance_direction'] : 'zero';
            $stmtAcc = $pdo->prepare("
                INSERT INTO accounts 
                (business_id, account_type, customer_id, supplier_id, opening_balance, opening_balance_direction, created_at)
                VALUES (?, 'customer', ?, NULL, ?, ?, NOW())
            ");
            $stmtAcc->execute([$biz_id, $customer_id, $op_balance, $direction]);
            $account_id = (int)$pdo->lastInsertId();

            // 3. If opening balance > 0, create an auditable initial ledger transaction
            if ($op_balance > 0) {
                $ref = generate_reference('OPB');
                $desc = $direction === 'receivable' ? 'Opening balance (Customer owes business)' : 'Opening balance (Advance / Excess credit)';
                $stmtTx = $pdo->prepare("
                    INSERT INTO transactions 
                    (business_id, transaction_reference, account_id, transaction_type, transaction_date, amount, payment_method, description, status, created_by, created_at)
                    VALUES (?, ?, ?, 'opening_balance', CURRENT_DATE, ?, 'Cash', ?, 'completed', ?, NOW())
                ");
                $stmtTx->execute([$biz_id, $ref, $account_id, $op_balance, $desc, $_SESSION['user_id']]);
            }

            $pdo->commit();
            flash_message('success', "Customer '{$form['name']}' added to your digital khata!");
            header('Location: ' . BASE_URL . 'customer-ledger.php?id=' . $customer_id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Error adding customer: ' . $e->getMessage());
            $errors[] = 'Failed to save customer. Please check customer code uniqueness or try again.';
        }
    }
}

$page_title = __('add_customer');
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 640px;">
  <div class="page-header">
    <div>
      <h1 class="page-title">➕ <?= __('add_customer') ?></h1>
      <p class="page-subtitle">Create a new customer ledger account</p>
    </div>
    <a href="<?= BASE_URL ?>customers.php" class="btn btn-outline btn-sm">
      ← <?= __('back') ?>
    </a>
  </div>

  <div class="card">
    <?php if (!empty($errors)): ?>
      <div class="inline-confirmation" style="margin-top: 0; margin-bottom: 16px;">
        <span class="confirmation-title">Please correct the following:</span>
        <ul style="margin-left: 20px; font-size: 13.5px; color: #7F1D1D;">
          <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" action="" data-loading-state>
      <?= csrf_field() ?>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="name">
            <?= __('customer_name') ?> <span class="required">*</span>
          </label>
          <input 
            type="text" 
            id="name" 
            name="name" 
            class="form-control" 
            placeholder="e.g. Tariq Mehmood" 
            value="<?= htmlspecialchars($form['name']) ?>" 
            required 
            autofocus
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="mobile">
            <?= __('phone') ?> <span class="required">*</span>
          </label>
          <input 
            type="tel" 
            id="mobile" 
            name="mobile" 
            class="form-control" 
            placeholder="03001234567" 
            value="<?= htmlspecialchars($form['mobile']) ?>" 
            required
          >
          <div class="form-hint">Used for 1-tap WhatsApp reminders</div>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="customer_code">Customer Code</label>
          <input 
            type="text" 
            id="customer_code" 
            name="customer_code" 
            class="form-control" 
            value="<?= htmlspecialchars($form['customer_code']) ?>"
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="alternate_mobile">Alternate Phone (Optional)</label>
          <input 
            type="tel" 
            id="alternate_mobile" 
            name="alternate_mobile" 
            class="form-control" 
            placeholder="e.g. 042-35750000" 
            value="<?= htmlspecialchars($form['alternate_mobile']) ?>"
          >
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="address">Shop / Residential Address</label>
        <textarea 
          id="address" 
          name="address" 
          class="form-control" 
          placeholder="House/Shop #, Street, Area, City"
          rows="2"
        ><?= htmlspecialchars($form['address']) ?></textarea>
      </div>

      <!-- Opening Balance Section with Strict Accounting Direction -->
      <div class="card" style="background: #F9FAFB; border: 1.5px dashed var(--border); padding: 14px; margin-bottom: 16px;">
        <h3 style="font-size: 14px; font-weight: 700; color: var(--text); margin-bottom: 10px;">
          ⚖️ Previous Balance (Opening Balance)
        </h3>
        
        <div class="form-row">
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" for="opening_balance">Amount (PKR)</label>
            <input 
              type="number" 
              step="0.01" 
              id="opening_balance" 
              name="opening_balance" 
              class="form-control" 
              value="<?= htmlspecialchars($form['opening_balance']) ?>"
            >
          </div>

          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" for="opening_balance_direction">Balance Direction</label>
            <select id="opening_balance_direction" name="opening_balance_direction" class="form-control">
              <option value="receivable" <?= $form['opening_balance_direction'] === 'receivable' ? 'selected' : '' ?>>
                Customer owes you (Aap ne lene hain)
              </option>
              <option value="payable" <?= $form['opening_balance_direction'] === 'payable' ? 'selected' : '' ?>>
                You owe customer (Aap ne dene hain / Advance)
              </option>
            </select>
          </div>
        </div>
        <div class="form-hint mt-2">Leave 0 if starting fresh with no previous udhaar.</div>
      </div>

      <div class="form-group">
        <label class="form-label" for="notes"><?= __('notes') ?></label>
        <input 
          type="text" 
          id="notes" 
          name="notes" 
          class="form-control" 
          placeholder="Any special remarks, credit terms or credit limit"
          value="<?= htmlspecialchars($form['notes']) ?>"
        >
      </div>

      <div style="display: flex; gap: 10px; margin-top: 20px;">
        <button type="submit" class="btn btn-primary" style="flex: 1;" data-loading-text="Adding Customer...">
          💾 <?= __('save') ?>
        </button>
        <a href="<?= BASE_URL ?>customers.php" class="btn btn-outline" style="min-width: 100px;">
          <?= __('cancel') ?>
        </a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
