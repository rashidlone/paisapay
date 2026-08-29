<?php
// backend/api/referrals/stats.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

header('Content-Type: application/json');

try {
    $user = authenticate();
    $user_id = $user['user_id'];
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get referral stats
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_referrals,
            SUM(CASE WHEN is_rewarded = 1 THEN 1 ELSE 0 END) as rewarded_referrals,
            SUM(CASE WHEN is_valid = 1 THEN 1 ELSE 0 END) as valid_referrals,
            COALESCE(SUM(reward_amount), 0) as total_reward_amount
        FROM referrals 
        WHERE referrer_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
    // Get daily referral count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as today_referrals 
        FROM referrals 
        WHERE referrer_id = ? AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$user_id]);
    $today = $stmt->fetch()['today_referrals'];
    
    // Get required referrals from settings
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'required_referrals'");
    $stmt->execute();
    $required = $stmt->fetch()['setting_value'] ?? 10;
    
    $response = [
        'success' => true,
        'data' => [
            'total' => $stats['total_referrals'] ?? 0,
            'rewarded' => $stats['rewarded_referrals'] ?? 0,
            'valid' => $stats['valid_referrals'] ?? 0,
            'today' => $today,
            'required' => $required,
            'progress' => min(100, (($stats['total_referrals'] ?? 0) / $required) * 100),
            'earnings' => $stats['total_reward_amount'] ?? 0
        ]
    ];
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}