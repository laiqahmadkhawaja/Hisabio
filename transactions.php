<?php
/**
 * Hisabio - Unified Transaction Management & History
 * PRD Section 11.1: Multi-filter transaction listing, customer/supplier unified search
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$biz = current_business();
$biz_id = (int)$biz['id'];
$pdo = get_db();

// Filters
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$type = $_GET['type'] ?? '';
$method = $_GET['method'] ?? '';
$search = trim($_GET['q'] ?? '');

$sql = "
    SELECT t.*, 
           a.account_type,
           c.id AS customer_id, c.name AS customer_name, c.mobile AS customer_mobile,
           s.id AS supplier_id, s.name AS supplier_name, s.mobile AS supplier_mobile,
           u.full_name AS creator_name
    FROM transactions t
    JOIN accounts a ON a.id = t.account_id
    LEFT JOIN customers c ON c.id = a.customer_id
    LEFT JOIN suppliers s ON s.id = a.supplier_id
    LEFT JOIN users u ON u.id = t.created_by
    WHERE t.business_id = ?
";
$params = [$biz_id];

if (!empty($start_date)) {
    $sql .= " AND t.transaction_date >= ?";
    $params[] = $start_date;
}
if (!empty($end_date)) {
    $sql .= " AND t.transaction_date <= ?";
    $params[] = $end_date;
}
if (!empty($type)) {
    $sql .= " AND t.transaction_type = ?";
    $params[] = $type;
}
if (!empty($method)) {
    $sql .= " AND t.payment_method = ?";
    $params[] = $method;
}
if (!empty($search)) {
    $sql .= " AND (t.transaction_reference LIKE ? OR t.reference_number LIKE ? OR t.description LIKE ? OR c.name LIKE ? OR s.name LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY t.transaction_date DESC, t.id DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Total in current filter
$total_in = 0.00;
$total_out = 0.00;
foreach ($transactions as $t) {
    if ($t['status'] === 'completed') {
        if (in_array($t['transaction_type'], ['payment_received'])) {
            $total_in += (float)$t['amount'];
        } elseif (in_array($t['transaction_type'], ['supplier_payment'])) {
            $total_out += (float)$t['amount'];
        }
    }
}

$page_title = __('transactions');
include __DIR__ . '/includes/header.php';
?>

<div class="page-container">
  <div class="page-header">
    <div>
      <h1 class="page-title">📑 <?= __('transactions') ?></h1>
      <p class="page-subtitle">
        Showing <?= count($transactions) ?> transactions • Cash In: <strong><?= format_currency($total_in) ?></strong> | Cash Out: <strong><?= format_currency($total_out) ?></strong>
      </p>
    </div>

    <div style="display: flex; gap: 8px;">
      <a href="<?= BASE_URL ?>give-credit.php" class="btn btn-outline-primary btn-sm">
        +Udhaar
      </a>
      <a href="<?= BASE_URL ?>receive-payment.php" class="btn btn-primary btn-sm">
        +Wasool
      </a>
    </div>
  </div>

  <!-- Multi-Filter Inline Panel (Strict No Modal) -->
  <div class="card mb-3">
    <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 10px;">
      <div style="flex: 2; min-width: 180px;">
        <input 
          type="text" 
          name="q" 
          class="form-control" 
          placeholder="Search ref #, party name, or details..." 
          value="<?= htmlspecialchars($search) ?>"
        >
      </div>

      <div style="flex: 1; min-width: 130px;">
        <select name="type" class="form-control" onchange="this.form.submit()">
          <option value="">All Types</option>
          <option value="credit_sale" <?= $type === 'credit_sale' ? 'selected' : '' ?>>Credit Sale (Udhaar)</option>
          <option value="payment_received" <?= $type === 'payment_received' ? 'selected' : '' ?>>Payment Received</option>
          <option value="supplier_purchase" <?= $type === 'supplier_purchase' ? 'selected' : '' ?>>Supplier Purchase</option>
          <option value="supplier_payment" <?= $type === 'supplier_payment' ? 'selected' : '' ?>>Supplier Payment</option>
          <option value="reversal" <?= $type === 'reversal' ? 'selected' : '' ?>>Reversals</option>
          <option value="opening_balance" <?= $type === 'opening_balance' ? 'selected' : '' ?>>Opening Balances</option>
        </select>
      </div>

      <div style="flex: 1; min-width: 130px;">
        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>" title="From Date">
      </div>

      <div style="flex: 1; min-width: 130px;">
        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>" title="To Date">
      </div>

      <button type="submit" class="btn btn-outline" style="min-width: 80px;">Filter</button>
      <?php if (!empty($search) || !empty($type) || !empty($start_date) || !empty($end_date)): ?>
        <a href="<?= BASE_URL ?>transactions.php" class="btn btn-outline" style="color: var(--danger);">Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Transaction Stack (Cards on mobile, structured rows on desktop) -->
  <?php if (empty($transactions)): ?>
    <div class="card" style="text-align: center; padding: 40px 16px; color: var(--text-muted);">
      <p style="font-size: 15px;">No transactions found matching your criteria.</p>
    </div>
  <?php else: ?>
    <div class="ledger-stack">
      <?php foreach ($transactions as $tx): ?>
        <?php
          $is_customer = ($tx['account_type'] === 'customer');
          $party = $is_customer ? ($tx['customer_name'] ?? 'Customer') : ($tx['supplier_name'] ?? 'Supplier');
          $party_url = $is_customer ? (BASE_URL . 'customer-ledger.php?id=' . $tx['customer_id']) : (BASE_URL . 'supplier-ledger.php?id=' . $tx['supplier_id']);
          
          $is_credit_type = in_array($tx['transaction_type'], ['credit_sale', 'supplier_purchase']);
          $is_payment_type = in_array($tx['transaction_type'], ['payment_received', 'supplier_payment']);
          $is_reversed = ($tx['status'] === 'reversed');

          $type_title = match($tx['transaction_type']) {
              'credit_sale' => 'Udhaar Diya',
              'payment_received' => 'Raqam Wasool',
              'supplier_purchase' => 'Credit Purchase',
              'supplier_payment' => 'Supplier Payment',
              'opening_balance' => 'Opening Balance',
              'reversal' => 'Reversal',
              default => ucfirst($tx['transaction_type'])
          };
        ?>
        <div class="ledger-item <?= $is_reversed ? 'reversal' : ($is_credit_type ? 'credit' : 'payment') ?>">
          <div class="item-main">
            <div class="flex-between" style="justify-content: flex-start; gap: 8px;">
              <a href="<?= $party_url ?>" class="item-title" style="text-decoration: underline;">
                <?= htmlspecialchars($party) ?>
              </a>
              <span class="badge <?= $is_credit_type ? 'badge-credit' : 'badge-payment' ?>" style="font-size: 11px;">
                <?= $type_title ?>
              </span>
              <?php if ($is_reversed): ?>
                <span class="badge badge-danger" style="font-size: 11px;">REVERSED</span>
              <?php endif; ?>
            </div>

            <div class="item-sub" style="margin-top: 4px;">
              <?= format_date($tx['transaction_date']) ?>
              • Ref: <code><?= htmlspecialchars($tx['transaction_reference']) ?></code>
              <?php if (!empty($tx['description'])): ?>
                • <?= htmlspecialchars(mb_strimwidth($tx['description'], 0, 40, '...')) ?>
              <?php endif; ?>
              <?php if (!empty($tx['payment_method'])): ?>
                • <?= htmlspecialchars($tx['payment_method']) ?>
              <?php endif; ?>
            </div>
          </div>

          <div class="item-amount">
            <div class="amount-val <?= $is_credit_type ? 'text-credit' : 'text-payment' ?>" style="<?= $is_reversed ? 'text-decoration: line-through; opacity: 0.6;' : '' ?>">
              <?= $is_credit_type ? '+' : '-' ?><?= format_currency($tx['amount']) ?>
            </div>
            <div class="item-sub">By <?= htmlspecialchars($tx['creator_name'] ?? 'Staff') ?></div>
          </div>

          <div style="margin-left: 10px;">
            <a href="<?= BASE_URL ?>transaction-details.php?id=<?= $tx['id'] ?>" class="btn btn-outline btn-sm" title="View Details / Receipt">
              Details
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
