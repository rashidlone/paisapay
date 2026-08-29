<?php
// /api/admin/withdraw/action.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

$admin_id = $_SESSION['admin_id'];
$input = json_decode(file_get_contents('php://input'), true);
$withdrawal_id = isset($input['withdrawal_id']) ? intval($input['withdrawal_id']) : 0;
$action = isset($input['action']) ? $input['action'] : '';
$admin_notes = isset($input['admin_notes']) ? trim($input['admin_notes']) : '';

if (!$withdrawal_id || !$action) {
    echo json_encode(['success' => false, 'message' => 'Missing withdrawal_id or action']);
    exit;
}

try {
    $conn = Database::getInstance()->getConnection();
    
    // Get withdrawal details
    $getStmt = $conn->prepare("SELECT user_id, amount, status FROM withdrawals WHERE id = ?");
    $getStmt->execute([$withdrawal_id]);
    $withdrawal = $getStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$withdrawal) {
        echo json_encode(['success' => false, 'message' => 'Withdrawal not found']);
        exit;
    }
    
    $conn->beginTransaction();
    
    try {
        $new_status = '';
        $response_message = '';
        
        switch ($action) {
            case 'approve':
                $new_status = 'approved';
                $response_message = 'Withdrawal approved successfully';
                $notif_title = '✅ Withdrawal Approved';
                $notif_message = 'Your withdrawal of ' . formatCurrency($withdrawal['amount']) . ' has been approved and is being processed.';
                break;
                
            case 'reject':
                $new_status = 'rejected';
                // Refund amount to user's wallet
                $refundStmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                $refundStmt->execute([$withdrawal['amount'], $withdrawal['user_id']]);
                
                // Log refund transaction
                $logStmt = $conn->prepare("
                    INSERT INTO wallet_transactions (user_id, amount, transaction_type, description, balance_after, created_at) 
                    SELECT ?, ?, 'credit', CONCAT('Refund from rejected withdrawal #', ?), wallet_balance + ?, NOW()
                    FROM users WHERE id = ?
                ");
                $logStmt->execute([$withdrawal['user_id'], $withdrawal['amount'], $withdrawal_id, $withdrawal['amount'], $withdrawal['user_id']]);
                
                $response_message = 'Withdrawal rejected and amount refunded';
                $notif_title = '❌ Withdrawal Rejected';
                $notif_message = 'Your withdrawal of ' . formatCurrency($withdrawal['amount']) . ' was rejected and the amount has been refunded to your wallet.'
                    . ($admin_notes ? ' Reason: ' . $admin_notes : '');
                break;
                
            case 'paid':
                $new_status = 'paid';
                $response_message = 'Withdrawal marked as paid';
                $notif_title = '💸 Withdrawal Confirmed';
                $notif_message = 'Your withdrawal of ' . formatCurrency($withdrawal['amount']) . ' has been paid out.';
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit;
        }
        
        // Update withdrawal
        $updateStmt = $conn->prepare("
            UPDATE withdrawals 
            SET status = ?, admin_notes = ?, processed_by = ?, processed_at = NOW(), updated_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$new_status, $admin_notes, $admin_id, $withdrawal_id]);
        
        // ✅ FIX: Log activity with proper JSON format
        $details = json_encode([
            'withdrawal_id' => $withdrawal_id,
            'amount' => $withdrawal['amount'],
            'old_status' => $withdrawal['status'],
            'new_status' => $new_status,
            'admin_notes' => $admin_notes,
            'action' => $action
        ]);
        
        $logStmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, admin_id, action, details, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $logStmt->execute([$withdrawal['user_id'], $admin_id, 'withdrawal_' . $new_status, $details]);
        
        sendNotification($withdrawal['user_id'], $notif_title, $notif_message);
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => $response_message,
            'status' => $new_status
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log('PDO error in ./api/admin/withdraw/action.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again later.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>