<?php
// /api/notifications/mark-read.php
// POST { "id": <int> }  -> marks one of the user's own notifications read
// POST { "all": true }  -> marks all of the user's notifications read

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/classes/Database.php';

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
$input = json_decode(file_get_contents('php://input'), true);

try {
    $conn = Database::getInstance()->getConnection();

    if (!empty($input['all'])) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
        exit;
    }

    $id = isset($input['id']) ? intval($input['id']) : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Notification id required']);
        exit;
    }

    // Scoped to user_id too — without this, any logged-in user could mark
    // an arbitrary notification id read just by guessing IDs.
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);

    echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
} catch (PDOException $e) {
    error_log('PDO error in ./api/notifications/mark-read.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again later.']);
}
