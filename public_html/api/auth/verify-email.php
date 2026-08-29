<?php
// /api/auth/verify-email.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/config.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Verification token required']);
    exit;
}

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Find user with this token
    $stmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE verification_token = ? AND is_verified = 0");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired verification token']);
        exit;
    }
    
    // Mark as verified
    $stmt = $conn->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // Send welcome email
    require_once __DIR__ . '/../../helpers/mailer.php';
    $mailer = new Mailer();
    $mailer->sendWelcome($user['email'], $user['full_name']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Email verified successfully! You can now login.'
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>