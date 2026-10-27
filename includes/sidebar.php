<?php
/**
 * Hisabio - Desktop Sidebar Navigation
 */
$current_page = basename($_SERVER['PHP_SELF'] ?? '');
?>
<aside class="app-sidebar no-print">
  <div class="sidebar-header">
    <a href="<?= BASE_URL ?>dashboard.php">
      <img src="<?= BASE_URL ?>assets/images/logo.svg" alt="Hisabio">
    </a>
  </div>

  <nav class="sidebar-nav">
    <a href="<?= BASE_URL ?>dashboard.php" class="sidebar-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
      <span>📊</span>
      <span><?= __('dashboard') ?></span>
    </a>

    <a href="<?= BASE_URL ?>customers.php" class="sidebar-link <?= in_array($current_page, ['customers.php', 'customer-add.php', 'customer-edit.php', 'customer-profile.php', 'customer-ledger.php', 'customer-statement.php'], true) ? 'active' : '' ?>">
      <span>👥</span>
      <span><?= __('customers') ?></span>
    </a>

    <a href="<?= BASE_URL ?>suppliers.php" class="sidebar-link <?= in_array($current_page, ['suppliers.php', 'supplier-add.php', 'supplier-edit.php', 'supplier-profile.php', 'supplier-ledger.php', 'supplier-statement.php'], true) ? 'active' : '' ?>">
      <span>🏢</span>
      <span><?= __('suppliers') ?></span>
    </a>

    <a href="<?= BASE_URL ?>give-credit.php" class="sidebar-link <?= $current_page === 'give-credit.php' ? 'active' : '' ?>">
      <span>📤</span>
      <span><?= __('give_credit') ?></span>
    </a>

    <a href="<?= BASE_URL ?>receive-payment.php" class="sidebar-link <?= $current_page === 'receive-payment.php' ? 'active' : '' ?>">
      <span>📥</span>
      <span><?= __('receive_payment') ?></span>
    </a>

    <a href="<?= BASE_URL ?>transactions.php" class="sidebar-link <?= in_array($current_page, ['transactions.php', 'transaction-details.php', 'transaction-reverse.php'], true) ? 'active' : '' ?>">
      <span>📑</span>
      <span><?= __('transactions') ?></span>
    </a>

    <a href="<?= BASE_URL ?>reports.php" class="sidebar-link <?= $current_page === 'reports.php' ? 'active' : '' ?>">
      <span>📈</span>
      <span><?= __('reports') ?></span>
    </a>

    <a href="<?= BASE_URL ?>reminders.php" class="sidebar-link <?= $current_page === 'reminders.php' ? 'active' : '' ?>">
      <span>💬</span>
      <span><?= __('reminders') ?></span>
    </a>

    <a href="<?= BASE_URL ?>settings.php" class="sidebar-link <?= in_array($current_page, ['settings.php', 'appearance.php', 'security.php', 'backup.php', 'users.php', 'roles.php'], true) ? 'active' : '' ?>">
      <span>⚙️</span>
      <span><?= __('settings') ?></span>
    </a>

    <a href="<?= BASE_URL ?>help.php" class="sidebar-link <?= $current_page === 'help.php' ? 'active' : '' ?>">
      <span>❓</span>
      <span><?= __('help_support') ?></span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="business-pill">
      <span class="business-pill-name"><?= htmlspecialchars($current_user['full_name'] ?? 'User') ?></span>
      <span class="business-pill-role"><?= htmlspecialchars($current_business['role_name'] ?? 'Owner') ?></span>
    </div>
    <a href="<?= BASE_URL ?>logout.php" class="btn btn-outline btn-sm" title="<?= __('logout') ?>" style="padding: 4px 8px;">
      🚪
    </a>
  </div>
</aside>
