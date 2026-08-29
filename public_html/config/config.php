<?php
// /config/config.php


// ============================================
// ✅ DATABASE CONFIGURATION
// ============================================

if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'u226625657_paisapay');
if (!defined('DB_USER')) define('DB_USER', 'u226625657_paisapay');
if (!defined('DB_PASS')) define('DB_PASS', 'PaisaPay@2026###');

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

// ============================================
// ✅ PATHS
// ============================================

if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__));
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', BASE_PATH . '/uploads');
if (!defined('LOG_PATH')) define('LOG_PATH', BASE_PATH . '/logs');

// ============================================
// ✅ ERROR REPORTING
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', LOG_PATH . '/error.log');

// ============================================
// ✅ TIMEZONE
// ============================================

date_default_timezone_set('Asia/Kolkata');

// ============================================
// ✅ CORS HEADERS
// ============================================

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}
?>