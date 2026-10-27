<?php
/**
 * Hisabio - User Profile Management
 * PRD Section 26: User profile & password settings
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$user = current_user();
$user_id = (int)$user['id'];
$pdo = get_db();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $current_password = $_POST['current_password'] ?? '';

    if (empty($full_name)) {
        $errors[] = 'Full name is required.';
    }
    if (empty($mobile)) {
        $errors[] = 'Mobile number is required.';
    }

    // Check mobile uniqueness if changed
    if ($mobile !== $user['mobile']) {
        $stmtChk = $pdo->prepare("SELECT id FROM users WHERE mobile = ? AND id != ?");
        $stmtChk->execute([$mobile, $user_id]);
        if ($stmtChk->fetch()) {
            $errors[] = 'This mobile number is already in use by another user.';
        }
    }

    // Password change check
    $update_pass = false;
    if (!empty($new_password)) {
        if (strlen($new_password) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } else {
            // Verify current password
            $stmtPass = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmtPass->execute([$user_id]);
            $current_hash = $stmtPass->fetchColumn();
            if (!password_verify($current_password, $current_hash)) {
                $errors[] = 'Current password is incorrect. Cannot update password.';
            } else {
                $update_pass = true;
            }
        }
    }

    if (empty($errors)) {
        try {
            if ($update_pass) {
                $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                $stmtUp = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, mobile = ?, password_hash = ? WHERE id = ?");
                $stmtUp->execute([$full_name, !empty($email) ? $email : null, $mobile, $new_hash, $user_id]);
            } else {
                $stmtUp = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, mobile = ? WHERE id = ?");
                $stmtUp->execute([$full_name, !empty($email) ? $email : null, $mobile, $user_id]);
            }

            flash_message('success', 'Profile updated successfully.');
            header('Location: ' . BASE_URL . 'profile.php');
            exit;
        } catch (Exception $e) {
            error_log('Profile update error: ' . $e->getMessage());
            $errors[] = 'Failed to update profile: ' . $e->getMessage();
        }
    }
}

$page_title = __('profile');
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 580px;">
  <div class="page-header">
    <div>
      <h1 class="page-title">👤 <?= __('profile') ?></h1>
      <p class="page-subtitle">Manage your personal credentials</p>
    </div>
    <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-outline btn-sm">
      ← <?= __('dashboard') ?>
    </a>
  </div>

  <div class="card">
    <?php if (!empty($errors)): ?>
      <div class="inline-confirmation" style="margin-top: 0; margin-bottom: 16px;">
        <span class="confirmation-title">Please correct:</span>
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
        <label class="form-label" for="full_name">Full Name <span class="required">*</span></label>
        <input type="text" id="full_name" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="mobile">Mobile Number <span class="required">*</span></label>
          <input type="tel" id="mobile" name="mobile" class="form-control" value="<?= htmlspecialchars($user['mobile']) ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="email">Email Address</label>
          <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
        </div>
      </div>

      <!-- Password Change Section -->
      <div class="card mt-3" style="background: #F9FAFB; border: 1px dashed var(--border); padding: 14px;">
        <h3 style="font-size: 14px; font-weight: 700; color: var(--text); margin-bottom: 8px;">
          🔑 Change Password (Optional)
        </h3>
        <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">
          Leave blank if you do not wish to change your current password.
        </p>

        <div class="form-group">
          <label class="form-label" for="current_password">Current Password</label>
          <input type="password" id="current_password" name="current_password" class="form-control" placeholder="Required only if changing password">
        </div>

        <div class="form-group mb-0">
          <label class="form-label" for="new_password">New Password</label>
          <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Min. 6 characters">
        </div>
      </div>

      <div style="display: flex; gap: 10px; margin-top: 20px;">
        <button type="submit" class="btn btn-primary" style="flex: 1;" data-loading-text="Saving Profile...">
          💾 <?= __('save') ?> Profile
        </button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
