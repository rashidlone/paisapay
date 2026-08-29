<?php
// backend/api/wallet/history.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

header('Content-Type: application/json');

$limit = $_GET['limit'] ?? 50;

try {
    $user = authenticate();
    $user_id = $user['user_id'];
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT 
            id, amount, transaction_type, description,
            balance_after, status, created_at
        FROM wallet_transactions 
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$user_id, $limit]);
    $transactions = $stmt->fetchAll();
    
    $response = [
        'success' => true,
        'data' => $transactions
    ];
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}