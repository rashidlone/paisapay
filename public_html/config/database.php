<?php
// /api/admin/dashboard.php - DEBUG VERSION

// Show all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

// Check if config files exist
$config_file = __DIR__ . '/../../config/config.php';
$database_file = __DIR__ . '/../../config/database.php';

if (!file_exists($config_file)) {
    echo json_encode(['success' => false, 'message' => 'config.php not found at: ' . $config_file]);
    exit;
}

if (!file_exists($database_file)) {
    echo json_encode(['success' => false, 'message' => 'database.php not found at: ' . $database_file]);
    exit;
}

// Include files
require_once $config_file;
require_once $database_file;

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Test simple query
try {
    $test = $conn->query("SELECT 1")->fetchColumn();
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Query failed: ' . $e->getMessage()]);
    exit;
}

// Get stats
try {
    $total_users = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $pending_withdrawals = $conn->query("SELECT COUNT(*) FROM withdrawals WHERE status='pending'")->fetchColumn();
    $total_referrals = $conn->query("SELECT COUNT(*) FROM referrals")->fetchColumn();
    $revenue = $conn->query("SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE transaction_type IN ('referral','task','bonus')")->fetchColumn();
    $recent = $conn->query("SELECT action, full_name, created_at FROM activity_logs al JOIN users u ON al.user_id = u.id ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Stats query failed: ' . $e->getMessage()]);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => [
        'total_users' => (int)$total_users,
        'pending_withdrawals' => (int)$pending_withdrawals,
        'total_referrals' => (int)$total_referrals,
        'revenue' => (float)$revenue,
        'recent_activity' => $recent
    ]
]);
?>