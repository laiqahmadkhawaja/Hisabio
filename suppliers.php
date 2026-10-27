<?php
/**
 * Hisabio - Supplier Listing & Search
 * PRD Section 10: Supplier management, payables tracking, server-side search
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$biz = current_business();
$biz_id = (int)$biz['id'];
$pdo = get_db();

$search = trim($_GET['q'] ?? '');
$status = in_array($_GET['status'] ?? '', ['active', 'archived']) ? $_GET['status'] : 'active';
$sort = $_GET['sort'] ?? 'name_asc';

$sql = "
    SELECT s.*, a.id AS account_id, a.opening_balance, a.opening_balance_direction
    FROM suppliers s
    JOIN accounts a ON a.supplier_id = s.id
    WHERE s.business_id = ? AND s.status = ?
";
$params = [$biz_id, $status];

if (!empty($search)) {
    $sql .= " AND (s.name LIKE ? OR s.mobile LIKE ? OR s.supplier_code LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$raw_suppliers = $stmt->fetchAll();

$suppliers = [];
$total_payables = 0.00;

foreach ($raw_suppliers as $supp) {
    $supp['balance'] = calculate_supplier_balance((int)$supp['account_id'], $pdo);
    if ($supp['balance'] > 0) {
        $total_payables += $supp['balance'];
    }
    $suppliers[] = $supp;
}

// Sorting
if ($sort === 'balance_desc') {
    usort($suppliers, fn($a, $b) => $b['balance'] <=> $a['balance']);
} elseif ($sort === 'balance_asc') {
    usort($suppliers, fn($a, $b) => $a['balance'] <=> $b['balance']);
} elseif ($sort === 'name_desc') {
    usort($suppliers, fn($a, $b) => strcasecmp($b['name'], $a['name']));
} else {
    usort($suppliers, fn($a, $b) => strcasecmp($a['name'], $b['name']));
}

$page_title = __('suppliers');
include __DIR__ . '/includes/header.php';
?>

<div class="page-container">
  <div class="page-header">
    <div>
      <h1 class="page-title"><?= __('suppliers') ?></h1>
      <p class="page-subtitle">
        <?= count($suppliers) ?> <?= $status === 'archived' ? 'archived' : 'active' ?> suppliers • Total Payable: <strong><?= format_currency($total_payables) ?></strong>
      </p>
    </div>

    <a href="<?= BASE_URL ?>supplier-add.php" class="btn btn-primary">
      🏢 <?= __('add_supplier') ?>
    </a>
  </div>

  <!-- Search & Filter Controls -->
  <div class="card mb-3">
    <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 10px;">
      <div style="flex: 1; min-width: 220px;">
        <input 
          type="text" 
          id="supplier-search-input"
          name="q" 
          class="form-control" 
          placeholder="Search suppliers by name, phone or code..." 
          value="<?= htmlspecialchars($search) ?>"
        >
      </div>

      <div style="width: 140px;">
        <select name="sort" class="form-control" onchange="this.form.submit()">
          <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
          <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
          <option value="balance_desc" <?= $sort === 'balance_desc' ? 'selected' : '' ?>>Highest Payable</option>
          <option value="balance_asc" <?= $sort === 'balance_asc' ? 'selected' : '' ?>>Lowest Payable</option>
        </select>
      </div>

      <div style="width: 120px;">
        <select name="status" class="form-control" onchange="this.form.submit()">
          <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
        </select>
      </div>

      <button type="submit" class="btn btn-outline" style="min-width: 80px;">Filter</button>
      <?php if (!empty($search) || $status !== 'active' || $sort !== 'name_asc'): ?>
        <a href="<?= BASE_URL ?>suppliers.php" class="btn btn-outline" style="color: var(--danger);">Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Supplier Cards Stack -->
  <?php if (empty($suppliers)): ?>
    <div class="card" style="text-align: center; padding: 40px 20px;">
      <p style="font-size: 16px; color: var(--text-muted); margin-bottom: 16px;">
        <?= !empty($search) ? 'No suppliers matching your search query.' : 'No suppliers registered in your khata.' ?>
      </p>
      <a href="<?= BASE_URL ?>supplier-add.php" class="btn btn-primary" style="display: inline-flex;">
        ➕ Add Your First Supplier
      </a>
    </div>
  <?php else: ?>
    <div class="ledger-stack" id="supplier-list">
      <?php foreach ($suppliers as $s): ?>
        <div class="ledger-item supplier-card-item <?= $s['balance'] > 0 ? 'payment' : '' ?>">
          <div class="item-main">
            <a href="<?= BASE_URL ?>supplier-profile.php?id=<?= $s['id'] ?>" class="item-title" style="display: flex; align-items: center; gap: 8px;">
              <span><?= htmlspecialchars($s['name']) ?></span>
              <span class="badge badge-neutral" style="font-size: 11px;"><?= htmlspecialchars($s['supplier_code']) ?></span>
            </a>
            <div class="item-sub">
              📞 <?= htmlspecialchars($s['mobile']) ?>
              <?php if (!empty($s['alternate_contact'])): ?>
                • Rep: <?= htmlspecialchars($s['alternate_contact']) ?>
              <?php endif; ?>
              <?php if (!empty($s['address'])): ?>
                • 📍 <?= htmlspecialchars(mb_strimwidth($s['address'], 0, 30, '...')) ?>
              <?php endif; ?>
            </div>
          </div>

          <div class="item-amount">
            <div class="amount-val <?= $s['balance'] > 0 ? 'text-payment' : 'text-muted' ?>">
              <?= format_currency($s['balance']) ?>
            </div>
            <div class="item-sub">
              <?= $s['balance'] > 0 ? 'You Owe Supplier' : ($s['balance'] < 0 ? 'Supplier Owes You' : 'Cleared') ?>
            </div>
          </div>

          <div style="display: flex; gap: 6px; margin-left: 12px;">
            <a href="<?= BASE_URL ?>supplier-purchase.php?supplier_id=<?= $s['id'] ?>" class="btn btn-outline btn-sm" title="Record Purchase" style="padding: 6px 8px; color: var(--credit);">
              +Buy
            </a>
            <a href="<?= BASE_URL ?>supplier-payment.php?supplier_id=<?= $s['id'] ?>" class="btn btn-outline btn-sm" title="Pay Supplier" style="padding: 6px 8px; color: var(--payment);">
              +Pay
            </a>
            <a href="<?= BASE_URL ?>supplier-ledger.php?id=<?= $s['id'] ?>" class="btn btn-primary btn-sm" style="padding: 6px 10px;">
              Khata
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  HisabioLedger.initInstantSearch('#supplier-search-input', '.supplier-card-item', '.item-title');
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
