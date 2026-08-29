<?php
// /api/leaderboard/index.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/config.php';

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
$period = $_GET['period'] ?? 'weekly';

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // ✅ Get top users by TOTAL EARNINGS
    $sql = "
        SELECT 
            u.id,
            u.full_name,
            u.referral_code,
            u.total_earnings as total_earnings,
            u.wallet_balance as wallet_balance,
            COUNT(DISTINCT r.id) as referral_count,
            COUNT(DISTINCT th.id) as task_count
        FROM users u
        LEFT JOIN referrals r ON u.id = r.referrer_id
        LEFT JOIN task_history th ON u.id = th.user_id AND th.is_claimed = 1
        WHERE u.is_active = 1 AND u.is_blocked = 0
        GROUP BY u.id
        ORDER BY u.total_earnings DESC
        LIMIT 100
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $rankings = $stmt->fetchAll();
    
    // Get top 3
    $top_three = array_slice($rankings, 0, 3);
    
    // Find user's position
    $user_position = null;
    $user_earnings = 0;
    $user_wallet = 0;
    foreach ($rankings as $index => $rank) {
        if ($rank['id'] == $user_id) {
            $user_position = $index + 1;
            $user_earnings = $rank['total_earnings'] ?? 0;
            $user_wallet = $rank['wallet_balance'] ?? 0;
            $rankings[$index]['is_user'] = true;
            break;
        }
    }
    
    // ✅ If user not found in rankings (no earnings), get their wallet balance separately
    if ($user_position === null) {
        $stmt = $conn->prepare("SELECT wallet_balance, total_earnings FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if ($user) {
            $user_wallet = $user['wallet_balance'] ?? 0;
            $user_earnings = $user['total_earnings'] ?? 0;
        }
        // Add user at the bottom
        $user_position = count($rankings) + 1;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'top_three' => $top_three,
            'rankings' => $rankings,
            'user_position' => $user_position,
            'user_earnings' => (float)$user_earnings,
            'user_wallet' => (float)$user_wallet,
            'balance' => (float)$user_wallet
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>