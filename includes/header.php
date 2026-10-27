<?php
/**
 * Hisabio - Shared Header Component
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/functions.php';

$current_user = current_user();
$current_business = current_business();
$is_rtl = is_rtl();
$lang = get_current_lang();
$flashes = get_flash_messages();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $is_rtl ? 'rtl' : 'ltr' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
  <meta name="theme-color" content="#087F5B">
  <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
  
  <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' — ' . APP_NAME : APP_NAME . ' — ' . __('tagline') ?></title>
  
  <!-- Favicons and PWA -->
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>favicon.svg">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>assets/images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>assets/images/favicon-16x16.png">
  <link rel="apple-touch-icon" href="<?= BASE_URL ?>assets/images/apple-touch-icon.png">
  <link rel="manifest" href="<?= BASE_URL ?>manifest.json">

  <!-- Stylesheets -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= APP_VERSION ?>">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/responsive.css?v=<?= APP_VERSION ?>">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/animations.css?v=<?= APP_VERSION ?>">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/print.css?v=<?= APP_VERSION ?>" media="print">
</head>
<body class="page-enter">
  <div id="offline-banner"></div>

  <div class="app-layout">
    <?php if (is_logged_in()): ?>
      <?php include __DIR__ . '/sidebar.php'; ?>
    <?php endif; ?>

    <main class="main-content">
      <header class="app-header no-print">
        <div class="header-brand">
          <a href="<?= is_logged_in() ? BASE_URL . 'dashboard.php' : BASE_URL . 'login.php' ?>" class="d-flex align-items-center">
            <img src="<?= BASE_URL ?>assets/images/logo.svg" alt="Hisabio Logo">
          </a>
          <?php if ($current_business): ?>
            <span class="badge badge-neutral" style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
              <?= htmlspecialchars($current_business['business_name']) ?>
            </span>
          <?php endif; ?>
        </div>

        <div class="header-actions">
          <!-- Language Toggle -->
          <?php if ($lang === 'en'): ?>
            <a href="?lang=ur" class="btn btn-outline btn-sm" title="اردو میں تبدیل کریں">اردو</a>
          <?php else: ?>
            <a href="?lang=en" class="btn btn-outline btn-sm" title="Switch to English">English</a>
          <?php endif; ?>

          <?php if (is_logged_in()): ?>
            <a href="<?= BASE_URL ?>settings.php" class="btn btn-outline btn-sm" title="<?= __('settings') ?>" aria-label="<?= __('settings') ?>">
              ⚙️
            </a>
          <?php else: ?>
            <a href="<?= BASE_URL ?>login.php" class="btn btn-primary btn-sm"><?= __('login') ?></a>
          <?php endif; ?>
        </div>
      </header>
