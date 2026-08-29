<?php
// /api/admin/dashboard.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $data = [];
    
    // Total users
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users");
    $data['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Today registrations
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()");
    $data['today_registrations'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Pending withdrawals
    $stmt = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as amount FROM withdrawals WHERE status IN ('pending', 'under_review')");
    $withdraw = $stmt->fetch(PDO::FETCH_ASSOC);
    $data['pending_withdrawals'] = $withdraw['count'];
    $data['pending_amount'] = $withdraw['amount'];
    
    // Revenue
    $stmt = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM wallet_transactions WHERE transaction_type IN ('referral', 'task', 'bonus')");
    $data['revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total referrals
    $stmt = $conn->query("SELECT COUNT(*) as count FROM referrals");
    $data['total_referrals'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Recent activity
    $stmt = $conn->query("
        SELECT al.*, u.full_name 
        FROM activity_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        ORDER BY al.created_at DESC 
        LIMIT 15
    ");
    $data['recent_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fraud alerts
    $stmt = $conn->query("SELECT * FROM fraud_reports WHERE status = 'pending' ORDER BY created_at DESC LIMIT 5");
    $data['fraud_alerts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $data]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>