<?php
/**
 * Hisabio - Financial Summary Dashboard
 * PRD Section 7: KPI Cards, Quick Actions, Recent Activity, Outstanding Balances
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$biz = current_business();
$biz_id = (int)$biz['id'];
$pdo = get_db();

// Period Filter Handling
$filter = $_GET['filter'] ?? 'all';
$stats = get_business_stats($biz_id, $filter);

// Fetch Recent Transactions
$stmtRecent = $pdo->prepare("
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
    ORDER BY t.transaction_date DESC, t.id DESC
    LIMIT 8
");
$stmtRecent->execute([$biz_id]);
$recent_txs = $stmtRecent->fetchAll();

// Fetch Top Debtors (Customers who owe the most)
$stmtCust = $pdo->prepare("
    SELECT c.id, c.name, c.mobile, c.customer_code, a.id AS account_id
    FROM customers c
    JOIN accounts a ON a.customer_id = c.id
    WHERE c.business_id = ? AND c.status = 'active'
");
$stmtCust->execute([$biz_id]);
$all_customers = $stmtCust->fetchAll();

$debtor_customers = [];
foreach ($all_customers as $cust) {
    $bal = calculate_customer_balance((int)$cust['account_id'], $pdo);
    if ($bal > 0) {
        $cust['balance'] = $bal;
        $debtor_customers[] = $cust;
    }
}
usort($debtor_customers, fn($a, $b) => $b['balance'] <=> $a['balance']);
$top_debtors = array_slice($debtor_customers, 0, 5);

// Fetch Top Creditors (Suppliers we owe the most)
$stmtSupp = $pdo->prepare("
    SELECT s.id, s.name, s.mobile, s.supplier_code, a.id AS account_id
    FROM suppliers s
    JOIN accounts a ON a.supplier_id = s.id
    WHERE s.business_id = ? AND s.status = 'active'
");
$stmtSupp->execute([$biz_id]);
$all_suppliers = $stmtSupp->fetchAll();

$payable_suppliers = [];
foreach ($all_suppliers as $supp) {
    $bal = calculate_supplier_balance((int)$supp['account_id'], $pdo);
    if ($bal > 0) {
        $supp['balance'] = $bal;
        $payable_suppliers[] = $supp;
    }
}
usort($payable_suppliers, fn($a, $b) => $b['balance'] <=> $a['balance']);
$top_payables = array_slice($payable_suppliers, 0, 5);

$page_title = __('dashboard');
include __DIR__ . '/includes/header.php';
?>

<div class="page-container">
  <!-- Business Welcome & Date Filter -->
  <div class="page-header">
    <div>
      <h1 class="page-title">
        <?= htmlspecialchars($biz['business_name']) ?>
      </h1>
      <p class="page-subtitle">
        <?= __('tagline') ?> • <?= date('l, d F Y') ?>
      </p>
    </div>

    <!-- Period Filters -->
    <div class="filter-bar no-print">
      <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>"><?= __('filter_all') ?></a>
      <a href="?filter=today" class="filter-tab <?= $filter === 'today' ? 'active' : '' ?>"><?= __('filter_today') ?></a>
      <a href="?filter=7days" class="filter-tab <?= $filter === '7days' ? 'active' : '' ?>"><?= __('filter_7days') ?></a>
      <a href="?filter=this_month" class="filter-tab <?= $filter === 'this_month' ? 'active' : '' ?>"><?= __('filter_this_month') ?></a>
    </div>
  </div>

  <!-- Primary Financial KPI Cards -->
  <div class="kpi-grid">
    <!-- Customer Receivables -->
    <a href="<?= BASE_URL ?>receivables.php" class="kpi-card receivable" style="text-decoration: none;">
      <span class="kpi-label"><?= __('total_receivables') ?></span>
      <span class="kpi-value credit"><?= format_currency($stats['total_receivables']) ?></span>
      <span class="kpi-meta"><?= $stats['active_customers'] ?> <?= __('active_customers') ?></span>
    </a>

    <!-- Supplier Payables -->
    <a href="<?= BASE_URL ?>payables.php" class="kpi-card payable" style="text-decoration: none;">
      <span class="kpi-label"><?= __('total_payables') ?></span>
      <span class="kpi-value payment"><?= format_currency($stats['total_payables']) ?></span>
      <span class="kpi-meta"><?= $stats['active_suppliers'] ?> <?= __('active_suppliers') ?></span>
    </a>

    <!-- Today's Credit Given -->
    <div class="kpi-card">
      <span class="kpi-label"><?= __('today_credit') ?></span>
      <span class="kpi-value text-credit"><?= format_currency($stats['today_credit']) ?></span>
      <span class="kpi-meta">Recorded today</span>
    </div>

    <!-- Today's Payment Received -->
    <div class="kpi-card">
      <span class="kpi-label"><?= __('today_received') ?></span>
      <span class="kpi-value text-payment"><?= format_currency($stats['today_received']) ?></span>
      <span class="kpi-meta">Counter & digital collections</span>
    </div>
  </div>

  <!-- Secondary Metrics Strip -->
  <div class="kpi-grid" style="grid-template-columns: repeat(2, 1fr); margin-top: -8px;">
    <div class="kpi-card" style="padding: 10px 14px;">
      <span class="kpi-label"><?= __('today_paid') ?></span>
      <span class="kpi-value" style="font-size: 16px; color: var(--text);"><?= format_currency($stats['today_paid']) ?></span>
    </div>

    <a href="<?= BASE_URL ?>overdue.php" class="kpi-card" style="padding: 10px 14px; text-decoration: none; border-left: 3px solid var(--danger);">
      <span class="kpi-label"><?= __('overdue_balances') ?></span>
      <span class="kpi-value text-danger" style="font-size: 16px;"><?= format_currency($stats['overdue_amount']) ?></span>
    </a>
  </div>

  <!-- Quick Actions Grid (PRD 7.2 - Strict No Modal Policy) -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title"><?= __('quick_actions') ?></h2>
      <span style="font-size: 12px; color: var(--text-muted);">Dedicated Fast Entry</span>
    </div>

    <div class="action-grid">
      <a href="<?= BASE_URL ?>give-credit.php" class="action-card">
        <div class="action-icon credit">📤</div>
        <span><?= __('give_credit') ?></span>
      </a>

      <a href="<?= BASE_URL ?>receive-payment.php" class="action-card">
        <div class="action-icon payment">📥</div>
        <span><?= __('receive_payment') ?></span>
      </a>

      <a href="<?= BASE_URL ?>customer-add.php" class="action-card">
        <div class="action-icon customer">➕</div>
        <span><?= __('add_customer') ?></span>
      </a>

      <a href="<?= BASE_URL ?>supplier-add.php" class="action-card">
        <div class="action-icon supplier">🏢</div>
        <span><?= __('add_supplier') ?></span>
      </a>

      <a href="<?= BASE_URL ?>supplier-purchase.php" class="action-card">
        <div class="action-icon credit">📦</div>
        <span><?= __('record_purchase') ?></span>
      </a>

      <a href="<?= BASE_URL ?>supplier-payment.php" class="action-card">
        <div class="action-icon payment">💳</div>
        <span><?= __('pay_supplier') ?></span>
      </a>

      <a href="<?= BASE_URL ?>reports.php" class="action-card">
        <div class="action-icon customer">📊</div>
        <span><?= __('reports') ?></span>
      </a>

      <a href="<?= BASE_URL ?>reminders.php" class="action-card">
        <div class="action-icon payment">💬</div>
        <span><?= __('reminders') ?></span>
      </a>
    </div>
  </div>

  <!-- Outstanding Debtors & Payables Side-by-Side (or stacked on mobile) -->
  <div class="form-row">
    <!-- Top Customer Receivables -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Customers Owing Money</h2>
        <a href="<?= BASE_URL ?>receivables.php" style="font-size: 12px; font-weight: 600;"><?= __('view_all') ?></a>
      </div>

      <?php if (empty($top_debtors)): ?>
        <p style="font-size: 13.5px; color: var(--text-muted); text-align: center; padding: 16px 0;">
          All customer accounts are settled! No pending receivables.
        </p>
      <?php else: ?>
        <div class="ledger-stack">
          <?php foreach ($top_debtors as $debtor): ?>
            <div class="ledger-item credit">
              <div class="item-main">
                <a href="<?= BASE_URL ?>customer-ledger.php?id=<?= $debtor['id'] ?>" class="item-title" style="display: block;">
                  <?= htmlspecialchars($debtor['name']) ?>
                </a>
                <div class="item-sub">
                  <?= htmlspecialchars($debtor['mobile']) ?> • <?= htmlspecialchars($debtor['customer_code']) ?>
                </div>
              </div>

              <div class="item-amount">
                <div class="amount-val text-credit"><?= format_currency($debtor['balance']) ?></div>
                <div class="item-sub">Receivable</div>
              </div>

              <div style="margin-left: 10px;">
                <?php 
                  $msg = sprintf(
                    "Assalam-o-Alaikum %s sahib! This is a reminder from %s. Your outstanding balance is %s. Please contact us for details. JazakAllah khair.",
                    $debtor['name'],
                    $biz['business_name'],
                    format_currency($debtor['balance'])
                  );
                ?>
                <a href="<?= build_whatsapp_link($debtor['mobile'], $msg) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp btn-sm" title="WhatsApp Reminder" style="padding: 6px 10px;">
                  💬
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Top Supplier Payables -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Suppliers To Be Paid</h2>
        <a href="<?= BASE_URL ?>payables.php" style="font-size: 12px; font-weight: 600;"><?= __('view_all') ?></a>
      </div>

      <?php if (empty($top_payables)): ?>
        <p style="font-size: 13.5px; color: var(--text-muted); text-align: center; padding: 16px 0;">
          No pending supplier dues. Everything is paid up!
        </p>
      <?php else: ?>
        <div class="ledger-stack">
          <?php foreach ($top_payables as $payable): ?>
            <div class="ledger-item payment">
              <div class="item-main">
                <a href="<?= BASE_URL ?>supplier-ledger.php?id=<?= $payable['id'] ?>" class="item-title" style="display: block;">
                  <?= htmlspecialchars($payable['name']) ?>
                </a>
                <div class="item-sub">
                  <?= htmlspecialchars($payable['mobile']) ?> • <?= htmlspecialchars($payable['supplier_code']) ?>
                </div>
              </div>

              <div class="item-amount">
                <div class="amount-val text-payment"><?= format_currency($payable['balance']) ?></div>
                <div class="item-sub">You Owe</div>
              </div>

              <div style="margin-left: 10px;">
                <a href="<?= BASE_URL ?>supplier-payment.php?supplier_id=<?= $payable['id'] ?>" class="btn btn-outline-primary btn-sm" style="padding: 6px 10px;">
                  Pay
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent Activity Stream -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title"><?= __('recent_activity') ?></h2>
      <a href="<?= BASE_URL ?>transactions.php" style="font-size: 12px; font-weight: 600;"><?= __('view_all') ?></a>
    </div>

    <?php if (empty($recent_txs)): ?>
      <p style="font-size: 13.5px; color: var(--text-muted); text-align: center; padding: 24px 0;">
        <?= __('no_records') ?> Start by giving credit or receiving payment above.
      </p>
    <?php else: ?>
      <div class="ledger-stack">
        <?php foreach ($recent_txs as $tx): ?>
          <?php
            $is_customer = $tx['account_type'] === 'customer';
            $party_name = $is_customer ? ($tx['customer_name'] ?? 'Unknown Customer') : ($tx['supplier_name'] ?? 'Unknown Supplier');
            $party_phone = $is_customer ? ($tx['customer_mobile'] ?? '') : ($tx['supplier_mobile'] ?? '');
            
            $type_label = match($tx['transaction_type']) {
                'credit_sale' => __('credit_sale'),
                'payment_received' => __('payment_received'),
                'supplier_purchase' => __('supplier_purchase'),
                'supplier_payment' => __('supplier_payment'),
                'opening_balance' => __('opening_balance'),
                'reversal' => __('reversal'),
                default => ucfirst(str_replace('_', ' ', $tx['transaction_type']))
            };

            $is_credit_type = in_array($tx['transaction_type'], ['credit_sale', 'supplier_purchase']);
            $badge_class = $is_credit_type ? 'badge-credit' : 'badge-payment';
            if ($tx['status'] === 'reversed') $badge_class = 'badge-danger';
          ?>
          <a href="<?= BASE_URL ?>transaction-details.php?id=<?= $tx['id'] ?>" class="ledger-item <?= $is_credit_type ? 'credit' : 'payment' ?>" style="text-decoration: none;">
            <div class="item-main">
              <div class="item-title">
                <?= htmlspecialchars($party_name) ?>
                <span class="badge <?= $badge_class ?>" style="margin-left: 6px; font-size: 11px;">
                  <?= $type_label ?>
                </span>
              </div>
              <div class="item-sub">
                <?= format_date($tx['transaction_date']) ?> • Ref: <?= htmlspecialchars($tx['transaction_reference']) ?>
                <?php if (!empty($tx['payment_method'])): ?>
                  • <?= htmlspecialchars($tx['payment_method']) ?>
                <?php endif; ?>
              </div>
            </div>

            <div class="item-amount">
              <div class="amount-val <?= $is_credit_type ? 'text-credit' : 'text-payment' ?>">
                <?= $is_credit_type ? '+' : '-' ?><?= format_currency($tx['amount']) ?>
              </div>
              <div class="item-sub"><?= htmlspecialchars($tx['creator_name'] ?? 'Staff') ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
