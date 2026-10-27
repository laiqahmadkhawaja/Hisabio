<?php
/**
 * Hisabio - Edit Supplier (Dedicated Page, No Modal)
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

$stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ? AND business_id = ?");
$stmt->execute([$supplier_id, $biz_id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    flash_message('error', 'Supplier not found.');
    header('Location: ' . BASE_URL . 'suppliers.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $name = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $alternate_contact = trim($_POST['alternate_contact'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['active', 'archived']) ? $_POST['status'] : 'active';

    if (empty($name)) {
        $errors[] = 'Supplier name is required.';
    }
    if (empty($mobile)) {
        $errors[] = 'Mobile number is required.';
    }

    if (empty($errors)) {
        try {
            $stmtUpdate = $pdo->prepare("
                UPDATE suppliers 
                SET name = ?, mobile = ?, alternate_contact = ?, address = ?, notes = ?, status = ?,
                    archived_at = CASE WHEN ? = 'archived' AND status != 'archived' THEN NOW() ELSE archived_at END
                WHERE id = ? AND business_id = ?
            ");
            $stmtUpdate->execute([
                $name,
                $mobile,
                !empty($alternate_contact) ? $alternate_contact : null,
                !empty($address) ? $address : null,
                !empty($notes) ? $notes : null,
                $status,
                $status,
                $supplier_id,
                $biz_id
            ]);

            flash_message('success', 'Supplier details updated successfully.');
            header('Location: ' . BASE_URL . 'supplier-profile.php?id=' . $supplier_id);
            exit;
        } catch (Exception $e) {
            error_log('Error updating supplier: ' . $e->getMessage());
            $errors[] = 'Failed to update supplier details.';
        }
    }
}

$page_title = 'Edit ' . htmlspecialchars($supplier['name']);
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 600px;">
  <div class="page-header">
    <div>
      <h1 class="page-title">✏️ Edit Supplier</h1>
      <p class="page-subtitle"><?= htmlspecialchars($supplier['name']) ?> (<?= htmlspecialchars($supplier['supplier_code']) ?>)</p>
    </div>
    <a href="<?= BASE_URL ?>supplier-profile.php?id=<?= $supplier['id'] ?>" class="btn btn-outline btn-sm">
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
            <?= __('supplier_name') ?> <span class="required">*</span>
          </label>
          <input 
            type="text" 
            id="name" 
            name="name" 
            class="form-control" 
            value="<?= htmlspecialchars($supplier['name']) ?>" 
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
            value="<?= htmlspecialchars($supplier['mobile']) ?>" 
            required
          >
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="alternate_contact">Sales Rep / Alternate Contact</label>
          <input 
            type="text" 
            id="alternate_contact" 
            name="alternate_contact" 
            class="form-control" 
            value="<?= htmlspecialchars($supplier['alternate_contact'] ?? '') ?>"
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="status">Account Status</label>
          <select id="status" name="status" class="form-control">
            <option value="active" <?= $supplier['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="archived" <?= $supplier['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="address">Address</label>
        <textarea id="address" name="address" class="form-control" rows="2"><?= htmlspecialchars($supplier['address'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label" for="notes"><?= __('notes') ?></label>
        <input type="text" id="notes" name="notes" class="form-control" value="<?= htmlspecialchars($supplier['notes'] ?? '') ?>">
      </div>

      <div style="display: flex; gap: 10px; margin-top: 20px;">
        <button type="submit" class="btn btn-primary" style="flex: 1;" data-loading-text="Saving Changes...">
          💾 <?= __('save') ?> Changes
        </button>
        <a href="<?= BASE_URL ?>supplier-profile.php?id=<?= $supplier['id'] ?>" class="btn btn-outline" style="min-width: 100px;">
          <?= __('cancel') ?>
        </a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
