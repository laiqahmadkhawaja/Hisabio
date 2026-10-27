<?php
/**
 * Hisabio - Roles & Permissions Management
 * PRD Section 19.1 & 26
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

// Handle creating new role
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $role_name = trim($_POST['role_name'] ?? '');
    if (!empty($role_name)) {
        $permissions = [
            'all' => false,
            'customers' => ['view', 'create'],
            'suppliers' => ['view', 'create'],
            'transactions' => ['view', 'create'],
            'reports' => ['view']
        ];
        $stmt = $pdo->prepare("INSERT INTO roles (business_id, role_name, permissions, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$biz_id, $role_name, json_encode($permissions)]);
        flash_message('success', "Role '{$role_name}' created.");
        header('Location: ' . BASE_URL . 'roles.php');
        exit;
    }
}

// Fetch all roles for this business
$stmt = $pdo->prepare("SELECT * FROM roles WHERE business_id = ? ORDER BY id ASC");
$stmt->execute([$biz_id]);
$roles = $stmt->fetchAll();

$page_title = 'Roles & Permissions';
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 640px;">
  <div class="page-header">
    <div>
      <h1 class="page-title">🛡️ Roles & Permissions</h1>
      <p class="page-subtitle">Configure access levels for staff and managers</p>
    </div>
    <a href="<?= BASE_URL ?>settings.php" class="btn btn-outline btn-sm">
      ← Settings
    </a>
  </div>

  <div class="card mb-4">
    <div class="card-header">
      <h2 class="card-title">Create Custom Role</h2>
    </div>

    <form method="POST" action="" style="display: flex; gap: 10px;">
      <?= csrf_field() ?>
      <input type="text" name="role_name" class="form-control" placeholder="e.g. Senior Munshi or Accountant" required>
      <button type="submit" class="btn btn-primary" style="white-space: nowrap;">Create Role</button>
    </form>
  </div>

  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Configured Business Roles</h2>
    </div>

    <div class="ledger-stack">
      <?php foreach ($roles as $r): ?>
        <?php $perms = json_decode($r['permissions'] ?? '{}', true); ?>
        <div class="ledger-item">
          <div class="item-main">
            <div class="item-title"><?= htmlspecialchars($r['role_name']) ?></div>
            <div class="item-sub">
              <?= !empty($perms['all']) ? 'Full Super-Admin Control across all khata modules' : 'Operational Staff (Record udhaar & payments, view khata)' ?>
            </div>
          </div>
          <div>
            <span class="badge <?= !empty($perms['all']) ? 'badge-credit' : 'badge-neutral' ?>">
              <?= !empty($perms['all']) ? 'Owner' : 'Staff' ?>
            </span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
