<?php
/**
 * Hisabio - Edit Customer Details (Dedicated Page, No Modal)
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

$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ? AND business_id = ?");
$stmt->execute([$customer_id, $biz_id]);
$customer = $stmt->fetch();

if (!$customer) {
    flash_message('error', 'Customer not found.');
    header('Location: ' . BASE_URL . 'customers.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $name = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $alternate_mobile = trim($_POST['alternate_mobile'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['active', 'archived']) ? $_POST['status'] : 'active';

    if (empty($name)) {
        $errors[] = 'Customer name is required.';
    }
    if (empty($mobile)) {
        $errors[] = 'Mobile number is required.';
    }

    if (empty($errors)) {
        try {
            $stmtUpdate = $pdo->prepare("
                UPDATE customers 
                SET name = ?, mobile = ?, alternate_mobile = ?, address = ?, notes = ?, status = ?,
                    archived_at = CASE WHEN ? = 'archived' AND status != 'archived' THEN NOW() ELSE archived_at END
                WHERE id = ? AND business_id = ?
            ");
            $stmtUpdate->execute([
                $name,
                $mobile,
                !empty($alternate_mobile) ? $alternate_mobile : null,
                !empty($address) ? $address : null,
                !empty($notes) ? $notes : null,
                $status,
                $status,
                $customer_id,
                $biz_id
            ]);

            flash_message('success', 'Customer details updated successfully.');
            header('Location: ' . BASE_URL . 'customer-profile.php?id=' . $customer_id);
            exit;
        } catch (Exception $e) {
            error_log('Error updating customer: ' . $e->getMessage());
            $errors[] = 'Failed to update customer details.';
        }
    }
}

$page_title = 'Edit ' . htmlspecialchars($customer['name']);
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 600px;">
  <div class="page-header">
    <div>
      <h1 class="page-title">✏️ Edit Customer</h1>
      <p class="page-subtitle"><?= htmlspecialchars($customer['name']) ?> (<?= htmlspecialchars($customer['customer_code']) ?>)</p>
    </div>
    <a href="<?= BASE_URL ?>customer-profile.php?id=<?= $customer['id'] ?>" class="btn btn-outline btn-sm">
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
            value="<?= htmlspecialchars($customer['name']) ?>" 
            required
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
            value="<?= htmlspecialchars($customer['mobile']) ?>" 
            required
          >
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="alternate_mobile">Alternate Phone</label>
          <input 
            type="tel" 
            id="alternate_mobile" 
            name="alternate_mobile" 
            class="form-control" 
            value="<?= htmlspecialchars($customer['alternate_mobile'] ?? '') ?>"
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="status">Account Status</label>
          <select id="status" name="status" class="form-control">
            <option value="active" <?= $customer['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="archived" <?= $customer['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="address">Address</label>
        <textarea id="address" name="address" class="form-control" rows="2"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label" for="notes"><?= __('notes') ?></label>
        <input type="text" id="notes" name="notes" class="form-control" value="<?= htmlspecialchars($customer['notes'] ?? '') ?>">
      </div>

      <div style="display: flex; gap: 10px; margin-top: 20px;">
        <button type="submit" class="btn btn-primary" style="flex: 1;" data-loading-text="Saving Changes...">
          💾 <?= __('save') ?> Changes
        </button>
        <a href="<?= BASE_URL ?>customer-profile.php?id=<?= $customer['id'] ?>" class="btn btn-outline" style="min-width: 100px;">
          <?= __('cancel') ?>
        </a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
