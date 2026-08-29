<?php
// /api/tasks/claim.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';

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
$task_id = isset($input['task_id']) ? intval($input['task_id']) : 0;

if (!$task_id) {
    echo json_encode(['success' => false, 'message' => 'Task ID required']);
    exit;
}

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ============================================
    // ✅ CHECK: User must be verified
    // ============================================
    
    $userCheckStmt = $conn->prepare("SELECT is_verified FROM users WHERE id = ?");
    $userCheckStmt->execute([$user_id]);
    $user = $userCheckStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || $user['is_verified'] == 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Please verify your email first to claim tasks'
        ]);
        exit;
    }
    
    // Get task details
    $taskStmt = $conn->prepare("SELECT id, reward_amount, daily_limit FROM tasks WHERE id = ? AND is_active = 1");
    $taskStmt->execute([$task_id]);
    $task = $taskStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'Task not found or inactive']);
        exit;
    }
    
    // Check if user already completed this task today
    $checkStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM task_history 
        WHERE user_id = ? AND task_id = ? 
        AND DATE(completed_at) = CURDATE()
    ");
    $checkStmt->execute([$user_id, $task_id]);
    $todayCount = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($todayCount >= $task['daily_limit']) {
        echo json_encode(['success' => false, 'message' => 'Daily limit reached for this task']);
        exit;
    }
    
    // Start transaction
    $conn->beginTransaction();
    
    // Insert task completion
    $insertStmt = $conn->prepare("
        INSERT INTO task_history (user_id, task_id, reward_amount, is_claimed, completed_at) 
        VALUES (?, ?, ?, 1, NOW())
    ");
    $insertStmt->execute([$user_id, $task_id, $task['reward_amount']]);
    
    // Update user's wallet
    $walletStmt = $conn->prepare("
        UPDATE users 
        SET wallet_balance = wallet_balance + ?,
            task_earnings = task_earnings + ?,
            total_earnings = total_earnings + ?
        WHERE id = ?
    ");
    $walletStmt->execute([$task['reward_amount'], $task['reward_amount'], $task['reward_amount'], $user_id]);
    
    // Get task title for transaction description
    $taskTitleStmt = $conn->prepare("SELECT title FROM tasks WHERE id = ?");
    $taskTitleStmt->execute([$task_id]);
    $taskTitle = $taskTitleStmt->fetch(PDO::FETCH_ASSOC);
    $taskName = $taskTitle ? $taskTitle['title'] : 'Task #' . $task_id;
    
    // Log transaction
    $logStmt = $conn->prepare("
        INSERT INTO wallet_transactions (user_id, amount, transaction_type, description, balance_after, created_at) 
        SELECT ?, ?, 'task', CONCAT(?, ' completed'), wallet_balance + ?, NOW()
        FROM users WHERE id = ?
    ");
    $logStmt->execute([$user_id, $task['reward_amount'], $taskName, $task['reward_amount'], $user_id]);
    
    // ============================================
    // ✅ UPDATE REFERRAL TASK COUNT (No is_genuine check)
    // ============================================
    
    // Get total tasks for this user
    $taskCountStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM task_history 
        WHERE user_id = ? AND is_claimed = 1
    ");
    $taskCountStmt->execute([$user_id]);
    $taskCount = $taskCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get user verification status
    $isVerified = $user['is_verified'];
    
    // Update referrals - NO is_genuine check
    $updateReferralStmt = $conn->prepare("
        UPDATE referrals r
        SET 
            r.referred_user_tasks_completed = ?,
            r.referred_user_active = CASE WHEN ? > 0 THEN 1 ELSE 0 END,
            r.referred_user_verified = ?,
            r.validation_status = CASE 
                WHEN ? = 1 AND ? >= 3 THEN 'verified'
                ELSE 'pending' 
            END,
            r.updated_at = NOW()
        WHERE r.referred_user_id = ?
    ");
    $updateReferralStmt->execute([
        $taskCount,          // referred_user_tasks_completed
        $taskCount,          // referred_user_active
        $isVerified,         // referred_user_verified
        $isVerified,         // validation_status - email verified
        $taskCount,          // validation_status - tasks >= 3
        $user_id             // WHERE referred_user_id = ?
    ]);
    
    $conn->commit();
    
    // Get updated balance
    $balanceStmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $balanceStmt->execute([$user_id]);
    $newBalance = $balanceStmt->fetch(PDO::FETCH_ASSOC)['wallet_balance'];
    
    echo json_encode([
        'success' => true,
        'message' => 'Task completed successfully!',
        'reward' => $task['reward_amount'],
        'new_balance' => $newBalance,
        'tasks_completed' => $taskCount
    ]);
    
} catch (PDOException $e) {
    if (isset($conn)) $conn->rollBack();
    error_log("Task claim error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    error_log("Task claim error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>