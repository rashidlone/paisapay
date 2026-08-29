<?php
// /api/admin/withdrawals.php

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

$admin_id = $_SESSION['admin_id'];

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // GET - List withdrawals
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $status = isset($_GET['status']) && $_GET['status'] != 'all' ? $_GET['status'] : '';
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        // View single withdrawal
        if ($action == 'view' && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("
                SELECT w.*, u.full_name, u.email, u.phone_number, u.wallet_balance, u.total_earnings,
                    (SELECT COUNT(*) FROM task_history WHERE user_id = u.id AND is_claimed = 1) as total_tasks,
                    (SELECT COUNT(*) FROM referrals WHERE referrer_id = u.id AND validation_status = 'verified') as verified_referrals
                FROM withdrawals w
                JOIN users u ON w.user_id = u.id
                WHERE w.id = ?
            ");
            $stmt->execute([$id]);
            $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$withdrawal) {
                echo json_encode(['success' => false, 'message' => 'Withdrawal not found']);
                exit;
            }
            
            // Get referrals for this user
            $refStmt = $conn->prepare("
                SELECT u.full_name as name, r.referred_user_tasks_completed as tasks, r.validation_status as status
                FROM referrals r
                JOIN users u ON r.referred_user_id = u.id
                WHERE r.referrer_id = ?
                ORDER BY r.created_at DESC
                LIMIT 20
            ");
            $refStmt->execute([$withdrawal['user_id']]);
            $withdrawal['referrals'] = $refStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Parse requirements
            if ($withdrawal['requirements_met']) {
                $withdrawal['requirements'] = json_decode($withdrawal['requirements_met'], true);
                unset($withdrawal['requirements_met']);
            }
            
            echo json_encode(['success' => true, 'data' => $withdrawal]);
            exit;
        }
        
        // List all withdrawals
        $query = "
            SELECT w.*, u.full_name, u.email,
                (SELECT COUNT(*) FROM task_history WHERE user_id = u.id AND is_claimed = 1) as total_tasks,
                (SELECT COUNT(*) FROM referrals WHERE referrer_id = u.id AND validation_status = 'verified') as verified_referrals
            FROM withdrawals w
            JOIN users u ON w.user_id = u.id
        ";
        
        if ($status) {
            $query .= " WHERE w.status = '" . $conn->quote($status) . "'";
        }
        
        $query .= " ORDER BY w.created_at DESC";
        
        $stmt = $conn->query($query);
        $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $withdrawals]);
        exit;
    }
    
    // POST - Update withdrawal
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $withdrawal_id = isset($input['withdrawal_id']) ? intval($input['withdrawal_id']) : 0;
        $status = isset($input['status']) ? $input['status'] : '';
        $admin_notes = isset($input['admin_notes']) ? trim($input['admin_notes']) : '';
        
        if (!$withdrawal_id || !$status) {
            echo json_encode(['success' => false, 'message' => 'Missing fields']);
            exit;
        }
        
        $conn->beginTransaction();
        
        try {
            // Get current status and user
            $getStmt = $conn->prepare("SELECT user_id, amount, status FROM withdrawals WHERE id = ?");
            $getStmt->execute([$withdrawal_id]);
            $withdrawal = $getStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$withdrawal) {
                echo json_encode(['success' => false, 'message' => 'Withdrawal not found']);
                exit;
            }
            
            $new_status = '';
            $message = '';
            
            switch ($status) {
                case 'approve':
                    $new_status = 'approved';
                    $message = 'Withdrawal approved';
                    break;
                    
                case 'reject':
                    $new_status = 'rejected';
                    // Refund amount
                    $refundStmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                    $refundStmt->execute([$withdrawal['amount'], $withdrawal['user_id']]);
                    
                    // Log refund
                    $logStmt = $conn->prepare("
                        INSERT INTO wallet_transactions (user_id, amount, transaction_type, description, balance_after, created_at) 
                        SELECT ?, ?, 'credit', CONCAT('Refund from rejected withdrawal #', ?), wallet_balance + ?, NOW()
                        FROM users WHERE id = ?
                    ");
                    $logStmt->execute([$withdrawal['user_id'], $withdrawal['amount'], $withdrawal_id, $withdrawal['amount'], $withdrawal['user_id']]);
                    $message = 'Withdrawal rejected and refunded';
                    break;
                    
                case 'paid':
                    $new_status = 'paid';
                    $message = 'Withdrawal marked as paid';
                    break;
                    
                default:
                    echo json_encode(['success' => false, 'message' => 'Invalid status']);
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
                'admin_notes' => $admin_notes
            ]);
            
            $logStmt = $conn->prepare("
                INSERT INTO activity_logs (user_id, admin_id, action, details, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $logStmt->execute([$withdrawal['user_id'], $admin_id, 'withdrawal_' . $new_status, $details]);
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => $message,
                'status' => $new_status
            ]);
            
        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
        exit;
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>