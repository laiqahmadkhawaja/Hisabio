<?php
/**
 * Hisabio - Database Backup & Data Export
 * PRD Section 22.6 & 26: One-click JSON & SQL backup for shopkeepers
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

// Trigger JSON Export
if (isset($_GET['download']) && $_GET['download'] === 'json') {
    // Collect all data for this business
    $stmtC = $pdo->prepare("SELECT * FROM customers WHERE business_id = ?");
    $stmtC->execute([$biz_id]);
    $customers = $stmtC->fetchAll();

    $stmtS = $pdo->prepare("SELECT * FROM suppliers WHERE business_id = ?");
    $stmtS->execute([$biz_id]);
    $suppliers = $stmtS->fetchAll();

    $stmtT = $pdo->prepare("SELECT * FROM transactions WHERE business_id = ?");
    $stmtT->execute([$biz_id]);
    $transactions = $stmtT->fetchAll();

    $backup_data = [
        'app' => APP_NAME,
        'version' => APP_VERSION,
        'exported_at' => date('Y-m-d H:i:s'),
        'business' => $biz,
        'customers' => $customers,
        'suppliers' => $suppliers,
        'transactions' => $transactions
    ];

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="Hisabio_Backup_' . preg_replace('/[^a-zA-Z0-9]/', '_', $biz['business_name']) . '_' . date('Ymd_His') . '.json"');
    echo json_encode($backup_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$page_title = 'Database Backup & Export';
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 600px;">
  <div class="page-header">
    <div>
      <h1 class="page-title">💾 <?= __('backup_export') ?></h1>
      <p class="page-subtitle">Download and preserve your complete khata records</p>
    </div>
    <a href="<?= BASE_URL ?>settings.php" class="btn btn-outline btn-sm">
      ← Settings
    </a>
  </div>

  <div class="card">
    <div style="text-align: center; padding: 20px 0;">
      <div style="font-size: 48px; margin-bottom: 12px;">📦</div>
      <h2 style="font-size: 18px; font-weight: 700; color: var(--text);">Complete Khata Data Backup</h2>
      <p style="font-size: 14px; color: var(--text-muted); max-width: 440px; margin: 8px auto 20px auto; line-height: 1.5;">
        Generate an instant, portable snapshot of all customers, suppliers, transactions, and balances. Store it securely on your phone, laptop, or Google Drive.
      </p>

      <a href="?download=json" class="btn btn-primary" style="padding: 12px 24px; font-size: 15px;">
        📥 Download JSON Backup File
      </a>
    </div>

    <div style="border-top: 1px solid var(--border); padding-top: 16px; margin-top: 10px; font-size: 13px; color: var(--text-muted);">
      <strong>Security Notice:</strong> Backups contain sensitive financial transactions and customer phone numbers. Keep downloaded files confidential.
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
