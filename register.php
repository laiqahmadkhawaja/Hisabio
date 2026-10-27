<?php
/**
 * Hisabio - User & Business Registration
 * PRD Section 5.1: Mobile-first registration, PKR default, initial business setup
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$errors = [];
$form_data = [
    'full_name' => '',
    'mobile' => '',
    'email' => '',
    'business_name' => '',
    'business_category' => 'General Store',
    'language' => 'en'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $form_data['full_name'] = trim($_POST['full_name'] ?? '');
    $form_data['mobile'] = trim($_POST['mobile'] ?? '');
    $form_data['email'] = trim($_POST['email'] ?? '');
    $form_data['business_name'] = trim($_POST['business_name'] ?? '');
    $form_data['business_category'] = trim($_POST['business_category'] ?? 'General Store');
    $form_data['language'] = in_array($_POST['language'] ?? '', ['en', 'ur'], true) ? $_POST['language'] : 'en';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Validation
    if (empty($form_data['full_name'])) {
        $errors[] = 'Full name is required.';
    }
    if (empty($form_data['mobile'])) {
        $errors[] = 'Mobile number is required.';
    }
    if (empty($form_data['business_name'])) {
        $errors[] = 'Business / Shop name is required.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $password_confirm) {
        $errors[] = 'Passwords do not match.';
    }

    $pdo = get_db();

    // Check if mobile already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE mobile = ?");
        $stmt->execute([$form_data['mobile']]);
        if ($stmt->fetch()) {
            $errors[] = 'This mobile number is already registered. Please login instead.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Create User
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $stmtUser = $pdo->prepare("
                INSERT INTO users (full_name, email, mobile, password_hash, status, created_at)
                VALUES (?, ?, ?, ?, 'active', NOW())
            ");
            $stmtUser->execute([
                $form_data['full_name'],
                !empty($form_data['email']) ? $form_data['email'] : null,
                $form_data['mobile'],
                $password_hash
            ]);
            $user_id = (int)$pdo->lastInsertId();

            // 2. Create Business
            $business_code = 'HSB-' . date('ym') . '-' . random_int(100, 999);
            $stmtBiz = $pdo->prepare("
                INSERT INTO businesses (business_name, business_code, owner_user_id, business_category, currency, country, timezone, language, status, created_at)
                VALUES (?, ?, ?, ?, 'PKR', 'Pakistan', 'Asia/Karachi', ?, 'active', NOW())
            ");
            $stmtBiz->execute([
                $form_data['business_name'],
                $business_code,
                $user_id,
                $form_data['business_category'],
                $form_data['language']
            ]);
            $business_id = (int)$pdo->lastInsertId();

            // 3. Create Default Role (Owner)
            $stmtRole = $pdo->prepare("
                INSERT INTO roles (business_id, role_name, permissions, created_at)
                VALUES (?, 'Owner', '{\"all\": true}', NOW())
            ");
            $stmtRole->execute([$business_id]);
            $role_id = (int)$pdo->lastInsertId();

            // 4. Link User to Business
            $stmtBU = $pdo->prepare("
                INSERT INTO business_users (business_id, user_id, role_id, status, created_at)
                VALUES (?, ?, ?, 'active', NOW())
            ");
            $stmtBU->execute([$business_id, $user_id, $role_id]);

            // 5. Default Business Settings
            $stmtSettings = $pdo->prepare("
                INSERT INTO business_settings (business_id, setting_key, setting_value)
                VALUES 
                (?, 'enable_whatsapp_reminders', '1'),
                (?, 'default_credit_days', '15'),
                (?, 'currency_symbol', 'PKR'),
                (?, 'date_format', 'd M Y')
            ");
            $stmtSettings->execute([$business_id, $business_id, $business_id, $business_id]);

            $pdo->commit();

            // Auto Login
            login_user($user_id, $business_id);
            $_SESSION['app_lang'] = $form_data['language'];
            flash_message('success', 'Mubarak! Your Hisabio digital khata has been created successfully.');
            header('Location: ' . BASE_URL . 'dashboard.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Registration Error: ' . $e->getMessage());
            $errors[] = 'Registration failed due to a server error. Please try again.';
        }
    }
}

$page_title = __('register');
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 520px; margin: 24px auto;">
  <div class="card">
    <div style="text-align: center; margin-bottom: 20px;">
      <img src="<?= BASE_URL ?>assets/images/logo.svg" alt="Hisabio Logo" style="height: 48px; width: auto; margin-bottom: 8px;">
      <h2 style="font-size: 20px; font-weight: 700; color: var(--text);"><?= __('register') ?></h2>
      <p style="font-size: 13px; color: var(--text-muted);"><?= __('tagline') ?></p>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="inline-confirmation" style="margin-top: 0; margin-bottom: 16px;">
        <span class="confirmation-title">Please fix the following:</span>
        <ul style="margin-left: 20px; font-size: 13.5px; color: #7F1D1D;">
          <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" action="" data-loading-state>
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label" for="business_name">
          Business / Shop Name <span class="required">*</span>
        </label>
        <input 
          type="text" 
          id="business_name" 
          name="business_name" 
          class="form-control" 
          placeholder="e.g. Al-Madina General Store" 
          value="<?= htmlspecialchars($form_data['business_name']) ?>" 
          required 
          autofocus
        >
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="full_name">
            Your Full Name <span class="required">*</span>
          </label>
          <input 
            type="text" 
            id="full_name" 
            name="full_name" 
            class="form-control" 
            placeholder="e.g. Muhammad Ahmed" 
            value="<?= htmlspecialchars($form_data['full_name']) ?>" 
            required
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="mobile">
            <?= __('phone') ?> <span class="required">*</span>
          </label>
          <input 
            type="tel" 
            id="mobile" 
            name="mobile" 
            class="form-control" 
            placeholder="03001234567" 
            value="<?= htmlspecialchars($form_data['mobile']) ?>" 
            required
          >
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="business_category">
            Business Category
          </label>
          <select id="business_category" name="business_category" class="form-control">
            <option value="General Store">Grocery / General Store</option>
            <option value="Medical Store">Medical Store / Pharmacy</option>
            <option value="Clothing Store">Clothing / Garments</option>
            <option value="Wholesale">Wholesale & Distribution</option>
            <option value="Electronics">Electronics & Mobile Phone Shop</option>
            <option value="Hardware">Hardware & Sanitary</option>
            <option value="Freelancer">Service Provider / Freelancer</option>
            <option value="Other">Other Small Business</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="language">
            Preferred Language
          </label>
          <select id="language" name="language" class="form-control">
            <option value="en" <?= $form_data['language'] === 'en' ? 'selected' : '' ?>>English</option>
            <option value="ur" <?= $form_data['language'] === 'ur' ? 'selected' : '' ?>>اردو (Urdu)</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="email">
          Email Address <span style="font-weight: normal; color: var(--text-muted);">(Optional)</span>
        </label>
        <input 
          type="email" 
          id="email" 
          name="email" 
          class="form-control" 
          placeholder="shop@example.com" 
          value="<?= htmlspecialchars($form_data['email']) ?>"
        >
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="password">
            Password <span class="required">*</span>
          </label>
          <input 
            type="password" 
            id="password" 
            name="password" 
            class="form-control" 
            placeholder="Min. 6 characters" 
            required
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="password_confirm">
            Confirm Password <span class="required">*</span>
          </label>
          <input 
            type="password" 
            id="password_confirm" 
            name="password_confirm" 
            class="form-control" 
            placeholder="Confirm password" 
            required
          >
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-block" data-loading-text="Creating Account..." style="margin-top: 10px;">
        ✨ Create Free Khata Account
      </button>
    </form>

    <div style="text-align: center; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border); font-size: 13.5px; color: var(--text-muted);">
      Already have an account? <a href="<?= BASE_URL ?>login.php" style="font-weight: 600;"><?= __('login') ?></a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
