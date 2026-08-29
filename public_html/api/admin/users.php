<?php
// /api/admin/users.php

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
require_once $_SERVER['DOCUMENT_ROOT'] . '/classes/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/helpers/functions.php';

try {
    $conn = Database::getInstance()->getConnection();
    
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $stmt = $conn->query("
            SELECT u.*, 
                (SELECT COUNT(*) FROM referrals WHERE referrer_id = u.id) as referral_count,
                (SELECT COUNT(*) FROM task_history WHERE user_id = u.id AND is_claimed = 1) as task_count
            FROM users u
            ORDER BY u.id DESC
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $users]);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = isset($input['action']) ? $input['action'] : '';
        $user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;
        
        if ($action == 'toggle') {
            $stmt = $conn->prepare("SELECT is_blocked FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit;
            }
            
            $newStatus = $user['is_blocked'] ? 0 : 1;
            $updateStmt = $conn->prepare("UPDATE users SET is_blocked = ? WHERE id = ?");
            $updateStmt->execute([$newStatus, $user_id]);
            
            sendNotification(
                $user_id,
                $newStatus ? '🚫 Account Suspended' : '✅ Account Restored',
                $newStatus
                    ? 'Your account has been suspended. Contact support if you believe this is a mistake.'
                    : 'Your account access has been restored.'
            );
            
            echo json_encode(['success' => true, 'message' => 'User toggled']);
            exit;
        }
        
        if ($action == 'adjust_wallet') {
            $amount = isset($input['amount']) ? floatval($input['amount']) : 0;
            $stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
            $stmt->execute([$amount, $user_id]);
            echo json_encode(['success' => true, 'message' => 'Wallet adjusted']);
            exit;
        }
        
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    error_log('PDO error in ./api/admin/users.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again later.']);
}
?>