<?php
// /api/withdraw/request.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/helpers/mailer.php';

// Get token
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$token = $matches[1];
$decoded = json_decode(base64_decode($token), true);

if (!$decoded || !isset($decoded['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

$user_id = $decoded['user_id'];

$input = json_decode(file_get_contents('php://input'), true);
$amount = isset($input['amount']) ? floatval($input['amount']) : 0;
$payment_method = isset($input['payment_method']) ? $input['payment_method'] : '';
$account_details = isset($input['account_details']) ? $input['account_details'] : [];

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get user data
    $userStmt = $conn->prepare("
        SELECT id, full_name, email, wallet_balance, is_verified, email_verified 
        FROM users WHERE id = ?
    ");
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Check if user has any pending withdrawals
    $pendingStmt = $conn->prepare("
        SELECT COUNT(*) as count FROM withdrawals 
        WHERE user_id = ? AND status IN ('pending', 'under_review')
    ");
    $pendingStmt->execute([$user_id]);
    $pending = $pendingStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pending['count'] > 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'You already have a pending withdrawal request. Please wait for it to be processed.'
        ]);
        exit;
    }
    
    // Get current period requirements (tasks and referrals since last withdrawal)
    $periodStmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM task_history 
             WHERE user_id = ? AND is_claimed = 1 
             AND completed_at > COALESCE(
                 (SELECT MAX(created_at) FROM withdrawals 
                  WHERE user_id = ? AND status IN ('paid', 'approved')),
                 '1970-01-01'
             )
            ) as tasks_since_last_withdrawal,
            
            (SELECT COUNT(*) FROM referrals 
             WHERE referrer_id = ? 
             AND created_at > COALESCE(
                 (SELECT MAX(created_at) FROM withdrawals 
                  WHERE user_id = ? AND status IN ('paid', 'approved')),
                 '1970-01-01'
             )
            ) as referrals_since_last_withdrawal,
            
            (SELECT COUNT(*) FROM referrals 
             WHERE referrer_id = ? 
             AND created_at > COALESCE(
                 (SELECT MAX(created_at) FROM withdrawals 
                  WHERE user_id = ? AND status IN ('paid', 'approved')),
                 '1970-01-01'
             )
             AND validation_status = 'verified'
            ) as verified_referrals_since_last_withdrawal
    ");
    $periodStmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
    $periodData = $periodStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get requirements from settings
    $settingsStmt = $conn->prepare("
        SELECT setting_key, setting_value FROM settings 
        WHERE setting_key IN ('required_tasks', 'required_referrals', 'min_withdrawal', 'max_withdrawal')
    ");
    $settingsStmt->execute();
    $settings = [];
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $required_tasks = $settings['required_tasks'] ?? 10;
    $required_referrals = $settings['required_referrals'] ?? 5;
    $min_withdrawal = $settings['min_withdrawal'] ?? 50;
    $max_withdrawal = $settings['max_withdrawal'] ?? 50000;
    
    // Check requirements
    $tasks_met = $periodData['tasks_since_last_withdrawal'] >= $required_tasks;
    $referrals_met = $periodData['verified_referrals_since_last_withdrawal'] >= $required_referrals;
    $amount_valid = $amount >= $min_withdrawal && $amount <= $max_withdrawal && $amount <= $user['wallet_balance'];
    
    $requirements = [
        'met' => $tasks_met && $referrals_met && $amount_valid,
        'details' => [
            'tasks' => [
                'required' => $required_tasks,
                'current' => intval($periodData['tasks_since_last_withdrawal']),
                'met' => $tasks_met
            ],
            'referrals' => [
                'required' => $required_referrals,
                'current' => intval($periodData['verified_referrals_since_last_withdrawal']),
                'total' => intval($periodData['referrals_since_last_withdrawal']),
                'met' => $referrals_met
            ],
            'amount' => [
                'min' => $min_withdrawal,
                'max' => $max_withdrawal,
                'current' => $amount,
                'met' => $amount_valid
            ]
        ],
        'last_withdrawal_date' => $conn->query("
            SELECT MAX(created_at) FROM withdrawals 
            WHERE user_id = $user_id AND status IN ('paid', 'approved')
        ")->fetchColumn() ?: 'Never'
    ];
    
    // If requirements not met, return error with details
    if (!$requirements['met']) {
        echo json_encode([
            'success' => false,
            'message' => 'Withdrawal requirements not met',
            'requirements' => $requirements
        ]);
        exit;
    }
    
    // Start transaction
    $conn->beginTransaction();
    
    try {
        // Create withdrawal request
        $withdrawStmt = $conn->prepare("
            INSERT INTO withdrawals (
                user_id, amount, payment_method, account_details, 
                requirements_met, status, created_at
            ) VALUES (?, ?, ?, ?, ?, 'under_review', NOW())
        ");
        $withdrawStmt->execute([
            $user_id, 
            $amount, 
            $payment_method, 
            json_encode($account_details),
            json_encode($requirements)
        ]);
        $withdrawal_id = $conn->lastInsertId();
        
        // Deduct from wallet (hold amount)
        $updateStmt = $conn->prepare("
            UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?
        ");
        $updateStmt->execute([$amount, $user_id]);
        
        // Log transaction
        $logStmt = $conn->prepare("
            INSERT INTO wallet_transactions (
                user_id, amount, transaction_type, description, 
                balance_after, status, created_at
            ) 
            SELECT ?, ?, 'withdrawal', CONCAT('Withdrawal request #', ?), 
                   wallet_balance, 'pending', NOW()
            FROM users WHERE id = ?
        ");
        $logStmt->execute([$user_id, $amount, $withdrawal_id, $user_id]);
        
        $conn->commit();
        
        // Send notification
        sendWithdrawalNotification($user['email'], $user['full_name'], $amount, $withdrawal_id);
        
        echo json_encode([
            'success' => true,
            'message' => 'Withdrawal request submitted for review',
            'withdrawal_id' => $withdrawal_id,
            'status' => 'under_review',
            'requirements' => $requirements,
            'reset_message' => '✅ Your tasks and referrals have been reset for the next withdrawal cycle.'
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function sendWithdrawalNotification($email, $name, $amount, $withdrawal_id) {
    // ... your existing notification code ...
}
?>