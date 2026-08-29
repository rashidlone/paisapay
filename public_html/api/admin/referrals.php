<?php
// /api/admin/referrals.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
    
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $stmt = $conn->query("
            SELECT r.*, 
                u1.full_name as referrer_name, 
                u2.full_name as referred_user_name
            FROM referrals r
            LEFT JOIN users u1 ON r.referrer_id = u1.id
            LEFT JOIN users u2 ON r.referred_user_id = u2.id
            ORDER BY r.created_at DESC
            LIMIT 100
        ");
        $referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $referrals]);
        exit;
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>