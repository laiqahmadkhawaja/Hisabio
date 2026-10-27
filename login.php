<?php
/**
 * Hisabio - User Login
 * PRD Section 5.2: Rate limiting, generic failure message, session regeneration
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$error = '';
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error = 'Please enter your mobile number or email and password.';
    } elseif (is_rate_limited($identifier)) {
        $error = 'Too many failed login attempts. Please wait 15 minutes before trying again.';
    } else {
        $pdo = get_db();
        $stmt = $pdo->prepare("SELECT id, password_hash, status FROM users WHERE mobile = ? OR email = ?");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if ($user && $user['status'] === 'active' && password_verify($password, $user['password_hash'])) {
            // Find active business association
            $stmtBiz = $pdo->prepare("SELECT business_id FROM business_users WHERE user_id = ? AND status = 'active' LIMIT 1");
            $stmtBiz->execute([$user['id']]);
            $bizId = $stmtBiz->fetchColumn();

            if ($bizId) {
                record_login_attempt($identifier, 'success');
                login_user((int)$user['id'], (int)$bizId);
                flash_message('success', 'Assalam-o-Alaikum! Welcome back to Hisabio.');
                $redirect = $_GET['redirect'] ?? BASE_URL . 'dashboard.php';
                header('Location: ' . $redirect);
                exit;
            } else {
                $error = 'No active business account associated with this user.';
            }
        } else {
            record_login_attempt($identifier, 'failure');
            $error = 'Invalid login credentials. Please check and try again.';
        }
    }
}

$page_title = __('login');
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 440px; margin: 30px auto;">
  <div class="card">
    <div style="text-align: center; margin-bottom: 20px;">
      <img src="<?= BASE_URL ?>assets/images/logo.svg" alt="Hisabio Logo" style="height: 48px; width: auto; margin-bottom: 8px;">
      <h2 style="font-size: 20px; font-weight: 700; color: var(--text);"><?= __('login') ?></h2>
      <p style="font-size: 13px; color: var(--text-muted);"><?= __('tagline') ?></p>
    </div>

    <?php if ($error): ?>
      <div class="inline-confirmation" style="margin-top: 0; margin-bottom: 16px;">
        <span class="confirmation-body"><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" action="" data-loading-state>
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label" for="identifier">
          <?= __('phone') ?> / Email <span class="required">*</span>
        </label>
        <input 
          type="text" 
          id="identifier" 
          name="identifier" 
          class="form-control" 
          placeholder="e.g. 03001234567 or email" 
          value="<?= htmlspecialchars($identifier) ?>" 
          required 
          autocomplete="username"
          autofocus
        >
        <div class="form-hint">Pakistani mobile number or registered email address</div>
      </div>

      <div class="form-group">
        <div class="flex-between mb-1">
          <label class="form-label" for="password" style="margin-bottom: 0;">
            Password <span class="required">*</span>
          </label>
          <a href="<?= BASE_URL ?>forgot-password.php" style="font-size: 12px; font-weight: 500;">
            <?= __('forgot_password') ?>
          </a>
        </div>
        <input 
          type="password" 
          id="password" 
          name="password" 
          class="form-control" 
          placeholder="••••••••" 
          required 
          autocomplete="current-password"
        >
      </div>

      <button type="submit" class="btn btn-primary btn-block" data-loading-text="Signing in..." style="margin-top: 10px;">
        <?= __('login') ?>
      </button>
    </form>

    <div style="text-align: center; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border); font-size: 13.5px; color: var(--text-muted);">
      Don't have an account? <a href="<?= BASE_URL ?>register.php" style="font-weight: 600;"><?= __('register') ?></a>
    </div>
  </div>

  <!-- Quick Demo Credentials Box -->
  <div class="card" style="background: #F9FAFB; border-style: dashed; padding: 12px 16px;">
    <div style="font-size: 12px; color: var(--text-muted);">
      <strong>Demo Credentials:</strong><br>
      Mobile: <code>03001234567</code><br>
      Password: <code>admin123</code>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
