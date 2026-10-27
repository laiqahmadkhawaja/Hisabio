<?php
/**
 * Hisabio - Password Recovery
 * PRD Section 5.3: Expiring, single-use recovery tokens / OTP workflow
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

$message = '';
$error = '';
$step = 'request'; // 'request' or 'reset'
$token = $_GET['token'] ?? '';

$pdo = get_db();

if (!empty($token)) {
    $token_hash = hash('sha256', $token);
    $stmt = $pdo->prepare("SELECT user_id FROM password_reset_tokens WHERE token_hash = ? AND expires_at > NOW() AND used_at IS NULL");
    $stmt->execute([$token_hash]);
    $reset_req = $stmt->fetch();
    if ($reset_req) {
        $step = 'reset';
    } else {
        $error = 'This password reset link is invalid or has expired. Please request a new one.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    if (isset($_POST['action']) && $_POST['action'] === 'request_reset') {
        $identifier = trim($_POST['identifier'] ?? '');
        if (empty($identifier)) {
            $error = 'Please enter your registered mobile number or email.';
        } else {
            $stmt = $pdo->prepare("SELECT id, full_name, mobile, email FROM users WHERE mobile = ? OR email = ?");
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch();

            if ($user) {
                $raw_token = bin2hex(random_bytes(24));
                $token_hash = hash('sha256', $raw_token);
                $expires_at = date('Y-m-d H:i:s', time() + (30 * 60)); // 30 minutes

                $stmtInsert = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
                $stmtInsert->execute([$user['id'], $token_hash, $expires_at]);

                $reset_link = BASE_URL . 'forgot-password.php?token=' . $raw_token;
                $message = "Password reset instructions generated! For local environment/testing, click below to set a new password:<br><a href='{$reset_link}' class='btn btn-sm btn-outline-primary mt-2' style='display:inline-flex;'>Reset Password Now</a>";
            } else {
                // Generic feedback to avoid account enumeration
                $message = 'If an account exists with this mobile or email, you will receive password reset instructions shortly.';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'set_new_password') {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $post_token = $_POST['reset_token'] ?? '';

        if (strlen($new_password) < 6) {
            $error = 'Password must be at least 6 characters.';
            $step = 'reset';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match.';
            $step = 'reset';
        } else {
            $token_hash = hash('sha256', $post_token);
            $stmt = $pdo->prepare("SELECT id, user_id FROM password_reset_tokens WHERE token_hash = ? AND expires_at > NOW() AND used_at IS NULL");
            $stmt->execute([$token_hash]);
            $valid_token = $stmt->fetch();

            if ($valid_token) {
                $hash = password_hash($new_password, PASSWORD_BCRYPT);
                $stmtUpdate = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmtUpdate->execute([$hash, $valid_token['user_id']]);

                $stmtUsed = $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?");
                $stmtUsed->execute([$valid_token['id']]);

                flash_message('success', 'Your password has been reset successfully. Please login.');
                header('Location: ' . BASE_URL . 'login.php');
                exit;
            } else {
                $error = 'Invalid or expired token.';
            }
        }
    }
}

$page_title = __('forgot_password');
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 440px; margin: 30px auto;">
  <div class="card">
    <div style="text-align: center; margin-bottom: 20px;">
      <h2 style="font-size: 20px; font-weight: 700; color: var(--text);"><?= __('forgot_password') ?></h2>
      <p style="font-size: 13px; color: var(--text-muted);">Recover access to your Hisabio account</p>
    </div>

    <?php if ($error): ?>
      <div class="inline-confirmation" style="margin-top: 0; margin-bottom: 16px;">
        <span class="confirmation-body"><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($message): ?>
      <div class="card" style="background: var(--accent-soft); border-color: var(--accent); margin-bottom: 16px;">
        <div style="font-size: 13.5px; color: var(--primary-dark);"><?= $message ?></div>
      </div>
    <?php endif; ?>

    <?php if ($step === 'request'): ?>
      <form method="POST" action="" data-loading-state>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="request_reset">

        <div class="form-group">
          <label class="form-label" for="identifier">
            Registered Mobile Number or Email <span class="required">*</span>
          </label>
          <input 
            type="text" 
            id="identifier" 
            name="identifier" 
            class="form-control" 
            placeholder="e.g. 03001234567" 
            required 
            autofocus
          >
          <div class="form-hint">Enter the phone number or email registered with your shop</div>
        </div>

        <button type="submit" class="btn btn-primary btn-block" data-loading-text="Sending...">
          Send Recovery Link / OTP
        </button>
      </form>
    <?php else: ?>
      <form method="POST" action="" data-loading-state>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="set_new_password">
        <input type="hidden" name="reset_token" value="<?= htmlspecialchars($token) ?>">

        <div class="form-group">
          <label class="form-label" for="new_password">
            New Password <span class="required">*</span>
          </label>
          <input 
            type="password" 
            id="new_password" 
            name="new_password" 
            class="form-control" 
            placeholder="Min. 6 characters" 
            required 
            autofocus
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="confirm_password">
            Confirm New Password <span class="required">*</span>
          </label>
          <input 
            type="password" 
            id="confirm_password" 
            name="confirm_password" 
            class="form-control" 
            placeholder="Confirm new password" 
            required
          >
        </div>

        <button type="submit" class="btn btn-primary btn-block" data-loading-text="Saving Password...">
          Update Password & Login
        </button>
      </form>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border); font-size: 13.5px;">
      <a href="<?= BASE_URL ?>login.php">← Back to <?= __('login') ?></a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
