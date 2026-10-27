<?php
/**
 * Hisabio - Add Supplier (Dedicated Page, No Modal)
 * PRD Section 10.1: Supplier records, opening payables, auditable ledger entry
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

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM suppliers WHERE business_id = ?");
$stmtCount->execute([$biz_id]);
$next_num = ((int)$stmtCount->fetchColumn()) + 1;
$default_code = sprintf('SUPP-%03d', $next_num);

$errors = [];
$form = [
    'name' => '',
    'mobile' => '',
    'alternate_contact' => '',
    'address' => '',
    'supplier_code' => $default_code,
    'opening_balance' => '0.00',
    'opening_balance_direction' => 'payable', // 'payable' (business owes supplier) or 'receivable'
    'notes' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $form['name'] = trim($_POST['name'] ?? '');
    $form['mobile'] = trim($_POST['mobile'] ?? '');
    $form['alternate_contact'] = trim($_POST['alternate_contact'] ?? '');
    $form['address'] = trim($_POST['address'] ?? '');
    $form['supplier_code'] = trim($_POST['supplier_code'] ?? $default_code);
    $form['opening_balance'] = trim($_POST['opening_balance'] ?? '0.00');
    $form['opening_balance_direction'] = in_array($_POST['opening_balance_direction'] ?? '', ['payable', 'receivable', 'zero'], true) ? $_POST['opening_balance_direction'] : 'payable';
    $form['notes'] = trim($_POST['notes'] ?? '');

    if (empty($form['name'])) {
        $errors[] = 'Supplier name is required.';
    }
    if (empty($form['mobile'])) {
        $errors[] = 'Mobile number is required.';
    }
    $op_balance = (float)$form['opening_balance'];
    if ($op_balance < 0) {
        $errors[] = 'Opening balance must be zero or a positive amount.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO suppliers 
                (business_id, supplier_code, name, mobile, alternate_contact, address, notes, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
            ");
            $stmt->execute([
                $biz_id,
                $form['supplier_code'],
                $form['name'],
                $form['mobile'],
                !empty($form['alternate_contact']) ? $form['alternate_contact'] : null,
                !empty($form['address']) ? $form['address'] : null,
                !empty($form['notes']) ? $form['notes'] : null,
                $_SESSION['user_id']
            ]);
            $supplier_id = (int)$pdo->lastInsertId();

            $direction = $op_balance > 0 ? $form['opening_balance_direction'] : 'zero';
            $stmtAcc = $pdo->prepare("
                INSERT INTO accounts 
                (business_id, account_type, customer_id, supplier_id, opening_balance, opening_balance_direction, created_at)
                VALUES (?, 'supplier', NULL, ?, ?, ?, NOW())
            ");
            $stmtAcc->execute([$biz_id, $supplier_id, $op_balance, $direction]);
            $account_id = (int)$pdo->lastInsertId();

            if ($op_balance > 0) {
                $ref = generate_reference('SOPB');
                $desc = $direction === 'payable' ? 'Opening payable (Business owes supplier)' : 'Opening balance (Advance paid to supplier)';
                $stmtTx = $pdo->prepare("
                    INSERT INTO transactions 
                    (business_id, transaction_reference, account_id, transaction_type, transaction_date, amount, payment_method, description, status, created_by, created_at)
                    VALUES (?, ?, ?, 'opening_balance', CURRENT_DATE, ?, 'Cash', ?, 'completed', ?, NOW())
                ");
                $stmtTx->execute([$biz_id, $ref, $account_id, $op_balance, $desc, $_SESSION['user_id']]);
            }

            $pdo->commit();
            flash_message('success', "Supplier '{$form['name']}' added to your records!");
            header('Location: ' . BASE_URL . 'supplier-ledger.php?id=' . $supplier_id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Error adding supplier: ' . $e->getMessage());
            $errors[] = 'Failed to save supplier. Supplier code must be unique.';
        }
    }
}

$page_title = __('add_supplier');
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 640px;">
  <div class="page-header">
    <div>
      <h1 class="page-title">🏢 <?= __('add_supplier') ?></h1>
      <p class="page-subtitle">Register a supplier, distributor, or wholesaler</p>
    </div>
    <a href="<?= BASE_URL ?>suppliers.php" class="btn btn-outline btn-sm">
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
            <?= __('supplier_name') ?> / Company <span class="required">*</span>
          </label>
          <input 
            type="text" 
            id="name" 
            name="name" 
            class="form-control" 
            placeholder="e.g. Shan Foods Depot or Lahore Beverage Hub" 
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
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="supplier_code">Supplier Code</label>
          <input 
            type="text" 
            id="supplier_code" 
            name="supplier_code" 
            class="form-control" 
            value="<?= htmlspecialchars($form['supplier_code']) ?>"
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="alternate_contact">Sales Rep / Alternate Contact</label>
          <input 
            type="text" 
            id="alternate_contact" 
            name="alternate_contact" 
            class="form-control" 
            placeholder="e.g. Kamran (Area Manager)" 
            value="<?= htmlspecialchars($form['alternate_contact']) ?>"
          >
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="address">Warehouse / Office Address</label>
        <textarea 
          id="address" 
          name="address" 
          class="form-control" 
          placeholder="Market, Wholesale Area, City"
          rows="2"
        ><?= htmlspecialchars($form['address']) ?></textarea>
      </div>

      <!-- Opening Payable Section -->
      <div class="card" style="background: #F9FAFB; border: 1.5px dashed var(--border); padding: 14px; margin-bottom: 16px;">
        <h3 style="font-size: 14px; font-weight: 700; color: var(--text); margin-bottom: 10px;">
          ⚖️ Opening Balance
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
              <option value="payable" <?= $form['opening_balance_direction'] === 'payable' ? 'selected' : '' ?>>
                You owe supplier (Aap ne dene hain)
              </option>
              <option value="receivable" <?= $form['opening_balance_direction'] === 'receivable' ? 'selected' : '' ?>>
                Supplier owes you (Advance Paid)
              </option>
            </select>
          </div>
        </div>
        <div class="form-hint mt-2">Leave 0 if starting with a clear account.</div>
      </div>

      <div class="form-group">
        <label class="form-label" for="notes"><?= __('notes') ?></label>
        <input 
          type="text" 
          id="notes" 
          name="notes" 
          class="form-control" 
          placeholder="Delivery terms, order cycle, product categories"
          value="<?= htmlspecialchars($form['notes']) ?>"
        >
      </div>

      <div style="display: flex; gap: 10px; margin-top: 20px;">
        <button type="submit" class="btn btn-primary" style="flex: 1;" data-loading-text="Adding Supplier...">
          💾 <?= __('save') ?> Supplier
        </button>
        <a href="<?= BASE_URL ?>suppliers.php" class="btn btn-outline" style="min-width: 100px;">
          <?= __('cancel') ?>
        </a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
