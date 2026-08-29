<?php
// /api/auth/reset-password.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/config.php';

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';
$password = $input['password'] ?? '';

if (empty($token) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Token and password required']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $conn->prepare("
        SELECT id FROM users 
        WHERE reset_token = ? AND reset_token_expiry > NOW()
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired reset token']);
        exit;
    }
    
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        UPDATE users 
        SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL 
        WHERE id = ?
    ");
    $stmt->execute([$password_hash, $user['id']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Password reset successfully! You can now login.'
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>