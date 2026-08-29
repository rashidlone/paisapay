<?php
// /api/withdraw/validate.php

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
    SELECT id, full_name, email, wallet_balance, is_verified, email_verified, 
           is_fraud_flag, last_login
        FROM users WHERE id = ?
    ");
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Check if user is fraud flagged
    if ($user['is_fraud_flag']) {
        echo json_encode([
            'success' => false, 
            'message' => 'Account flagged for suspicious activity. Contact support.',
            'fraud' => true
        ]);
        exit;
    }
    
    // Check email verification
    if (!$user['email_verified']) {
        echo json_encode([
            'success' => false,
            'message' => 'Please verify your email before requesting withdrawal',
            'requires_verification' => true
        ]);
        exit;
    }
    
    // Validate requirements
    $requirements = validateWithdrawalRequirements($conn, $user_id, $amount);
    
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
        
        // Update activity summary
        updateActivitySummary($conn, $user_id);
        
        $conn->commit();
        
        // Send notification email
        sendWithdrawalNotification($user['email'], $user['full_name'], $amount, $withdrawal_id);
        
        echo json_encode([
            'success' => true,
            'message' => 'Withdrawal request submitted for review',
            'withdrawal_id' => $withdrawal_id,
            'status' => 'under_review',
            'requirements' => $requirements
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

// ============================================
// VALIDATE WITHDRAWAL REQUIREMENTS
// ============================================

function validateWithdrawalRequirements($conn, $user_id, $amount) {
    $requirements = [
        'met' => true,
        'tasks_required' => 10,
        'referrals_required' => 5,
        'tasks_completed' => 0,
        'referrals_made' => 0,
        'referrals_verified' => 0,
        'referrals_active' => 0,
        'pending_withdrawals' => 0,
        'min_withdrawal' => 50,
        'max_withdrawal' => 50000,
        'details' => []
    ];
    
    // Get tasks completed in current period
    $taskStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM task_history 
        WHERE user_id = ? AND is_claimed = 1 
        AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $taskStmt->execute([$user_id]);
    $tasks = $taskStmt->fetch(PDO::FETCH_ASSOC);
    $requirements['tasks_completed'] = intval($tasks['count']);
    
    // Get referrals
    $referralStmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN referred_user_verified = 1 THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN referred_user_active = 1 THEN 1 ELSE 0 END) as active
        FROM referrals 
        WHERE referrer_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $referralStmt->execute([$user_id]);
    $referrals = $referralStmt->fetch(PDO::FETCH_ASSOC);
    $requirements['referrals_made'] = intval($referrals['total']);
    $requirements['referrals_verified'] = intval($referrals['verified']);
    $requirements['referrals_active'] = intval($referrals['active']);
    
    // Get pending withdrawals
    $pendingStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM withdrawals 
        WHERE user_id = ? AND status IN ('pending', 'under_review')
    ");
    $pendingStmt->execute([$user_id]);
    $pending = $pendingStmt->fetch(PDO::FETCH_ASSOC);
    $requirements['pending_withdrawals'] = intval($pending['count']);
    
    // Check each requirement
    $requirements['details']['tasks'] = [
        'required' => $requirements['tasks_required'],
        'current' => $requirements['tasks_completed'],
        'met' => $requirements['tasks_completed'] >= $requirements['tasks_required']
    ];
    
    $requirements['details']['referrals'] = [
        'required' => $requirements['referrals_required'],
        'current' => $requirements['referrals_active'],
        'met' => $requirements['referrals_active'] >= $requirements['referrals_required']
    ];
    
    $requirements['details']['no_pending'] = [
        'required' => 0,
        'current' => $requirements['pending_withdrawals'],
        'met' => $requirements['pending_withdrawals'] == 0
    ];
    
    $requirements['details']['amount'] = [
        'min' => $requirements['min_withdrawal'],
        'max' => $requirements['max_withdrawal'],
        'current' => $amount,
        'met' => $amount >= $requirements['min_withdrawal'] && $amount <= $requirements['max_withdrawal']
    ];
    
    // Check if all requirements are met
    foreach ($requirements['details'] as $key => $detail) {
        if (!$detail['met']) {
            $requirements['met'] = false;
            break;
        }
    }
    
    return $requirements;
}

// ============================================
// UPDATE ACTIVITY SUMMARY
// ============================================

function updateActivitySummary($conn, $user_id) {
    $period_start = date('Y-m-d', strtotime('first day of this month'));
    $period_end = date('Y-m-d', strtotime('last day of this month'));
    
    // Get tasks completed
    $taskStmt = $conn->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(reward_amount), 0) as earnings
        FROM task_history 
        WHERE user_id = ? AND is_claimed = 1 
        AND completed_at BETWEEN ? AND ?
    ");
    $taskStmt->execute([$user_id, $period_start . ' 00:00:00', $period_end . ' 23:59:59']);
    $tasks = $taskStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get referrals
    $referralStmt = $conn->prepare("
        SELECT 
            COUNT(*) as count,
            SUM(CASE WHEN referred_user_active = 1 THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN referred_user_verified = 1 THEN 1 ELSE 0 END) as verified
        FROM referrals 
        WHERE referrer_id = ? AND created_at BETWEEN ? AND ?
    ");
    $referralStmt->execute([$user_id, $period_start . ' 00:00:00', $period_end . ' 23:59:59']);
    $referrals = $referralStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get withdrawals
    $withdrawStmt = $conn->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total
        FROM withdrawals 
        WHERE user_id = ? AND status = 'paid'
        AND created_at BETWEEN ? AND ?
    ");
    $withdrawStmt->execute([$user_id, $period_start . ' 00:00:00', $period_end . ' 23:59:59']);
    $withdrawals = $withdrawStmt->fetch(PDO::FETCH_ASSOC);
    
    // Check eligibility
    $eligible = ($tasks['count'] >= 10 && $referrals['active'] >= 5);
    
    // Insert or update
    $stmt = $conn->prepare("
        INSERT INTO user_activity_summary 
        (user_id, period_start, period_end, tasks_completed, referrals_made, 
         referrals_active, referrals_verified, earnings, withdrawal_count, 
         total_withdrawn, is_withdrawal_eligible, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
        tasks_completed = VALUES(tasks_completed),
        referrals_made = VALUES(referrals_made),
        referrals_active = VALUES(referrals_active),
        referrals_verified = VALUES(referrals_verified),
        earnings = VALUES(earnings),
        withdrawal_count = VALUES(withdrawal_count),
        total_withdrawn = VALUES(total_withdrawn),
        is_withdrawal_eligible = VALUES(is_withdrawal_eligible),
        updated_at = NOW()
    ");
    $stmt->execute([
        $user_id,
        $period_start,
        $period_end,
        $tasks['count'],
        $referrals['count'],
        $referrals['active'],
        $referrals['verified'],
        $tasks['earnings'],
        $withdrawals['count'],
        $withdrawals['total'],
        $eligible ? 1 : 0
    ]);
}

// ============================================
// SEND WITHDRAWAL NOTIFICATION
// ============================================

function sendWithdrawalNotification($email, $name, $amount, $withdrawal_id) {
    $mailer = new Mailer();
    $subject = "💰 Withdrawal Request #$withdrawal_id - Under Review";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background: #0a0e1a; color: #ffffff; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #111827; border-radius: 12px; }
            .header { text-align: center; padding-bottom: 20px; border-bottom: 1px solid #1a2234; }
            .logo { font-size: 24px; font-weight: 800; color: #8B5CF6; }
            .content { padding: 20px 0; }
            .amount { font-size: 36px; font-weight: 800; color: #fbbf24; }
            .status-box { background: rgba(96,165,250,0.1); border: 1px solid rgba(96,165,250,0.2); border-radius: 8px; padding: 16px; margin: 16px 0; }
            .status { color: #60a5fa; font-weight: 600; }
            .footer { text-align: center; padding-top: 20px; border-top: 1px solid #1a2234; color: #64748b; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='logo'>💰 PaisaPay</div>
            </div>
            <div class='content'>
                <h2>Withdrawal Request Submitted</h2>
                <p>Hello <strong>$name</strong>,</p>
                <p>Your withdrawal request has been submitted and is now <span class='status'>UNDER REVIEW</span>.</p>
                <div class='status-box'>
                    <p><strong>Amount:</strong> <span class='amount'>₹" . number_format($amount, 2) . "</span></p>
                    <p><strong>Request ID:</strong> #$withdrawal_id</p>
                    <p><strong>Status:</strong> <span class='status'>Under Review</span></p>
                </div>
                <p>Our team will review your request within 24-48 hours. You will receive a notification once approved.</p>
                <p><strong>What happens during review?</strong></p>
                <ul>
                    <li>✅ Verification of your account activity</li>
                    <li>✅ Validation of referrals and tasks</li>
                    <li>✅ Fraud prevention check</li>
                </ul>
            </div>
            <div class='footer'>
                <p>&copy; 2024 PaisaPay. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $mailer->sendRaw($email, $subject, $message);
}
?>