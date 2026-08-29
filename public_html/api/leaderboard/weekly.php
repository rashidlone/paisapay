<?php
// backend/api/leaderboard/weekly.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

header('Content-Type: application/json');

try {
    $user = authenticate();
    $user_id = $user['user_id'];
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get weekly leaders
    $stmt = $conn->prepare("
        SELECT 
            u.id, u.full_name,
            COALESCE(SUM(wt.amount), 0) as weekly_earnings
        FROM users u
        LEFT JOIN wallet_transactions wt ON u.id = wt.user_id 
            AND wt.transaction_type IN ('referral', 'task', 'bonus')
            AND wt.status = 'completed'
            AND DATE(wt.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        WHERE u.is_active = 1 AND u.is_blocked = 0
        GROUP BY u.id
        ORDER BY weekly_earnings DESC
        LIMIT 20
    ");
    $stmt->execute();
    $rankings = $stmt->fetchAll();
    
    $response = [
        'success' => true,
        'data' => $rankings
    ];
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}