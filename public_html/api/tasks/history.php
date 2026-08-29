<?php
// backend/api/tasks/history.php

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
            th.id, th.reward_amount, th.completed_at,
            t.title, t.task_type
        FROM task_history th
        JOIN tasks t ON th.task_id = t.id
        WHERE th.user_id = ? AND th.is_claimed = 1
        ORDER BY th.completed_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $history = $stmt->fetchAll();
    
    $response = [
        'success' => true,
        'data' => $history
    ];
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}