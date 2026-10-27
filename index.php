<?php
/**
 * Hisabio - Landing Page & Entry Gateway
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$page_title = 'Digital Khata & Business Ledger for Pakistan';
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 680px; margin: 40px auto 20px auto; text-align: center;">
  <div style="margin-bottom: 24px;">
    <img src="<?= BASE_URL ?>assets/images/app-icon.svg" alt="Hisabio App Icon" style="width: 88px; height: 88px; border-radius: 20px; box-shadow: var(--shadow-md);">
  </div>

  <h1 style="font-size: 32px; font-weight: 800; color: var(--text); letter-spacing: -0.5px; margin-bottom: 8px;">
    Hisab<span style="color: var(--primary);">io</span>
  </h1>
  <p style="font-size: 18px; font-weight: 600; color: var(--primary); margin-bottom: 12px;">
    "Har Hisab Saaf, Har Khata Aasaan"
  </p>
  <p style="font-size: 15px; color: var(--text-muted); line-height: 1.6; margin-bottom: 30px;">
    Smart digital khata and udhaar ledger for Pakistani shopkeepers, retailers, wholesalers, and small businesses. Replace paper notebooks with fast, 100% accurate, mobile-friendly records.
  </p>

  <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 36px;">
    <a href="<?= BASE_URL ?>login.php" class="btn btn-primary btn-block" style="font-size: 16px; padding: 14px;">
      🔑 <?= __('login') ?>
    </a>
    <a href="<?= BASE_URL ?>register.php" class="btn btn-outline-primary btn-block" style="font-size: 16px; padding: 14px;">
      ✨ <?= __('register') ?>
    </a>
  </div>

  <div class="card" style="text-align: left; background: #FFFFFF;">
    <h3 style="font-size: 16px; font-weight: 700; color: var(--text); margin-bottom: 14px; border-bottom: 1px solid var(--border); padding-bottom: 8px;">
      🇵🇰 Designed for Pakistani Businesses
    </h3>
    <ul style="list-style: none; display: flex; flex-direction: column; gap: 10px; font-size: 14px; color: var(--text);">
      <li>✅ <strong>Customer Udhaar & Payments:</strong> Instant running balance calculations with strict mathematical accuracy.</li>
      <li>✅ <strong>Supplier Ledger:</strong> Track credit purchases and payments to distributors seamlessly.</li>
      <li>✅ <strong>1-Tap WhatsApp Reminders:</strong> Send polite, formatted balance payment reminders in English & Urdu.</li>
      <li>✅ <strong>Strict No-Modal Policy:</strong> Clean mobile ergonomics—no annoying popup dialogs or accidental dismissals.</li>
      <li>✅ <strong>Bilingual (English & اردو):</strong> Full native Urdu RTL support for shopkeepers and munshis.</li>
    </ul>
  </div>

  <div class="card" style="background: var(--accent-soft); border-color: var(--accent); margin-top: 16px;">
    <p style="font-size: 13px; color: var(--primary-dark); font-weight: 600;">
      ⚡ Default Demo Credentials:
    </p>
    <p style="font-size: 13px; color: var(--text); margin-top: 4px;">
      Mobile: <code>03001234567</code> | Password: <code>admin123</code>
    </p>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
