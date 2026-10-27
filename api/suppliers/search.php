<?php
/**
 * Hisabio API - Supplier Instant Autocomplete & Search
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}

$biz = current_business();
$biz_id = (int)$biz['id'];
$q = trim($_GET['q'] ?? '');
$pdo = get_db();

$sql = "
    SELECT s.id, s.name, s.mobile, s.supplier_code, a.id AS account_id
    FROM suppliers s
    JOIN accounts a ON a.supplier_id = s.id
    WHERE s.business_id = ? AND s.status = 'active'
";
$params = [$biz_id];

if (!empty($q)) {
    $sql .= " AND (s.name LIKE ? OR s.mobile LIKE ? OR s.supplier_code LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY s.name ASC LIMIT 25";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

$data = [];
foreach ($results as $r) {
    $bal = calculate_supplier_balance((int)$r['account_id'], $pdo);
    $data[] = [
        'id' => (int)$r['id'],
        'name' => $r['name'],
        'mobile' => $r['mobile'],
        'code' => $r['supplier_code'],
        'balance' => $bal,
        'formatted_balance' => format_currency($bal)
    ];
}

json_response([
    'success' => true,
    'data' => $data
]);
