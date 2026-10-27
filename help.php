<?php
/**
 * Hisabio - Help & Support Guide
 * PRD Section 26: User guide and FAQ for Pakistani shopkeepers
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = __('help_support');
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 720px;">
  <div class="page-header">
    <div>
      <h1 class="page-title">❓ <?= __('help_support') ?></h1>
      <p class="page-subtitle">Simple guide for everyday shopkeepers and business owners</p>
    </div>
    <?php if (is_logged_in()): ?>
      <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-outline btn-sm">
        ← <?= __('dashboard') ?>
      </a>
    <?php endif; ?>
  </div>

  <div class="card mb-4" style="background: var(--accent-soft); border-color: var(--accent);">
    <h2 style="font-size: 18px; font-weight: 700; color: var(--primary-dark); margin-bottom: 6px;">
      "Har Hisab Saaf, Har Khata Aasaan"
    </h2>
    <p style="font-size: 14px; color: var(--text); line-height: 1.6;">
      Hisabio replaces traditional, fragile paper ledgers with a 100% accurate, mobile-first digital khata. You can record credit sales, counter collections, send WhatsApp reminders, and track supplier dues seamlessly.
    </p>
  </div>

  <!-- FAQ Accordion Details -->
  <div style="display: flex; flex-direction: column; gap: 12px;">
    <!-- 1. Customer Udhaar Entry -->
    <details class="card" style="margin: 0;" open>
      <summary style="font-weight: 700; font-size: 15px; cursor: pointer; color: var(--text);">
        1. How do I record credit given to a customer (Udhaar Diya)?
      </summary>
      <div style="margin-top: 10px; font-size: 14px; color: var(--text-muted); line-height: 1.6;">
        Tap the <strong>+Udhaar Diya</strong> button on the dashboard or inside any customer ledger. Select the customer, enter the amount and item description, and tap Save. The customer's running balance updates instantly.
      </div>
    </details>

    <!-- 2. Customer Payment -->
    <details class="card" style="margin: 0;">
      <summary style="font-weight: 700; font-size: 15px; cursor: pointer; color: var(--text);">
        2. How do I record a payment received from a customer (Raqam Wasool)?
      </summary>
      <div style="margin-top: 10px; font-size: 14px; color: var(--text-muted); line-height: 1.6;">
        Tap <strong>+Raqam Wasool</strong>. Choose the customer, enter the amount received, select payment method (Cash, Easypaisa, JazzCash, or Bank), and save. You will immediately be offered a digital receipt and a 1-tap WhatsApp confirmation link.
      </div>
    </details>

    <!-- 3. Understanding Balances -->
    <details class="card" style="margin: 0;">
      <summary style="font-weight: 700; font-size: 15px; cursor: pointer; color: var(--text);">
        3. What does a Positive vs Negative balance mean?
      </summary>
      <div style="margin-top: 10px; font-size: 14px; color: var(--text-muted); line-height: 1.6;">
        <strong>Customer Balance:</strong><br>
        • Positive (e.g. PKR 5,000) = Customer owes your shop money (Receivable).<br>
        • Negative (e.g. -PKR 500) = Customer paid in advance or has an excess credit.<br><br>
        <strong>Supplier Balance:</strong><br>
        • Positive (e.g. PKR 15,000) = You owe the supplier for stock received (Payable).<br>
        • Zero (PKR 0.00) = Completely cleared.
      </div>
    </details>

    <!-- 4. WhatsApp Reminders -->
    <details class="card" style="margin: 0;">
      <summary style="font-weight: 700; font-size: 15px; cursor: pointer; color: var(--text);">
        4. How do WhatsApp reminders work?
      </summary>
      <div style="margin-top: 10px; font-size: 14px; color: var(--text-muted); line-height: 1.6;">
        Hisabio generates an official, polite payment reminder message with your shop name and the exact outstanding amount. Tapping the WhatsApp button opens your customer's chat with the message ready to send with one tap.
      </div>
    </details>

    <!-- 5. Strict No-Modal Design -->
    <details class="card" style="margin: 0;">
      <summary style="font-weight: 700; font-size: 15px; cursor: pointer; color: var(--text);">
        5. Why are there no popup dialogs or modals in Hisabio?
      </summary>
      <div style="margin-top: 10px; font-size: 14px; color: var(--text-muted); line-height: 1.6;">
        On mobile phones, popup dialogs (modals) often get accidentally closed, cause horizontal scrolling, or break keyboards. Hisabio adheres to a <strong>Strict No-Modal Policy</strong>: every entry form has its own dedicated, clean screen, preventing accidental data loss and ensuring maximum speed.
      </div>
    </details>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
