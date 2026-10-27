<?php
/**
 * Hisabio - Security Settings & Audit Trail
 * PRD Section 22: Authentication security, rate limiting, login activity
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$user = current_user();
$pdo = get_db();

// Fetch recent login activity for this user or identifier
$hash = hash('sha256', strtolower(trim($user['mobile'])));
$stmt = $pdo->prepare("SELECT * FROM login_attempts WHERE identifier_hash = ? ORDER BY attempted_at DESC LIMIT 15");
$stmt->execute([$hash]);
$login_logs = $stmt->fetchAll();

$page_title = 'Security Settings';
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 640px;">
  <div class="page-header">
    <div>
      <h1 class="page-title">🛡️ Security Settings</h1>
      <p class="page-subtitle">Account protection, rate limiting, and access logs</p>
    </div>
    <a href="<?= BASE_URL ?>settings.php" class="btn btn-outline btn-sm">
      ← Settings
    </a>
  </div>

  <div class="card mb-4">
    <div class="card-header">
      <h2 class="card-title">Security & Session Status</h2>
    </div>
    <ul style="list-style: none; display: flex; flex-direction: column; gap: 10px; font-size: 14px;">
      <li>✅ <strong>Password Hashing:</strong> BCRYPT (cost factor 10) active.</li>
      <li>✅ <strong>Brute-Force Protection:</strong> Automatic 15-minute lock after 5 failed attempts.</li>
      <li>✅ <strong>Session Security:</strong> HttpOnly, SameSite=Lax, and session regeneration on login.</li>
      <li>✅ <strong>CSRF Protection:</strong> Cryptographic token validation on all state-changing requests.</li>
      <li>✅ <strong>Tenant Isolation:</strong> Strict database filtering per business ID.</li>
    </ul>

    <div class="mt-3">
      <a href="<?= BASE_URL ?>profile.php" class="btn btn-outline btn-sm">
        Change Password in Profile →
      </a>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Recent Login Attempts</h2>
      <span style="font-size: 12px; color: var(--text-muted);">Recorded by security monitor</span>
    </div>

    <?php if (empty($login_logs)): ?>
      <p style="font-size: 13.5px; color: var(--text-muted); text-align: center; padding: 20px 0;">
        No recent login security flags recorded.
      </p>
    <?php else: ?>
      <div class="ledger-stack">
        <?php foreach ($login_logs as $log): ?>
          <div class="ledger-item" style="padding: 10px 14px;">
            <div class="item-main">
              <div style="font-size: 13.5px; font-weight: 600;">
                IP: <?= htmlspecialchars($log['ip_address'] ?? 'Unknown') ?>
              </div>
              <div class="item-sub">
                <?= format_datetime($log['attempted_at']) ?>
              </div>
            </div>

            <div>
              <span class="badge <?= $log['outcome'] === 'success' ? 'badge-payment' : 'badge-danger' ?>">
                <?= ucfirst($log['outcome']) ?>
              </span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
