<?php
// /api/admin/tasks.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
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
    
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $stmt = $conn->query("SELECT * FROM tasks ORDER BY id DESC");
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $tasks]);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Toggle action
        if (isset($input['action']) && $input['action'] == 'toggle' && isset($input['id'])) {
            $stmt = $conn->prepare("SELECT is_active FROM tasks WHERE id = ?");
            $stmt->execute([$input['id']]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            $newStatus = $task['is_active'] ? 0 : 1;
            $updateStmt = $conn->prepare("UPDATE tasks SET is_active = ? WHERE id = ?");
            $updateStmt->execute([$newStatus, $input['id']]);
            echo json_encode(['success' => true, 'message' => 'Task toggled']);
            exit;
        }
        
        // Create or update
        $id = isset($input['id']) ? intval($input['id']) : 0;
        $title = isset($input['title']) ? trim($input['title']) : '';
        $description = isset($input['description']) ? trim($input['description']) : '';
        $task_type = isset($input['task_type']) ? $input['task_type'] : 'website';
        $url = isset($input['url']) ? trim($input['url']) : '';
        $reward_amount = isset($input['reward_amount']) ? floatval($input['reward_amount']) : 0;
        $timer_seconds = isset($input['timer_seconds']) ? intval($input['timer_seconds']) : 30;
        $daily_limit = isset($input['daily_limit']) ? intval($input['daily_limit']) : 5;
        $icon = isset($input['icon']) ? $input['icon'] : 'fa-link';
        $is_one_time = isset($input['is_one_time']) ? intval($input['is_one_time']) : 0;
        $is_repeatable = isset($input['is_repeatable']) ? intval($input['is_repeatable']) : 0;
        $is_active = isset($input['is_active']) ? intval($input['is_active']) : 1;
        
        if (empty($title) || empty($url) || $reward_amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Title, URL, and reward are required']);
            exit;
        }
        
        if ($id > 0) {
            $stmt = $conn->prepare("
                UPDATE tasks SET 
                    title = ?, description = ?, task_type = ?, url = ?, 
                    reward_amount = ?, timer_seconds = ?, daily_limit = ?, 
                    icon = ?, is_one_time = ?, is_repeatable = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$title, $description, $task_type, $url, $reward_amount, $timer_seconds, $daily_limit, $icon, $is_one_time, $is_repeatable, $is_active, $id]);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO tasks (title, description, task_type, url, reward_amount, timer_seconds, daily_limit, icon, is_one_time, is_repeatable, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$title, $description, $task_type, $url, $reward_amount, $timer_seconds, $daily_limit, $icon, $is_one_time, $is_repeatable, $is_active]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Task saved']);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Task deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Task ID required']);
        }
        exit;
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>