<?php
// /api/referrals/list.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/config.php';

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
    
    // ✅ Get referrals with user names
    $stmt = $conn->prepare("
        SELECT 
            r.id,
            r.referred_user_id,
            r.referred_user_name,  -- ✅ Get the stored name
            r.referral_code,
            r.reward_amount,
            r.is_rewarded,
            r.is_valid,
            r.validation_status,
            r.created_at,
            r.reward_date
        FROM referrals r
        WHERE r.referrer_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get stats
    $statsStmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            COALESCE(SUM(reward_amount), 0) as earnings,
            SUM(CASE WHEN is_rewarded = 1 THEN 1 ELSE 0 END) as rewarded_count
        FROM referrals 
        WHERE referrer_id = ?
    ");
    $statsStmt->execute([$user_id]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total' => intval($stats['total']),
            'earnings' => floatval($stats['earnings']),
            'rewarded_count' => intval($stats['rewarded_count']),
            'history' => $referrals
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>