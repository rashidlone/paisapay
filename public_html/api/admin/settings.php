<?php
// /api/admin/settings.php - COMPLETE FIXED VERSION

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
require_once $_SERVER['DOCUMENT_ROOT'] . '/classes/Database.php';

try {
    $conn = Database::getInstance()->getConnection();
    
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $stmt = $conn->query("SELECT * FROM settings ORDER BY setting_key");
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $settings]);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        foreach ($input as $key => $value) {
            // Sanitize based on type
            $stmt = $conn->prepare("SELECT setting_type FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $type = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($type) {
                switch ($type['setting_type']) {
                    case 'integer':
                        $value = intval($value);
                        break;
                    case 'decimal':
                        $value = floatval($value);
                        break;
                    case 'boolean':
                        $value = $value ? '1' : '0';
                        break;
                    default:
                        $value = trim($value);
                }
                
                $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            }
        }
        
        // Clear settings cache
        $GLOBALS['settings_cache'] = null;
        $GLOBALS['debug_mode_cache'] = null;
        
        echo json_encode(['success' => true, 'message' => 'Settings updated']);
        exit;
    }
    
} catch (PDOException $e) {
    error_log('Settings error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>