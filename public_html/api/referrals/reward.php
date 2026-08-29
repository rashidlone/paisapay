<?php
// backend/api/referrals/reward.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$referral_id = $input['referral_id'] ?? null;

$response = ['success' => false, 'message' => 'Invalid request'];

try {
    $user = authenticate();
    $user_id = $user['user_id'];
    
    if (!$referral_id) {
        throw new Exception('Referral ID required');
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check if referral belongs to user
    $stmt = $conn->prepare("
        SELECT id, referrer_id, reward_amount, is_rewarded 
        FROM referrals 
        WHERE id = ? AND referrer_id = ?
    ");
    $stmt->execute([$referral_id, $user_id]);
    $referral = $stmt->fetch();
    
    if (!$referral) {
        throw new Exception('Referral not found');
    }
    
    if ($referral['is_rewarded']) {
        throw new Exception('Referral already rewarded');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Update referral status
    $stmt = $conn->prepare("
        UPDATE referrals 
        SET is_rewarded = 1, reward_date = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$referral_id]);
    
    // Add reward to wallet
    $amount = $referral['reward_amount'];
    $stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_balance = $stmt->fetch()['wallet_balance'];
    $new_balance = $current_balance + $amount;
    
    $stmt = $conn->prepare("
        UPDATE users 
        SET wallet_balance = ?, 
            referral_earnings = referral_earnings + ? 
        WHERE id = ?
    ");
    $stmt->execute([$new_balance, $amount, $user_id]);
    
    $stmt = $conn->prepare("
        INSERT INTO wallet_transactions (
            user_id, amount, transaction_type, description, 
            balance_after, status, created_at
        ) VALUES (?, ?, 'referral', ?, ?, 'completed', NOW())
    ");
    $stmt->execute([
        $user_id,
        $amount,
        "Referral reward #{$referral_id}",
        $new_balance
    ]);
    
    $db->commit();
    
    $response = [
        'success' => true,
        'message' => 'Referral reward claimed!',
        'data' => [
            'amount' => $amount,
            'new_balance' => $new_balance
        ]
    ];
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollback();
    }
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);