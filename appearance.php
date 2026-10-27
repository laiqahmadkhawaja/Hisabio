<?php
/**
 * Hisabio - Language & Appearance Settings
 * PRD Section 25 & 26: English & Urdu LTR/RTL configuration
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $lang = in_array($_POST['language'] ?? '', ['en', 'ur'], true) ? $_POST['language'] : 'en';

    $_SESSION['app_lang'] = $lang;
    $stmt = $pdo->prepare("UPDATE businesses SET language = ? WHERE id = ?");
    $stmt->execute([$lang, $biz_id]);

    flash_message('success', $lang === 'ur' ? 'زبان کامیابی سے اردو میں تبدیل ہو گئی ہے۔' : 'Language changed to English successfully.');
    header('Location: ' . BASE_URL . 'appearance.php');
    exit;
}

$current_lang = get_current_lang();
$page_title = 'Language & Appearance';
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 600px;">
  <div class="page-header">
    <div>
      <h1 class="page-title">🎨 Language & Appearance</h1>
      <p class="page-subtitle">Configure English & Urdu right-to-left layout</p>
    </div>
    <a href="<?= BASE_URL ?>settings.php" class="btn btn-outline btn-sm">
      ← Settings
    </a>
  </div>

  <div class="card">
    <form method="POST" action="">
      <?= csrf_field() ?>

      <h3 style="font-size: 16px; font-weight: 700; color: var(--text); margin-bottom: 14px;">
        Choose Application Language
      </h3>

      <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px;">
        <!-- English Option -->
        <label class="card" style="margin: 0; padding: 14px; cursor: pointer; display: flex; align-items: center; gap: 12px; border: 2px solid <?= $current_lang === 'en' ? 'var(--primary)' : 'var(--border)' ?>; background: <?= $current_lang === 'en' ? 'var(--accent-soft)' : '#FFF' ?>;">
          <input type="radio" name="language" value="en" <?= $current_lang === 'en' ? 'checked' : '' ?> style="width: 18px; height: 18px;">
          <div>
            <div style="font-weight: 700; font-size: 15px;">English (Default)</div>
            <div style="font-size: 12.5px; color: var(--text-muted);">Standard Left-to-Right (LTR) layout with English labels</div>
          </div>
        </label>

        <!-- Urdu Option -->
        <label class="card" style="margin: 0; padding: 14px; cursor: pointer; display: flex; align-items: center; gap: 12px; border: 2px solid <?= $current_lang === 'ur' ? 'var(--primary)' : 'var(--border)' ?>; background: <?= $current_lang === 'ur' ? 'var(--accent-soft)' : '#FFF' ?>;">
          <input type="radio" name="language" value="ur" <?= $current_lang === 'ur' ? 'checked' : '' ?> style="width: 18px; height: 18px;">
          <div>
            <div style="font-weight: 700; font-size: 16px; font-family: var(--font-urdu);">اردو (Urdu)</div>
            <div style="font-size: 12.5px; color: var(--text-muted);">دائیں سے بائیں (RTL) لے آؤٹ، جمیل نوری نستعلیق فونٹس اور اردو اصطلاحات</div>
          </div>
        </label>
      </div>

      <button type="submit" class="btn btn-primary btn-block">
        💾 Save Language Preference
      </button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
