<?php
/**
 * Hisabio - Mobile Sticky Bottom Navigation
 * Strict No-Modal UI: Quick action button navigates to dedicated entry page
 */
$current_page = basename($_SERVER['PHP_SELF'] ?? '');
?>
<?php if (is_logged_in()): ?>
  <!-- Mobile Floating Quick-Action Button -->
  <a href="<?= BASE_URL ?>give-credit.php" class="floating-add-btn no-print" title="<?= __('give_credit') ?>" aria-label="<?= __('give_credit') ?>">
    +
  </a>

  <!-- Mobile Bottom Navigation Bar -->
  <nav class="bottom-nav no-print" aria-label="Mobile Navigation">
    <a href="<?= BASE_URL ?>dashboard.php" class="nav-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
      </svg>
      <span><?= __('dashboard') ?></span>
    </a>

    <a href="<?= BASE_URL ?>customers.php" class="nav-item <?= in_array($current_page, ['customers.php', 'customer-ledger.php', 'customer-profile.php', 'customer-add.php'], true) ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
      </svg>
      <span><?= __('customers') ?></span>
    </a>

    <a href="<?= BASE_URL ?>transactions.php" class="nav-item <?= in_array($current_page, ['transactions.php', 'transaction-details.php'], true) ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
      </svg>
      <span><?= __('transactions') ?></span>
    </a>

    <a href="<?= BASE_URL ?>reports.php" class="nav-item <?= $current_page === 'reports.php' ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
      </svg>
      <span><?= __('reports') ?></span>
    </a>

    <a href="<?= BASE_URL ?>settings.php" class="nav-item <?= in_array($current_page, ['settings.php', 'suppliers.php', 'reminders.php', 'help.php'], true) ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
      </svg>
      <span><?= __('settings') ?></span>
    </a>
  </nav>
<?php endif; ?>
