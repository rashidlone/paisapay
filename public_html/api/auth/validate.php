<?php
// /api/auth/validate.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/config.php';

// Get token from header
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    echo json_encode(['valid' => false, 'message' => 'No token provided']);
    exit;
}

$token = $matches[1];

try {
    // Decode token
    $decoded = json_decode(base64_decode($token), true);
    
    if (!$decoded || !isset($decoded['user_id']) || !isset($decoded['exp'])) {
        echo json_encode(['valid' => false, 'message' => 'Invalid token format']);
        exit;
    }
    
    // Check expiration
    if ($decoded['exp'] < time()) {
        echo json_encode(['valid' => false, 'message' => 'Token expired']);
        exit;
    }
    
    // Verify user exists in database
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $conn->prepare("SELECT id, is_active, is_blocked FROM users WHERE id = ?");
    $stmt->execute([$decoded['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['valid' => false, 'message' => 'User not found']);
        exit;
    }
    
    if ($user['is_blocked']) {
        echo json_encode(['valid' => false, 'message' => 'User is blocked']);
        exit;
    }
    
    if (!$user['is_active']) {
        echo json_encode(['valid' => false, 'message' => 'User is inactive']);
        exit;
    }
    
    echo json_encode(['valid' => true, 'user_id' => $user['id']]);
    
} catch (PDOException $e) {
    echo json_encode(['valid' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    echo json_encode(['valid' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>