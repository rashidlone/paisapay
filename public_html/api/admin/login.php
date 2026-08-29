<?php
// /api/admin/login.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';

$input = json_decode(file_get_contents('php://input'), true);
$username = isset($input['username']) ? trim($input['username']) : '';
$password = isset($input['password']) ? trim($input['password']) : '';

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Username and password required']);
    exit;
}

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $conn->prepare("SELECT id, username, full_name, password_hash FROM admins WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }
    
    $token = base64_encode(json_encode([
        'admin_id' => $admin['id'],
        'username' => $admin['username'],
        'exp' => time() + (7 * 24 * 60 * 60)
    ]));
    
    // Start session
    session_start();
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    
    // Log login
    $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, action, ip_address) VALUES (?, 'admin_login', ?)");
    $logStmt->execute([$admin['id'], $_SERVER['REMOTE_ADDR']]);
    
    echo json_encode([
        'success' => true,
        'token' => $token,
        'admin' => [
            'id' => $admin['id'],
            'username' => $admin['username'],
            'full_name' => $admin['full_name']
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>