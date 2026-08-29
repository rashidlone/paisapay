<?php
// /api/withdraw/detail.php

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
$withdrawal_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$withdrawal_id) {
    echo json_encode(['success' => false, 'message' => 'Withdrawal ID required']);
    exit;
}

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get withdrawal details
    $stmt = $conn->prepare("
        SELECT 
            id, amount, payment_method, account_details,
            status, admin_notes, requirements_met,
            validation_notes, processed_at, created_at
        FROM withdrawals 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$withdrawal_id, $user_id]);
    $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$withdrawal) {
        echo json_encode(['success' => false, 'message' => 'Withdrawal not found']);
        exit;
    }
    
    // Parse requirements JSON
    if ($withdrawal['requirements_met']) {
        $withdrawal['requirements'] = json_decode($withdrawal['requirements_met'], true);
        unset($withdrawal['requirements_met']);
    }
    
    // In the referral query section
$refStmt = $conn->prepare("
    SELECT 
        u.full_name as name,
        u.email,
        u.is_verified as email_verified,
        r.validation_status as status,
        r.referred_user_tasks_completed as tasks_completed,
        r.is_genuine,
        r.validation_notes,
        -- Determine if referral should be verified
        CASE 
            WHEN r.validation_status = 'verified' THEN 'verified'
            WHEN u.is_verified = 1 AND r.referred_user_tasks_completed >= 3 THEN 'verified'
            WHEN r.is_genuine = 0 THEN 'flagged'
            ELSE 'pending'
        END as detailed_status,
        CASE 
            WHEN r.referred_user_tasks_completed >= 3 AND u.is_verified = 1 THEN TRUE
            ELSE FALSE
        END as all_requirements_met,
        u.is_active,
        (SELECT COUNT(*) FROM task_history WHERE user_id = u.id AND is_claimed = 1) as total_tasks_completed
    FROM referrals r
    JOIN users u ON r.referred_user_id = u.id
    WHERE r.referrer_id = ?
    ORDER BY r.created_at DESC
    LIMIT 20
");
$refStmt->execute([$user_id]);
    $withdrawal['referrals'] = $refStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get tasks summary
    $taskStmt = $conn->prepare("
        SELECT 
            COUNT(*) as completed,
            (SELECT setting_value FROM settings WHERE setting_key = 'required_tasks') as required,
            COUNT(CASE WHEN completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as this_month
        FROM task_history 
        WHERE user_id = ? AND is_claimed = 1
    ");
    $taskStmt->execute([$user_id]);
    $withdrawal['tasks_summary'] = $taskStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $withdrawal
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>