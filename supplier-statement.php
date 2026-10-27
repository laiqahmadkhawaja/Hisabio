<?php
/**
 * Hisabio - Supplier Account Statement (Printable & Exportable)
 * PRD Section 13.2: Supplier balance summary, ledger statement, print styles
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$biz = current_business();
$biz_id = (int)$biz['id'];
$supplier_id = (int)($_GET['id'] ?? 0);
$pdo = get_db();

$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$stmt = $pdo->prepare("
    SELECT s.*, a.id AS account_id, a.opening_balance, a.opening_balance_direction
    FROM suppliers s
    JOIN accounts a ON a.supplier_id = s.id
    WHERE s.id = ? AND s.business_id = ?
");
$stmt->execute([$supplier_id, $biz_id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    flash_message('error', 'Supplier not found.');
    header('Location: ' . BASE_URL . 'suppliers.php');
    exit;
}

$account_id = (int)$supplier['account_id'];

$sql = "
    SELECT t.*, u.full_name AS creator_name
    FROM transactions t
    LEFT JOIN users u ON u.id = t.created_by
    WHERE t.account_id = ?
";
$params = [$account_id];

if (!empty($start_date)) {
    $sql .= " AND t.transaction_date >= ?";
    $params[] = $start_date;
}
if (!empty($end_date)) {
    $sql .= " AND t.transaction_date <= ?";
    $params[] = $end_date;
}
$sql .= " ORDER BY t.transaction_date ASC, t.id ASC";

$stmtTx = $pdo->prepare($sql);
$stmtTx->execute($params);
$txs = $stmtTx->fetchAll();

// CSV Export Mode
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Supplier_Statement_' . preg_replace('/[^a-zA-Z0-9]/', '_', $supplier['name']) . '_' . date('Ymd') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Hisabio - Supplier Account Statement']);
    fputcsv($output, ['Business:', $biz['business_name']]);
    fputcsv($output, ['Supplier:', $supplier['name'], 'Mobile:', $supplier['mobile']]);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    fputcsv($output, ['Date', 'Reference', 'Type', 'Description', 'Method', 'Credit Purchases (+)', 'Payments (-)', 'Status']);

    foreach ($txs as $row) {
        $pur_amt = ($row['transaction_type'] === 'supplier_purchase') ? $row['amount'] : '';
        $pay_amt = ($row['transaction_type'] === 'supplier_payment') ? $row['amount'] : '';
        fputcsv($output, [
            $row['transaction_date'],
            $row['transaction_reference'],
            $row['transaction_type'],
            $row['description'],
            $row['payment_method'],
            $pur_amt,
            $pay_amt,
            $row['status']
        ]);
    }
    fclose($output);
    exit;
}

// Compute balances and totals
$running = 0.00;
if (empty($start_date)) {
    if ($supplier['opening_balance_direction'] === 'payable') {
        $running += (float)$supplier['opening_balance'];
    } elseif ($supplier['opening_balance_direction'] === 'receivable') {
        $running -= (float)$supplier['opening_balance'];
    }
}

$total_purchases = 0.00;
$total_payments = 0.00;
$entries = [];

foreach ($txs as $t) {
    $amt = (float)$t['amount'];
    if ($t['status'] === 'completed') {
        if ($t['transaction_type'] === 'supplier_purchase') {
            $running += $amt;
            $total_purchases += $amt;
        } elseif ($t['transaction_type'] === 'supplier_payment') {
            $running -= $amt;
            $total_payments += $amt;
        } elseif ($t['transaction_type'] === 'adjustment' || $t['transaction_type'] === 'reversal') {
            $running += $amt;
        }
    }
    $t['running_balance'] = $running;
    $entries[] = $t;
}

$page_title = 'Supplier Statement: ' . htmlspecialchars($supplier['name']);
include __DIR__ . '/includes/header.php';
?>

<div class="page-container" style="max-width: 860px;">
  <div class="page-header no-print">
    <div>
      <h1 class="page-title">🖨️ Supplier Account Statement</h1>
      <p class="page-subtitle"><?= htmlspecialchars($supplier['name']) ?> (<?= htmlspecialchars($supplier['supplier_code']) ?>)</p>
    </div>

    <div style="display: flex; gap: 8px;">
      <button type="button" onclick="window.print()" class="btn btn-primary btn-sm">
        🖨️ Print Statement
      </button>
      <a href="?id=<?= $supplier['id'] ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&export=csv" class="btn btn-outline btn-sm">
        📥 Export CSV
      </a>
      <a href="<?= BASE_URL ?>supplier-ledger.php?id=<?= $supplier['id'] ?>" class="btn btn-outline btn-sm">
        ← Back to Khata
      </a>
    </div>
  </div>

  <div class="card no-print mb-3">
    <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end;">
      <input type="hidden" name="id" value="<?= $supplier['id'] ?>">

      <div style="flex: 1; min-width: 150px;">
        <label class="form-label" style="font-size: 12px;">From Date</label>
        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
      </div>

      <div style="flex: 1; min-width: 150px;">
        <label class="form-label" style="font-size: 12px;">To Date</label>
        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
      </div>

      <button type="submit" class="btn btn-primary" style="height: 46px;">Filter Statement</button>
      <?php if (!empty($start_date) || !empty($end_date)): ?>
        <a href="?id=<?= $supplier['id'] ?>" class="btn btn-outline" style="height: 46px;">Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="card" style="padding: 24px;">
    <div class="print-header" style="border-bottom: 2px solid var(--primary); padding-bottom: 14px; margin-bottom: 20px;">
      <div class="flex-between">
        <div>
          <h2 style="font-size: 24px; font-weight: 800; color: var(--primary);">
            <?= htmlspecialchars($biz['business_name']) ?>
          </h2>
          <div style="font-size: 13px; color: var(--text-muted); margin-top: 2px;">
            <?= htmlspecialchars($biz['address'] ?: 'Pakistan') ?> • Phone: <?= htmlspecialchars($biz['phone'] ?: 'N/A') ?>
          </div>
        </div>
        <div style="text-align: right;">
          <div style="font-size: 18px; font-weight: 700; color: var(--text);">SUPPLIER STATEMENT</div>
          <div style="font-size: 12px; color: var(--text-muted);">
            Generated: <?= date('d M Y, h:i A') ?>
          </div>
        </div>
      </div>
    </div>

    <div class="card" style="background: #F9FAFB; border: 1px solid var(--border); padding: 14px; margin-bottom: 20px;">
      <div class="form-row" style="margin-bottom: 0;">
        <div>
          <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Supplier Info</div>
          <div style="font-size: 16px; font-weight: 700; color: var(--text);"><?= htmlspecialchars($supplier['name']) ?></div>
          <div style="font-size: 13px; color: var(--text);">Phone: <?= htmlspecialchars($supplier['mobile']) ?></div>
          <?php if (!empty($supplier['address'])): ?>
            <div style="font-size: 12.5px; color: var(--text-muted);"><?= htmlspecialchars($supplier['address']) ?></div>
          <?php endif; ?>
        </div>

        <div style="text-align: right;">
          <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Payable Summary</div>
          <div style="font-size: 13px; color: var(--text);">Opening: <?= format_currency($supplier['opening_balance']) ?></div>
          <div style="font-size: 18px; font-weight: 800; color: <?= $running > 0 ? 'var(--payment)' : 'var(--text)' ?>; margin-top: 4px;">
            Net Payable: <?= format_currency($running) ?>
          </div>
        </div>
      </div>
    </div>

    <table class="print-table" style="width: 100%; border-collapse: collapse; font-size: 13.5px;">
      <thead>
        <tr style="background: #F3F4F6; border-bottom: 2px solid var(--border);">
          <th style="padding: 10px; text-align: left;">Date</th>
          <th style="padding: 10px; text-align: left;">Ref #</th>
          <th style="padding: 10px; text-align: left;">Description</th>
          <th style="padding: 10px; text-align: right;">Purchases (+)</th>
          <th style="padding: 10px; text-align: right;">Payments (-)</th>
          <th style="padding: 10px; text-align: right;">Payable Balance</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($entries)): ?>
          <tr>
            <td colspan="6" style="padding: 20px; text-align: center; color: var(--text-muted);">
              No transactions recorded in this period.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($entries as $row): ?>
            <?php
              $is_pur = ($row['transaction_type'] === 'supplier_purchase');
              $is_pay = ($row['transaction_type'] === 'supplier_payment');
            ?>
            <tr style="border-bottom: 1px solid var(--border);">
              <td style="padding: 10px;"><?= format_date($row['transaction_date']) ?></td>
              <td style="padding: 10px; font-family: monospace; font-size: 12px;"><?= htmlspecialchars($row['transaction_reference']) ?></td>
              <td style="padding: 10px;">
                <?= htmlspecialchars($row['description']) ?>
                <?php if ($row['status'] === 'reversed'): ?>
                  <span class="badge badge-danger" style="font-size: 10px;">Reversed</span>
                <?php endif; ?>
              </td>
              <td style="padding: 10px; text-align: right; color: var(--credit); font-weight: <?= $is_pur ? '600' : 'normal' ?>;">
                <?= $is_pur ? format_currency($row['amount'], false) : '—' ?>
              </td>
              <td style="padding: 10px; text-align: right; color: var(--payment); font-weight: <?= $is_pay ? '600' : 'normal' ?>;">
                <?= $is_pay ? format_currency($row['amount'], false) : '—' ?>
              </td>
              <td style="padding: 10px; text-align: right; font-weight: 700;">
                <?= format_currency($row['running_balance'], false) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr style="background: #FAFBFD; border-top: 2px solid var(--border); font-weight: 700;">
          <td colspan="3" style="padding: 10px; text-align: right;">Period Totals:</td>
          <td style="padding: 10px; text-align: right; color: var(--credit);"><?= format_currency($total_purchases) ?></td>
          <td style="padding: 10px; text-align: right; color: var(--payment);"><?= format_currency($total_payments) ?></td>
          <td style="padding: 10px; text-align: right; font-size: 15px; color: var(--payment);">
            <?= format_currency($running) ?>
          </td>
        </tr>
      </tfoot>
    </table>

    <div class="print-footer" style="margin-top: 30px; text-align: center; font-size: 12px; color: var(--text-muted); border-top: 1px solid var(--border); padding-top: 14px;">
      <?= htmlspecialchars($biz['receipt_footer'] ?: 'Hisabio - Smart Digital Khata System') ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
