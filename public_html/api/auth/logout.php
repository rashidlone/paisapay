<?php
// backend/api/auth/logout.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid request'];

try {
    $user = authenticate();
    $user_id = $user['user_id'];
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Log logout activity
    $stmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, ip_address, created_at) 
        VALUES (?, 'logout', ?, NOW())
    ");
    $stmt->execute([$user_id, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    
    $response = [
        'success' => true,
        'message' => 'Logged out successfully'
    ];
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);