<?php
// backend/api/user/update.php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$full_name = $input['full_name'] ?? '';

$response = ['success' => false, 'message' => 'Invalid request'];

try {
    $user = authenticate();
    $user_id = $user['user_id'];
    
    if (empty($full_name)) {
        throw new Exception('Full name is required');
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        UPDATE users 
        SET full_name = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$full_name, $user_id]);
    
    $response = [
        'success' => true,
        'message' => 'Profile updated successfully'
    ];
    
} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);