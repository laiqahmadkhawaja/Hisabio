<?php
/**
 * Hisabio - 500 Internal Server Error
 * PRD Section 26
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/lang.php';

http_response_code(500);
$page_title = '500 - Server Error';
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 500px; margin: 40px auto; text-align: center;">
  <div class="card" style="padding: 36px 20px;">
    <div style="font-size: 54px; margin-bottom: 12px;">⚠️</div>
    <h1 style="font-size: 26px; font-weight: 800; color: var(--danger); margin-bottom: 8px;">500 - Server Error</h1>
    <p style="font-size: 14px; color: var(--text-muted); line-height: 1.5; margin-bottom: 24px;">
      An unexpected error occurred while processing your financial request. Rest assured, incomplete transactions are automatically rolled back.
    </p>

    <div style="display: flex; justify-content: center; gap: 10px;">
      <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-primary">
        🏠 Refresh Dashboard
      </a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
