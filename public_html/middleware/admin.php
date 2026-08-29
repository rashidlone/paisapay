<?php
// backend/middleware/admin.php

require_once __DIR__ . '/auth.php';

function requireAdmin() {
    $user = authenticate();
    
    // Check if user is admin
    // You need to have an admins table or a role field in users table
    // For this implementation, we'll check if the user exists in admins table
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT id, role, full_name 
        FROM admins 
        WHERE id = ? AND is_active = 1
    ");
    $stmt->execute([$user['user_id']]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Forbidden - Admin access required'
        ]);
        exit;
    }
    
    return [
        'user_id' => $admin['id'],
        'role' => $admin['role'],
        'full_name' => $admin['full_name']
    ];
}