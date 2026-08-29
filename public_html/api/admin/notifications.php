<?php
// /api/admin/notifications.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $title = isset($input['title']) ? trim($input['title']) : '';
        $message = isset($input['message']) ? trim($input['message']) : '';
        $target = isset($input['target']) ? $input['target'] : 'all';
        $type = isset($input['type']) ? $input['type'] : 'in_app';
        
        if (empty($title) || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Title and message required']);
            exit;
        }
        
        // Get target users
        $where = "1=1";
        if ($target == 'active') {
            $where = "last_activity_date > DATE_SUB(NOW(), INTERVAL 30 DAY)";
        } elseif ($target == 'verified') {
            $where = "is_verified = 1";
        }
        
        $userStmt = $conn->query("SELECT id FROM users WHERE $where");
        $users = $userStmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($users as $user_id) {
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$user_id, $title, $message, $type]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Notification sent to ' . count($users) . ' users']);
        exit;
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>