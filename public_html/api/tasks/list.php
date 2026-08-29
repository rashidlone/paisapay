<?php
// /api/tasks/list.php

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

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all active tasks
    $stmt = $conn->prepare("
        SELECT 
            id, title, description, icon, url, reward_amount, 
            timer_seconds, daily_limit, is_one_time, is_one_time, 
            task_type, is_active
        FROM tasks 
        WHERE is_active = 1 
        ORDER BY id DESC
    ");
    $stmt->execute();
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ✅ FIX: Get today's task counts for the user
    foreach ($tasks as &$task) {
        $countStmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM task_history 
            WHERE user_id = ? 
            AND task_id = ? 
            AND DATE(completed_at) = CURDATE()
            AND is_claimed = 1
        ");
        $countStmt->execute([$user_id, $task['id']]);
        $result = $countStmt->fetch(PDO::FETCH_ASSOC);
        $task['completed_today'] = intval($result['count']);
        
        // Also get total completed ever (for one-time tasks)
        $totalStmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM task_history 
            WHERE user_id = ? 
            AND task_id = ?
            AND is_claimed = 1
        ");
        $totalStmt->execute([$user_id, $task['id']]);
        $totalResult = $totalStmt->fetch(PDO::FETCH_ASSOC);
        $task['total_completed'] = intval($totalResult['count']);
        
        // Check if one-time task already completed
        if ($task['is_one_time'] && $task['total_completed'] > 0) {
            $task['is_available'] = false;
        } else {
            $task['is_available'] = $task['completed_today'] < $task['daily_limit'];
        }
    }
    
    // Get today's total tasks count
    $todayStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM task_history 
        WHERE user_id = ? 
        AND DATE(completed_at) = CURDATE()
        AND is_claimed = 1
    ");
    $todayStmt->execute([$user_id]);
    $todayCount = $todayStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get today's total earnings
    $earningsStmt = $conn->prepare("
        SELECT COALESCE(SUM(reward_amount), 0) as total 
        FROM task_history 
        WHERE user_id = ? 
        AND DATE(completed_at) = CURDATE()
        AND is_claimed = 1
    ");
    $earningsStmt->execute([$user_id]);
    $todayEarnings = $earningsStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $tasks,
        'today_stats' => [
            'tasks_completed' => intval($todayCount['count']),
            'earnings' => floatval($todayEarnings['total'])
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>