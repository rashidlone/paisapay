<?php
// /api/user/profile.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// FIXED: Use absolute path
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';

// Get token
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - No token provided']);
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
    
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        // Fetch user data
        $stmt = $conn->prepare("SELECT id, full_name, email, phone_number, referral_code, wallet_balance, total_earnings, is_verified, created_at FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $user
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] == 'POST' || $_SERVER['REQUEST_METHOD'] == 'PUT') {
        // Update user data (name and phone number)
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Log the input for debugging
        error_log("Update input: " . print_r($input, true));
        
        // Check if user exists first
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $checkStmt->execute([$user_id]);
        if (!$checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        // Build update query dynamically
        $updates = [];
        $params = [];
        $responseData = [];
        
        // Update full_name if provided
        if (isset($input['full_name'])) {
            $full_name = trim($input['full_name']);
            if (empty($full_name)) {
                echo json_encode(['success' => false, 'message' => 'Name cannot be empty']);
                exit;
            }
            $updates[] = "full_name = ?";
            $params[] = $full_name;
            $responseData['full_name'] = $full_name;
        }
        
        // Update phone_number if provided
        if (isset($input['phone_number'])) {
            $phone_number = trim($input['phone_number']);
            // Allow empty phone number (user might want to clear it)
            // But if provided, we update it (even if empty)
            $updates[] = "phone_number = ?";
            $params[] = $phone_number;
            $responseData['phone_number'] = $phone_number;
        }
        
        // If no fields to update
        if (empty($updates)) {
            echo json_encode([
                'success' => true,
                'message' => 'No fields to update',
                'data' => []
            ]);
            exit;
        }
        
        // Add updated_at and user_id to params
        $updates[] = "updated_at = NOW()";
        $params[] = $user_id;
        
        // Build and execute query
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        // Get updated user data
        $stmt = $conn->prepare("SELECT id, full_name, email, phone_number, referral_code, wallet_balance, total_earnings, is_verified, created_at FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $updatedUser
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>