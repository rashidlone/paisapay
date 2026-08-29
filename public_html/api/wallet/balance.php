<?php
// backend/api/wallet/balance.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

header('Content-Type: application/json');

try {
    $user = authenticate();
    $user_id = $user['user_id'];
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT 
            wallet_balance,
            total_earnings,
            referral_earnings,
            task_earnings
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $balance = $stmt->fetch();
    
    if (!$balance) {
        throw new Exception('User not found');
    }
    
    $response = [
        'success' => true,
        'data' => $balance
    ];
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}