<?php
// /config/config.php - COMPLETE FIXED VERSION

// ============================================
// ✅ ERROR REPORTING - GLOBAL CONTROL
// ============================================

// This will be overridden by admin setting
$GLOBALS['DEBUG_MODE'] = false;

// ============================================
// ✅ DATABASE CONFIGURATION
// ============================================

if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'u226625657_paisapay');
if (!defined('DB_USER')) define('DB_USER', 'u226625657_paisapay');
if (!defined('DB_PASS')) define('DB_PASS', 'PaisaPay@2026###');

// ============================================
// ✅ TIMEZONE - FORCE IST
// ============================================

date_default_timezone_set('Asia/Kolkata');
if (!defined('APP_TIMEZONE')) define('APP_TIMEZONE', 'Asia/Kolkata');

// ============================================
// ✅ SMTP CONFIGURATION
// ============================================

if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.gmail.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USER')) define('SMTP_USER', 'noreply.paisapay@gmail.com');
if (!defined('SMTP_PASS')) define('SMTP_PASS', 'avfitjicggmmolsc');
if (!defined('SMTP_FROM')) define('SMTP_FROM', 'noreply.paisapay@gmail.com');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'PaisaPay');

// ============================================
// ✅ JWT CONFIGURATION
// ============================================

if (!defined('JWT_SECRET')) define('JWT_SECRET', 'hgdiwe-akhdiw-whiwhd-wuwubx-iwdnbwi');

// ============================================
// ✅ API CONFIGURATION
// ============================================

if (!defined('API_VERSION')) define('API_VERSION', 'v1');
if (!defined('API_BASE_URL')) define('API_BASE_URL', 'https://paisa-pay.online/api');
if (!defined('APP_URL')) define('APP_URL', 'https://paisa-pay.online');

// ============================================
// ✅ FIREBASE CONFIGURATION
// ============================================

if (!defined('FIREBASE_PROJECT_ID')) define('FIREBASE_PROJECT_ID', 'paisapay-in');
if (!defined('FIREBASE_API_KEY')) define('FIREBASE_API_KEY', 'AIzaSyDlPWFmNHNGFawZUvYLFL2T5_71IclREHI');
if (!defined('FIREBASE_SERVER_KEY')) define('FIREBASE_SERVER_KEY', 'AAAA7IuPuhI:APA91bH2xHYrZ3lFc9t5NtGxXXq3W7XZxjPqQmUjxFlLpKVlM3Q4mI1xvMxXyM5Vz4pPpVjF9xMpzQ');

// ============================================
// ✅ APP CONFIGURATION
// ============================================

if (!defined('APP_NAME')) define('APP_NAME', 'PaisaPay');
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');
if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', '₹');

// ============================================
// ✅ SECURITY
// ============================================

if (!defined('RATE_LIMIT_REQUESTS')) define('RATE_LIMIT_REQUESTS', 60);
if (!defined('RATE_LIMIT_TIME')) define('RATE_LIMIT_TIME', 60);
if (!defined('CSRF_TOKEN_NAME')) define('CSRF_TOKEN_NAME', 'csrf_token');

// ============================================
// ✅ PATHS
// ============================================

if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__));
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', BASE_PATH . '/uploads');
if (!defined('LOG_PATH')) define('LOG_PATH', BASE_PATH . '/logs');

// ============================================
// ✅ CORS HEADERS
// ============================================

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================
// ✅ GLOBAL FUNCTIONS
// ============================================

/**
 * Get debug mode from database
 */
function isDebugMode() {
    global $debug_mode_cache;
    
    if (isset($debug_mode_cache)) {
        return $debug_mode_cache;
    }
    
    try {
        $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'debug_mode'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $debug_mode_cache = ($result && $result['setting_value'] == '1');
        
        // Set error reporting based on debug mode
        if ($debug_mode_cache) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            ini_set('log_errors', 1);
        } else {
            error_reporting(0);
            ini_set('display_errors', 0);
            ini_set('log_errors', 1);
        }
        
        return $debug_mode_cache;
    } catch (PDOException $e) {
        // If DB not available, use default
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        return true;
    }
}

/**
 * Get setting from database with caching
 */
function getSetting($key, $default = null) {
    static $settings_cache = null;
    
    if ($settings_cache === null) {
        try {
            $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $conn->query("SELECT setting_key, setting_value FROM settings");
            $settings_cache = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings_cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (PDOException $e) {
            $settings_cache = [];
        }
    }
    
    return isset($settings_cache[$key]) ? $settings_cache[$key] : $default;
}

/**
 * Get debug mode (alias)
 */
function debugMode() {
    return isDebugMode();
}

/**
 * Get IST time
 */
function istTime($timestamp = null) {
    $timezone = new DateTimeZone('Asia/Kolkata');
    if ($timestamp === null) {
        return new DateTime('now', $timezone);
    }
    $dt = new DateTime('@' . $timestamp);
    $dt->setTimezone($timezone);
    return $dt;
}

/**
 * Format date in IST
 */
function formatIST($date, $format = 'Y-m-d H:i:s') {
    if (is_string($date)) {
        $dt = new DateTime($date, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
    } else if ($date instanceof DateTime) {
        $dt = clone $date;
        $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
    } else {
        $dt = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    }
    return $dt->format($format);
}

/**
 * Get currency symbol
 */
function getCurrencySymbol() {
    return getSetting('currency_symbol', '₹');
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    $symbol = getCurrencySymbol();
    return $symbol . number_format($amount, 2);
}

// Initialize debug mode
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', isDebugMode());
}

// Set error reporting based on debug mode
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_PATH . '/error.log');
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_PATH . '/error.log');
}
?>