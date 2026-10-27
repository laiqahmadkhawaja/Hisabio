<?php
/**
 * Hisabio - Customer Listing & Search
 * PRD Section 8.1: Server-side search, sort, filter, pagination, mobile-friendly cards
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

// Build Query
$sql = "
    SELECT c.*, a.id AS account_id, a.opening_balance, a.opening_balance_direction
    FROM customers c
    JOIN accounts a ON a.customer_id = c.id
    WHERE c.business_id = ? AND c.status = ?
";
$params = [$biz_id, $status];

if (!empty($search)) {
    $sql .= " AND (c.name LIKE ? OR c.mobile LIKE ? OR c.customer_code LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$raw_customers = $stmt->fetchAll();

// Calculate live authoritative balances
$customers = [];
$total_receivables = 0.00;

foreach ($raw_customers as $cust) {
    $cust['balance'] = calculate_customer_balance((int)$cust['account_id'], $pdo);
    if ($cust['balance'] > 0) {
        $total_receivables += $cust['balance'];
    }
    $customers[] = $cust;
}

// Sorting
if ($sort === 'balance_desc') {
    usort($customers, fn($a, $b) => $b['balance'] <=> $a['balance']);
} elseif ($sort === 'balance_asc') {
    usort($customers, fn($a, $b) => $a['balance'] <=> $b['balance']);
} elseif ($sort === 'name_desc') {
    usort($customers, fn($a, $b) => strcasecmp($b['name'], $a['name']));
} else {
    usort($customers, fn($a, $b) => strcasecmp($a['name'], $b['name']));
}

$page_title = __('customers');
include __DIR__ . '/includes/header.php';
?>

<div class="page-container">
  <div class="page-header">
    <div>
      <h1 class="page-title"><?= __('customers') ?></h1>
      <p class="page-subtitle">
        <?= count($customers) ?> <?= $status === 'archived' ? 'archived' : 'active' ?> customers • Total Receivable: <strong><?= format_currency($total_receivables) ?></strong>
      </p>
    </div>

    <!-- Strict No-Modal: Direct page link -->
    <a href="<?= BASE_URL ?>customer-add.php" class="btn btn-primary">
      ➕ <?= __('add_customer') ?>
    </a>
  </div>

  <!-- Search & Filter Controls -->
  <div class="card mb-3">
    <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 10px;">
      <div style="flex: 1; min-width: 220px;">
        <input 
          type="text" 
          id="customer-search-input"
          name="q" 
          class="form-control" 
          placeholder="<?= __('search') ?>" 
          value="<?= htmlspecialchars($search) ?>"
        >
      </div>

      <div style="width: 140px;">
        <select name="sort" class="form-control" onchange="this.form.submit()">
          <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
          <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
          <option value="balance_desc" <?= $sort === 'balance_desc' ? 'selected' : '' ?>>Highest Balance</option>
          <option value="balance_asc" <?= $sort === 'balance_asc' ? 'selected' : '' ?>>Lowest Balance</option>
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
        <a href="<?= BASE_URL ?>customers.php" class="btn btn-outline" style="color: var(--danger);">Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Customer Cards / Stack -->
  <?php if (empty($customers)): ?>
    <div class="card" style="text-align: center; padding: 40px 20px;">
      <p style="font-size: 16px; color: var(--text-muted); margin-bottom: 16px;">
        <?= !empty($search) ? 'No customers matching your search query.' : 'No customers found in your records.' ?>
      </p>
      <a href="<?= BASE_URL ?>customer-add.php" class="btn btn-primary" style="display: inline-flex;">
        ➕ Add Your First Customer
      </a>
    </div>
  <?php else: ?>
    <div class="ledger-stack" id="customer-list">
      <?php foreach ($customers as $c): ?>
        <div class="ledger-item customer-card-item <?= $c['balance'] > 0 ? 'credit' : ($c['balance'] < 0 ? 'payment' : '') ?>">
          <div class="item-main">
            <a href="<?= BASE_URL ?>customer-profile.php?id=<?= $c['id'] ?>" class="item-title" style="display: flex; align-items: center; gap: 8px;">
              <span><?= htmlspecialchars($c['name']) ?></span>
              <span class="badge badge-neutral" style="font-size: 11px;"><?= htmlspecialchars($c['customer_code']) ?></span>
            </a>
            <div class="item-sub">
              📞 <?= htmlspecialchars($c['mobile']) ?>
              <?php if (!empty($c['address'])): ?>
                • 📍 <?= htmlspecialchars(mb_strimwidth($c['address'], 0, 30, '...')) ?>
              <?php endif; ?>
            </div>
          </div>

          <div class="item-amount">
            <div class="amount-val <?= $c['balance'] > 0 ? 'text-credit' : ($c['balance'] < 0 ? 'text-payment' : 'text-muted') ?>">
              <?= format_currency($c['balance']) ?>
            </div>
            <div class="item-sub">
              <?= $c['balance'] > 0 ? 'Owes You' : ($c['balance'] < 0 ? 'Advance / Credit' : 'Cleared') ?>
            </div>
          </div>

          <div style="display: flex; gap: 6px; margin-left: 12px;">
            <a href="<?= BASE_URL ?>give-credit.php?customer_id=<?= $c['id'] ?>" class="btn btn-outline btn-sm" title="Give Udhaar" style="padding: 6px 8px; color: var(--credit);">
              +Udhaar
            </a>
            <a href="<?= BASE_URL ?>receive-payment.php?customer_id=<?= $c['id'] ?>" class="btn btn-outline btn-sm" title="Receive Cash" style="padding: 6px 8px; color: var(--payment);">
              +Wasool
            </a>
            <a href="<?= BASE_URL ?>customer-ledger.php?id=<?= $c['id'] ?>" class="btn btn-primary btn-sm" style="padding: 6px 10px;">
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
  HisabioLedger.initInstantSearch('#customer-search-input', '.customer-card-item', '.item-title');
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
