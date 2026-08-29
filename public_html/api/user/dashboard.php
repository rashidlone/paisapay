<?php
// /api/user/dashboard.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';

// Get token
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - No token provided']);
    exit;
}

$token = $matches[1];
$decoded = json_decode(base64_decode($token), true);

if (!$decoded || !isset($decoded['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

$user_id = $decoded['user_id'];

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ============================================
    // 1. GET USER DATA
    // ============================================
    
    $userStmt = $conn->prepare("
        SELECT 
            id, 
            full_name, 
            email, 
            phone_number, 
            referral_code, 
            wallet_balance, 
            total_earnings, 
            referral_earnings,
            task_earnings,
            is_verified, 
            is_active,
            is_blocked,
            created_at 
        FROM users 
        WHERE id = ?
    ");
    $userStmt->execute([$user_id]);
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // ============================================
    // 2. GET REFERRAL STATS
    // ============================================
    
    $refStmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_referrals,
            SUM(CASE WHEN is_rewarded = 1 THEN 1 ELSE 0 END) as rewarded_referrals,
            SUM(CASE WHEN validation_status = 'verified' THEN 1 ELSE 0 END) as verified_referrals,
            SUM(reward_amount) as total_reward_amount,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_referrals
        FROM referrals 
        WHERE referrer_id = ?
    ");
    $refStmt->execute([$user_id]);
    $referralStats = $refStmt->fetch(PDO::FETCH_ASSOC);

    // ============================================
    // 3. GET TASK STATS
    // ============================================
    
    $taskStmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_tasks,
            SUM(reward_amount) as total_task_earnings,
            COUNT(CASE WHEN DATE(completed_at) = CURDATE() THEN 1 END) as today_tasks,
            SUM(CASE WHEN DATE(completed_at) = CURDATE() THEN reward_amount ELSE 0 END) as today_earnings
        FROM task_history 
        WHERE user_id = ? AND is_claimed = 1
    ");
    $taskStmt->execute([$user_id]);
    $taskStats = $taskStmt->fetch(PDO::FETCH_ASSOC);

    // ============================================
    // 4. GET WITHDRAWAL STATS
    // ============================================
    
    $withdrawStmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_withdrawals,
            SUM(amount) as total_withdrawn,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
            SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review_count,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count
        FROM withdrawals 
        WHERE user_id = ?
    ");
    $withdrawStmt->execute([$user_id]);
    $withdrawalStats = $withdrawStmt->fetch(PDO::FETCH_ASSOC);

    // ============================================
    // 5. GET TODAY'S STATS (FIXED)
    // ============================================
    
    $todayTaskStmt = $conn->prepare("
        SELECT 
            COUNT(*) as count,
            COALESCE(SUM(reward_amount), 0) as earnings
        FROM task_history 
        WHERE user_id = ? 
        AND DATE(completed_at) = CURDATE()
        AND is_claimed = 1
    ");
    $todayTaskStmt->execute([$user_id]);
    $todayTasks = $todayTaskStmt->fetch(PDO::FETCH_ASSOC);

    // ============================================
    // 6. GET PERIOD DATA (Since Last Withdrawal)
    // ============================================
    
    // Get last successful withdrawal date
    $lastWithdrawalStmt = $conn->prepare("
        SELECT MAX(created_at) as last_date 
        FROM withdrawals 
        WHERE user_id = ? AND status IN ('paid', 'approved')
    ");
    $lastWithdrawalStmt->execute([$user_id]);
    $lastWithdrawal = $lastWithdrawalStmt->fetch(PDO::FETCH_ASSOC);
    $lastDate = $lastWithdrawal['last_date'] ?? '1970-01-01 00:00:00';

    // Tasks since last withdrawal
    $periodTaskStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM task_history 
        WHERE user_id = ? 
        AND is_claimed = 1 
        AND completed_at > ?
    ");
    $periodTaskStmt->execute([$user_id, $lastDate]);
    $periodTasks = $periodTaskStmt->fetch(PDO::FETCH_ASSOC);

    // Referrals since last withdrawal
    $periodReferralStmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN validation_status = 'verified' THEN 1 ELSE 0 END) as verified
        FROM referrals 
        WHERE referrer_id = ? 
        AND created_at > ?
    ");
    $periodReferralStmt->execute([$user_id, $lastDate]);
    $periodReferrals = $periodReferralStmt->fetch(PDO::FETCH_ASSOC);

    // ============================================
    // 7. GET SETTINGS (For withdrawal requirements)
    // ============================================
    
    $settingsStmt = $conn->prepare("
        SELECT setting_key, setting_value 
        FROM settings 
        WHERE setting_key IN (
            'min_withdrawal', 
            'max_withdrawal', 
            'required_referrals', 
            'required_tasks',
            'daily_task_limit',
            'daily_referral_limit',
            'currency_symbol'
        )
    ");
    $settingsStmt->execute();
    $settings = [];
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    // ============================================
    // 8. GET RECENT TRANSACTIONS
    // ============================================
    
    $txnStmt = $conn->prepare("
        SELECT 
            id, 
            amount, 
            transaction_type, 
            description,
            balance_after, 
            status, 
            created_at
        FROM wallet_transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $txnStmt->execute([$user_id]);
    $recentTransactions = $txnStmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================
    // 9. GET ACTIVE TASKS (with completion status)
    // ============================================
    
    $taskListStmt = $conn->prepare("
        SELECT 
            t.id, 
            t.title, 
            t.description, 
            t.icon, 
            t.url, 
            t.reward_amount, 
            t.timer_seconds, 
            t.daily_limit,
            t.is_one_time,
            t.task_type,
            t.is_active,
            COALESCE(
                (SELECT COUNT(*) FROM task_history 
                 WHERE task_id = t.id AND user_id = ? 
                 AND DATE(completed_at) = CURDATE() AND is_claimed = 1),
                0
            ) as completed_today
        FROM tasks t
        WHERE t.is_active = 1 
        ORDER BY t.created_at DESC 
        LIMIT 10
    ");
    $taskListStmt->execute([$user_id]);
    $activeTasks = $taskListStmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================
    // 10. BUILD RESPONSE
    // ============================================
    
    $response = [
        'success' => true,
        'data' => [
            // User data
            'user' => $userData,
            
            // Stats
            'stats' => [
                'referrals' => [
                    'total' => intval($referralStats['total_referrals'] ?? 0),
                    'rewarded' => intval($referralStats['rewarded_referrals'] ?? 0),
                    'verified' => intval($referralStats['verified_referrals'] ?? 0),
                    'total_reward' => floatval($referralStats['total_reward_amount'] ?? 0),
                    'today' => intval($referralStats['today_referrals'] ?? 0)
                ],
                'tasks' => [
                    'total' => intval($taskStats['total_tasks'] ?? 0),
                    'total_earnings' => floatval($taskStats['total_task_earnings'] ?? 0),
                    'today' => intval($taskStats['today_tasks'] ?? 0),
                    'today_earnings' => floatval($taskStats['today_earnings'] ?? 0)
                ],
                'withdrawals' => [
                    'total' => intval($withdrawalStats['total_withdrawals'] ?? 0),
                    'total_withdrawn' => floatval($withdrawalStats['total_withdrawn'] ?? 0),
                    'pending_count' => intval($withdrawalStats['pending_count'] ?? 0) + intval($withdrawalStats['under_review_count'] ?? 0),
                    'pending_amount' => floatval($withdrawalStats['pending_amount'] ?? 0),
                    'approved' => intval($withdrawalStats['approved_count'] ?? 0),
                    'rejected' => intval($withdrawalStats['rejected_count'] ?? 0),
                    'paid' => intval($withdrawalStats['paid_count'] ?? 0)
                ]
            ],
            
            // ✅ TODAY'S STATS (FIXED)
            'today_stats' => [
                'tasks_completed' => intval($todayTasks['count'] ?? 0),
                'earnings' => floatval($todayTasks['earnings'] ?? 0)
            ],
            
            // ✅ PERIOD DATA (Since last withdrawal) (FIXED)
            'period' => [
                'tasks_since_last_withdrawal' => intval($periodTasks['count'] ?? 0),
                'referrals_since_last_withdrawal' => intval($periodReferrals['total'] ?? 0),
                'verified_referrals_since_last_withdrawal' => intval($periodReferrals['verified'] ?? 0),
                'last_withdrawal_date' => $lastWithdrawal['last_date'] ?? 'Never'
            ],
            
            // Settings
            'settings' => $settings,
            
            // Recent transactions
            'recent_transactions' => $recentTransactions,
            
            // Active tasks
            'active_tasks' => $activeTasks
        ]
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>