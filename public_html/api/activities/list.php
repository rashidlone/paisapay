<?php
// /api/activities/list.php - Simpler Version

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

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get wallet transactions
    $walletStmt = $conn->prepare("
        SELECT 
            id,
            'wallet' as source,
            transaction_type as type,
            amount,
            description,
            status,
            created_at
        FROM wallet_transactions 
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $walletStmt->execute([$user_id]);
    $walletActivities = $walletStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get withdrawals
    $withdrawStmt = $conn->prepare("
        SELECT 
            id,
            'withdrawal' as source,
            'withdrawal' as type,
            -amount as amount,
            CONCAT('Withdrawal via ', payment_method) as description,
            status,
            created_at
        FROM withdrawals 
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $withdrawStmt->execute([$user_id]);
    $withdrawActivities = $withdrawStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get referrals
    $referralStmt = $conn->prepare("
        SELECT 
            r.id,
            'referral' as source,
            'referral' as type,
            r.reward_amount as amount,
            CONCAT('Referral bonus from ', u.full_name) as description,
            'completed' as status,
            r.created_at
        FROM referrals r
        JOIN users u ON r.referred_user_id = u.id
        WHERE r.referrer_id = ?
        ORDER BY r.created_at DESC
    ");
    $referralStmt->execute([$user_id]);
    $referralActivities = $referralStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Combine all activities
    $allActivities = array_merge($walletActivities, $withdrawActivities, $referralActivities);
    
    // Sort by created_at (newest first)
    usort($allActivities, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Apply pagination
    $totalActivities = count($allActivities);
    $paginatedActivities = array_slice($allActivities, $offset, $limit);
    
    // Get stats
    $statsStmt = $conn->prepare("
        SELECT 
            (SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions 
             WHERE user_id = ? AND transaction_type IN ('credit', 'referral', 'task', 'bonus')) as total_earnings,
            (SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions 
             WHERE user_id = ? AND transaction_type = 'withdrawal') as total_withdrawn,
            (SELECT COUNT(*) FROM referrals WHERE referrer_id = ?) as total_referrals,
            (SELECT COUNT(*) FROM task_history WHERE user_id = ? AND is_claimed = 1) as total_tasks
    ");
    $statsStmt->execute([$user_id, $user_id, $user_id, $user_id]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $paginatedActivities,
        'stats' => $stats,
        'pagination' => [
            'total' => $totalActivities,
            'limit' => $limit,
            'offset' => $offset
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>