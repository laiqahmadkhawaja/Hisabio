<?php
/**
 * Hisabio - Financial & Ledger Reports
 * PRD Section 13: Customer & supplier summaries, cash movement vs credit distinction, CSV export
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$biz = current_business();
$biz_id = (int)$biz['id'];
$pdo = get_db();

// Period Filter
$period = $_GET['period'] ?? 'this_month';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

if ($period === 'today') {
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d');
} elseif ($period === 'yesterday') {
    $start_date = date('Y-m-d', strtotime('-1 day'));
    $end_date = date('Y-m-d', strtotime('-1 day'));
} elseif ($period === '7days') {
    $start_date = date('Y-m-d', strtotime('-7 days'));
    $end_date = date('Y-m-d');
} elseif ($period === '30days') {
    $start_date = date('Y-m-d', strtotime('-30 days'));
    $end_date = date('Y-m-d');
} elseif ($period === 'this_month') {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
} elseif ($period === 'last_month') {
    $start_date = date('Y-m-d', strtotime('first day of last month'));
    $end_date = date('Y-m-d', strtotime('last day of last month'));
}

// Fetch Aggregated Metrics for selected date range
$sql = "
    SELECT 
        SUM(CASE WHEN transaction_type = 'credit_sale' AND status = 'completed' THEN amount ELSE 0 END) AS credit_given,
        SUM(CASE WHEN transaction_type = 'payment_received' AND status = 'completed' THEN amount ELSE 0 END) AS cash_received,
        SUM(CASE WHEN transaction_type = 'supplier_purchase' AND status = 'completed' THEN amount ELSE 0 END) AS supplier_credit_purchase,
        SUM(CASE WHEN transaction_type = 'supplier_payment' AND status = 'completed' THEN amount ELSE 0 END) AS cash_paid_suppliers,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) AS total_tx_count
    FROM transactions
    WHERE business_id = ?
";
$params = [$biz_id];
if (!empty($start_date)) {
    $sql .= " AND transaction_date >= ?";
    $params[] = $start_date;
}
if (!empty($end_date)) {
    $sql .= " AND transaction_date <= ?";
    $params[] = $end_date;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$report_metrics = $stmt->fetch();

// Overall Current Position (Receivables & Payables across entire store)
$stats = get_business_stats($biz_id);

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Hisabio_Financial_Report_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Hisabio Financial Performance Report']);
    fputcsv($out, ['Business Name:', $biz['business_name']]);
    fputcsv($out, ['Report Period:', "$start_date to $end_date"]);
    fputcsv($out, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($out, []);
    fputcsv($out, ['Metric', 'Amount (PKR)']);
    fputcsv($out, ['Customer Credit Given (Udhaar Diya)', $report_metrics['credit_given']]);
    fputcsv($out, ['Customer Payments Received (Cash In)', $report_metrics['cash_received']]);
    fputcsv($out, ['Supplier Credit Purchases', $report_metrics['supplier_credit_purchase']]);
    fputcsv($out, ['Supplier Payments (Cash Out)', $report_metrics['cash_paid_suppliers']]);
    fputcsv($out, ['Total Receivables (Current Balance)', $stats['total_receivables']]);
    fputcsv($out, ['Total Payables (Current Balance)', $stats['total_payables']]);
    fclose($out);
    exit;
}

$page_title = __('reports');
include __DIR__ . '/includes/header.php';
?>

<div class="page-container">
  <div class="page-header no-print">
    <div>
      <h1 class="page-title">📈 <?= __('reports') ?> & Financial Insights</h1>
      <p class="page-subtitle">
        Performance from <?= format_date($start_date) ?> to <?= format_date($end_date) ?>
      </p>
    </div>

    <div style="display: flex; gap: 8px;">
      <button type="button" onclick="window.print()" class="btn btn-outline btn-sm">
        🖨️ Print Report
      </button>
      <a href="?period=<?= urlencode($period) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&export=csv" class="btn btn-primary btn-sm">
        📥 Export CSV
      </a>
    </div>
  </div>

  <!-- Period Filter Bar -->
  <div class="card no-print mb-3">
    <div class="filter-bar" style="margin-bottom: 10px;">
      <a href="?period=today" class="filter-tab <?= $period === 'today' ? 'active' : '' ?>">Today</a>
      <a href="?period=yesterday" class="filter-tab <?= $period === 'yesterday' ? 'active' : '' ?>">Yesterday</a>
      <a href="?period=7days" class="filter-tab <?= $period === '7days' ? 'active' : '' ?>">Last 7 Days</a>
      <a href="?period=this_month" class="filter-tab <?= $period === 'this_month' ? 'active' : '' ?>">This Month</a>
      <a href="?period=last_month" class="filter-tab <?= $period === 'last_month' ? 'active' : '' ?>">Last Month</a>
    </div>

    <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end;">
      <input type="hidden" name="period" value="custom">

      <div style="flex: 1; min-width: 150px;">
        <label class="form-label" style="font-size: 12px;">Custom Start Date</label>
        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
      </div>

      <div style="flex: 1; min-width: 150px;">
        <label class="form-label" style="font-size: 12px;">Custom End Date</label>
        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
      </div>

      <button type="submit" class="btn btn-outline" style="height: 46px;">Filter Range</button>
    </form>
  </div>

  <!-- Printable Business Header -->
  <div class="print-header" style="display: none; border-bottom: 2px solid var(--primary); padding-bottom: 14px; margin-bottom: 20px;">
    <div class="flex-between">
      <div>
        <h2 style="font-size: 24px; font-weight: 800; color: var(--primary);"><?= htmlspecialchars($biz['business_name']) ?></h2>
        <div style="font-size: 13px; color: var(--text-muted);"><?= htmlspecialchars($biz['address'] ?: 'Pakistan') ?> • <?= htmlspecialchars($biz['phone'] ?: '') ?></div>
      </div>
      <div style="text-align: right;">
        <div style="font-size: 18px; font-weight: 700;">FINANCIAL PERFORMANCE REPORT</div>
        <div style="font-size: 12px; color: var(--text-muted);"><?= format_date($start_date) ?> - <?= format_date($end_date) ?></div>
      </div>
    </div>
  </div>

  <!-- Accounting Distinction Banner (PRD Section 13.3) -->
  <div class="card" style="background: #FAFBFD; border-left: 4px solid var(--primary); padding: 14px; margin-bottom: 20px;">
    <div style="font-size: 13.5px; color: var(--text);">
      <strong>Accounting Clarification:</strong> In this khata system, <strong>Credit Sales</strong> represent goods given on deferred payment (receivable obligations) rather than liquid cash on hand. <strong>Cash Received</strong> represents actual counter collections, while <strong>Cash Paid</strong> indicates outflows to suppliers.
    </div>
  </div>

  <!-- High-Level Financial Summaries -->
  <div class="form-row">
    <!-- Customer Activity -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Customer Khata Activity</h2>
      </div>

      <div style="display: flex; flex-direction: column; gap: 14px;">
        <div class="flex-between">
          <span style="color: var(--text-muted); font-size: 14px;">Credit Given (Udhaar):</span>
          <span class="font-bold text-credit" style="font-size: 18px;"><?= format_currency($report_metrics['credit_given']) ?></span>
        </div>

        <div class="flex-between">
          <span style="color: var(--text-muted); font-size: 14px;">Payments Received (Cash In):</span>
          <span class="font-bold text-payment" style="font-size: 18px;"><?= format_currency($report_metrics['cash_received']) ?></span>
        </div>

        <div class="flex-between" style="border-top: 1px solid var(--border); padding-top: 10px;">
          <span style="font-weight: 600; font-size: 14px;">Total Store Receivables:</span>
          <span class="font-bold text-credit" style="font-size: 18px;"><?= format_currency($stats['total_receivables']) ?></span>
        </div>
      </div>

      <div class="mt-3">
        <a href="<?= BASE_URL ?>receivables.php" class="btn btn-outline btn-sm btn-block">
          View Receivables Breakdown →
        </a>
      </div>
    </div>

    <!-- Supplier Activity -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Supplier Khata Activity</h2>
      </div>

      <div style="display: flex; flex-direction: column; gap: 14px;">
        <div class="flex-between">
          <span style="color: var(--text-muted); font-size: 14px;">Credit Purchases:</span>
          <span class="font-bold text-credit" style="font-size: 18px;"><?= format_currency($report_metrics['supplier_credit_purchase']) ?></span>
        </div>

        <div class="flex-between">
          <span style="color: var(--text-muted); font-size: 14px;">Payments to Suppliers (Cash Out):</span>
          <span class="font-bold text-payment" style="font-size: 18px;"><?= format_currency($report_metrics['cash_paid_suppliers']) ?></span>
        </div>

        <div class="flex-between" style="border-top: 1px solid var(--border); padding-top: 10px;">
          <span style="font-weight: 600; font-size: 14px;">Total Store Payables:</span>
          <span class="font-bold text-payment" style="font-size: 18px;"><?= format_currency($stats['total_payables']) ?></span>
        </div>
      </div>

      <div class="mt-3">
        <a href="<?= BASE_URL ?>payables.php" class="btn btn-outline btn-sm btn-block">
          View Payables Breakdown →
        </a>
      </div>
    </div>
  </div>

  <!-- Cash Flow Activity Summary -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Liquid Cash Flow Summary</h2>
      <span style="font-size: 12px; color: var(--text-muted);"><?= $report_metrics['total_tx_count'] ?> completed transactions</span>
    </div>

    <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
      <tr style="border-bottom: 1px solid var(--border);">
        <td style="padding: 10px 0; color: var(--text);">Total Cash & Digital Payments In:</td>
        <td style="padding: 10px 0; text-align: right; font-weight: 700; color: var(--payment);">
          +<?= format_currency($report_metrics['cash_received']) ?>
        </td>
      </tr>
      <tr style="border-bottom: 1px solid var(--border);">
        <td style="padding: 10px 0; color: var(--text);">Total Payments Disbursed to Suppliers:</td>
        <td style="padding: 10px 0; text-align: right; font-weight: 700; color: var(--danger);">
          -<?= format_currency($report_metrics['cash_paid_suppliers']) ?>
        </td>
      </tr>
      <?php $net_cash = (float)$report_metrics['cash_received'] - (float)$report_metrics['cash_paid_suppliers']; ?>
      <tr style="background: #FAFBFD; font-weight: 800; font-size: 16px;">
        <td style="padding: 12px 0;">Net Counter Cash Inflow:</td>
        <td style="padding: 12px 0; text-align: right; color: <?= $net_cash >= 0 ? 'var(--payment)' : 'var(--danger)' ?>;">
          <?= format_currency($net_cash) ?>
        </td>
      </tr>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
