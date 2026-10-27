<?php
/**
 * Hisabio API - Dashboard Live Metrics
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
$filter = $_GET['filter'] ?? 'all';

$stats = get_business_stats($biz_id, $filter);
json_response([
    'success' => true,
    'data' => $stats
]);
