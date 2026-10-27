<?php
/**
 * Hisabio - 404 Not Found Page
 * PRD Section 26
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/auth.php';

http_response_code(404);
$page_title = '404 - Page Not Found';
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 500px; margin: 40px auto; text-align: center;">
  <div class="card" style="padding: 36px 20px;">
    <div style="font-size: 54px; margin-bottom: 12px;">🔍</div>
    <h1 style="font-size: 26px; font-weight: 800; color: var(--text); margin-bottom: 8px;">404 - Page Not Found</h1>
    <p style="font-size: 14px; color: var(--text-muted); line-height: 1.5; margin-bottom: 24px;">
      The ledger view, transaction, or customer account you requested could not be located.
    </p>

    <div style="display: flex; justify-content: center; gap: 10px;">
      <a href="<?= is_logged_in() ? BASE_URL . 'dashboard.php' : BASE_URL . 'login.php' ?>" class="btn btn-primary">
        🏠 Back to <?= is_logged_in() ? 'Dashboard' : 'Login' ?>
      </a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
