<?php
/**
 * Hisabio - Business Staff & User Management
 * PRD Section 19.1 & 26: Multi-user shop access (Owner, Munshi, Cashier)
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

// Fetch roles for business
$stmtRoles = $pdo->prepare("SELECT id, role_name FROM roles WHERE business_id = ?");
$stmtRoles->execute([$biz_id]);
$roles = $stmtRoles->fetchAll();

$errors = [];

// Handle Adding a Staff Member
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $name = trim($_POST['full_name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    $role_id = (int)($_POST['role_id'] ?? 0);

    if (empty($name)) $errors[] = 'Full name is required.';
    if (empty($mobile)) $errors[] = 'Mobile number is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($role_id <= 0) $errors[] = 'Please select a role.';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Check if user already exists
            $stmtUser = $pdo->prepare("SELECT id FROM users WHERE mobile = ?");
            $stmtUser->execute([$mobile]);
            $existing_user_id = $stmtUser->fetchColumn();

            if (!$existing_user_id) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmtIns = $pdo->prepare("INSERT INTO users (full_name, mobile, password_hash, status, created_at) VALUES (?, ?, ?, 'active', NOW())");
                $stmtIns->execute([$name, $mobile, $hash]);
                $existing_user_id = (int)$pdo->lastInsertId();
            }

            // Link to business_users
            $stmtLink = $pdo->prepare("
                INSERT INTO business_users (business_id, user_id, role_id, status, created_at)
                VALUES (?, ?, ?, 'active', NOW())
                ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), status = 'active'
            ");
            $stmtLink->execute([$biz_id, $existing_user_id, $role_id]);

            $pdo->commit();
            flash_message('success', "Staff member '{$name}' added successfully.");
            header('Location: ' . BASE_URL . 'users.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Error adding staff: ' . $e->getMessage());
            $errors[] = 'Failed to add staff member: ' . $e->getMessage();
        }
    }
}

// Fetch all staff members linked to this business
$stmtStaff = $pdo->prepare("
    SELECT u.id, u.full_name, u.mobile, u.email, bu.status, r.role_name
    FROM business_users bu
    JOIN users u ON u.id = bu.user_id
    JOIN roles r ON r.id = bu.role_id
    WHERE bu.business_id = ?
    ORDER BY u.id ASC
");
$stmtStaff->execute([$biz_id]);
$staff_list = $stmtStaff->fetchAll();

$page_title = 'Staff & User Management';
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 680px;">
  <div class="page-header">
    <div>
      <h1 class="page-title">👥 Staff & Users</h1>
      <p class="page-subtitle">Manage shop cashiers, munshis, and operators</p>
    </div>
    <a href="<?= BASE_URL ?>settings.php" class="btn btn-outline btn-sm">
      ← Settings
    </a>
  </div>

  <!-- Add Staff Card -->
  <div class="card mb-4">
    <div class="card-header">
      <h2 class="card-title">Add Staff / Operator</h2>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="inline-confirmation" style="margin-top: 0; margin-bottom: 16px;">
        <span class="confirmation-title">Please fix:</span>
        <ul style="margin-left: 20px; font-size: 13.5px; color: #7F1D1D;">
          <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" action="" data-loading-state>
      <?= csrf_field() ?>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="full_name">Staff Full Name <span class="required">*</span></label>
          <input type="text" id="full_name" name="full_name" class="form-control" placeholder="e.g. Asim Raza" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="mobile">Mobile Number (Login) <span class="required">*</span></label>
          <input type="tel" id="mobile" name="mobile" class="form-control" placeholder="03009876543" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="password">Login Password <span class="required">*</span></label>
          <input type="password" id="password" name="password" class="form-control" placeholder="Min. 6 chars" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="role_id">Assigned Role <span class="required">*</span></label>
          <select id="role_id" name="role_id" class="form-control" required>
            <?php foreach ($roles as $r): ?>
              <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-block" data-loading-text="Adding Staff...">
        ➕ Add Staff Account
      </button>
    </form>
  </div>

  <!-- Existing Staff List -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Current Business Users</h2>
      <span style="font-size: 12px; color: var(--text-muted);"><?= count($staff_list) ?> members</span>
    </div>

    <div class="ledger-stack">
      <?php foreach ($staff_list as $st): ?>
        <div class="ledger-item">
          <div class="item-main">
            <div class="item-title">
              <?= htmlspecialchars($st['full_name']) ?>
              <span class="badge badge-neutral" style="font-size: 11px; margin-left: 6px;">
                <?= htmlspecialchars($st['role_name']) ?>
              </span>
            </div>
            <div class="item-sub">
              📞 <?= htmlspecialchars($st['mobile']) ?>
            </div>
          </div>

          <div>
            <span class="badge badge-payment">Active</span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
