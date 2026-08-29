<?php
// /api/notifications/list.php
// Returns the logged-in user's recent notifications + unread count,
// for the bell icon in includes/nav.html.

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

try {
    $conn = Database::getInstance()->getConnection();

    $limit = isset($_GET['limit']) ? max(1, min(50, intval($_GET['limit']))) : 20;

    $stmt = $conn->prepare("
        SELECT id, title, message, type, is_read, created_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countStmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $countStmt->execute([$user_id]);
    $unreadCount = (int) $countStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'data' => [
            'notifications' => $notifications,
            'unread_count' => $unreadCount
        ]
    ]);
} catch (PDOException $e) {
    error_log('PDO error in ./api/notifications/list.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again later.']);
}
