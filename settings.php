<?php
/**
 * Hisabio - Business Settings & Profile
 * PRD Section 5.4: Business configuration, logo upload, receipt footer, currency
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

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $business_name = trim($_POST['business_name'] ?? '');
    $business_category = trim($_POST['business_category'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $receipt_footer = trim($_POST['receipt_footer'] ?? '');
    $language = in_array($_POST['language'] ?? '', ['en', 'ur'], true) ? $_POST['language'] : 'en';

    if (empty($business_name)) {
        $errors[] = 'Business name cannot be empty.';
    }

    // Logo Upload Handling (PRD Section 22.5)
    $logo_path = $biz['logo'];
    if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowed_mime = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
        $file_tmp = $_FILES['logo']['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file_tmp);
        finfo_close($finfo);

        if (!in_array($mime, $allowed_mime)) {
            $errors[] = 'Invalid image type. Please upload JPG, PNG, WEBP, or SVG.';
        } elseif ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Logo image file size must be less than 2MB.';
        } else {
            $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $new_name = 'biz_logo_' . $biz_id . '_' . time() . '.' . $ext;
            $destination = __DIR__ . '/uploads/business/' . $new_name;
            if (move_uploaded_file($file_tmp, $destination)) {
                $logo_path = 'uploads/business/' . $new_name;
            } else {
                $errors[] = 'Failed to save uploaded logo.';
            }
        }
    }

    if (empty($errors)) {
        try {
            $stmtUpdate = $pdo->prepare("
                UPDATE businesses 
                SET business_name = ?, business_category = ?, phone = ?, city = ?, address = ?, receipt_footer = ?, language = ?, logo = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmtUpdate->execute([
                $business_name,
                $business_category,
                $phone,
                $city,
                $address,
                $receipt_footer,
                $language,
                $logo_path,
                $biz_id
            ]);

            $_SESSION['app_lang'] = $language;
            flash_message('success', 'Business settings updated successfully.');
            header('Location: ' . BASE_URL . 'settings.php');
            exit;
        } catch (Exception $e) {
            error_log('Error updating settings: ' . $e->getMessage());
            $errors[] = 'Failed to update settings: ' . $e->getMessage();
        }
    }
}

$page_title = __('settings');
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 720px;">
  <div class="page-header">
    <div>
      <h1 class="page-title">⚙️ Business <?= __('settings') ?></h1>
      <p class="page-subtitle"><?= htmlspecialchars($biz['business_name']) ?> (<?= htmlspecialchars($biz['business_code']) ?>)</p>
    </div>

    <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-outline btn-sm">
      ← <?= __('dashboard') ?>
    </a>
  </div>

  <!-- Settings Navigation Bar -->
  <div class="card mb-3 no-print">
    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
      <a href="<?= BASE_URL ?>settings.php" class="btn btn-primary btn-sm">Business Info</a>
      <a href="<?= BASE_URL ?>appearance.php" class="btn btn-outline btn-sm">Language & Direction</a>
      <a href="<?= BASE_URL ?>security.php" class="btn btn-outline btn-sm">Security & Passwords</a>
      <a href="<?= BASE_URL ?>users.php" class="btn btn-outline btn-sm">Users & Staff</a>
      <a href="<?= BASE_URL ?>roles.php" class="btn btn-outline btn-sm">Roles & Permissions</a>
      <a href="<?= BASE_URL ?>backup.php" class="btn btn-outline btn-sm">Backup Database</a>
    </div>
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

    <form method="POST" action="" enctype="multipart/form-data" data-loading-state>
      <?= csrf_field() ?>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="business_name">
            Business / Shop Name <span class="required">*</span>
          </label>
          <input 
            type="text" 
            id="business_name" 
            name="business_name" 
            class="form-control" 
            value="<?= htmlspecialchars($biz['business_name']) ?>" 
            required
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="business_category">
            Category
          </label>
          <input 
            type="text" 
            id="business_category" 
            name="business_category" 
            class="form-control" 
            value="<?= htmlspecialchars($biz['business_category'] ?? 'General Store') ?>"
          >
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="phone">
            Official Shop Phone / WhatsApp
          </label>
          <input 
            type="tel" 
            id="phone" 
            name="phone" 
            class="form-control" 
            value="<?= htmlspecialchars($biz['phone'] ?? '') ?>"
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="city">
            City
          </label>
          <input 
            type="text" 
            id="city" 
            name="city" 
            class="form-control" 
            value="<?= htmlspecialchars($biz['city'] ?? 'Lahore') ?>"
          >
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="address">
          Shop / Warehouse Address
        </label>
        <textarea 
          id="address" 
          name="address" 
          class="form-control" 
          rows="2"
        ><?= htmlspecialchars($biz['address'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label" for="receipt_footer">
          Receipt Footer Message
        </label>
        <textarea 
          id="receipt_footer" 
          name="receipt_footer" 
          class="form-control" 
          rows="2"
        ><?= htmlspecialchars($biz['receipt_footer'] ?? 'Shukriya! JazakAllah khair for your business. Har Hisab Saaf, Har Khata Aasaan.') ?></textarea>
        <div class="form-hint">Appears at the bottom of printed vouchers and receipts</div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="language">
            Default Language
          </label>
          <select id="language" name="language" class="form-control">
            <option value="en" <?= ($biz['language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
            <option value="ur" <?= ($biz['language'] ?? 'en') === 'ur' ? 'selected' : '' ?>>اردو (Urdu)</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="logo">
            Shop Logo (Optional)
          </label>
          <input 
            type="file" 
            id="logo" 
            name="logo" 
            class="form-control" 
            accept="image/*"
          >
          <?php if (!empty($biz['logo'])): ?>
            <div class="form-hint">Current logo: <code><?= htmlspecialchars($biz['logo']) ?></code></div>
          <?php endif; ?>
        </div>
      </div>

      <div style="display: flex; gap: 10px; margin-top: 20px;">
        <button type="submit" class="btn btn-primary" style="flex: 1;" data-loading-text="Saving Settings...">
          💾 <?= __('save') ?> Settings
        </button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
