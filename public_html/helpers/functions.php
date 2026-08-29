<?php
// /helpers/functions.php

/**
 * Generate unique referral code
 */
function generateReferralCode($conn, $length = 8) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    do {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        $stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ?");
        $stmt->execute([$code]);
        $exists = $stmt->fetch();
    } while ($exists);
    return $code;
}

/**
 * Format currency with dynamic symbol
 */
function formatCurrency($amount) {
    $amount = floatval($amount);
    $symbol = getSetting('currency_symbol', '₹');
    return $symbol . number_format($amount, 2);
}

function getSetting($key, $default = null) {
    static $settings_cache = null;
    
    if ($settings_cache === null) {
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            $stmt = $conn->query("SELECT setting_key, setting_value FROM settings");
            $settings_cache = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings_cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $settings_cache = [];
        }
    }
    
    return isset($settings_cache[$key]) ? $settings_cache[$key] : $default;
}

/**
 * Format currency without symbol
 */
function formatCurrencyRaw($amount) {
    $amount = floatval($amount);
    return number_format($amount, 2);
}

/**
 * Get client IP
 */
function getClientIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    
    return $ip;
}

/**
 * Get device fingerprint
 */
function getDeviceFingerprint() {
    $data = [
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
        $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? ''
    ];
    return md5(implode('||', $data));
}

/**
 * Log activity
 */
function logActivity($userId, $action, $details = null) {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $action,
            $details ? json_encode($details) : null,
            getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log('Log activity error: ' . $e->getMessage());
    }
}

/**
 * Send notification to user
 */
function sendNotification($userId, $title, $message, $type = 'in_app') {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, type, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$userId, $title, $message, $type]);
    } catch (Exception $e) {
        error_log('Send notification error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has pending withdrawal
 */
function hasPendingWithdrawal($userId) {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM withdrawals 
            WHERE user_id = ? AND status IN ('pending', 'under_review')
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get withdrawal requirements with dynamic values
 */
function getWithdrawalRequirements($userId) {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Get settings
        $requiredTasks = intval(getSetting('required_tasks', 10));
        $requiredReferrals = intval(getSetting('required_referrals', 5));
        $minWithdrawal = floatval(getSetting('min_withdrawal', 50));
        $maxWithdrawal = floatval(getSetting('max_withdrawal', 50000));
        
        // Get user stats since last withdrawal
        $stmt = $conn->prepare("
            SELECT 
                (SELECT COUNT(*) FROM task_history 
                 WHERE user_id = ? AND is_claimed = 1 
                 AND completed_at > COALESCE(
                     (SELECT MAX(created_at) FROM withdrawals 
                      WHERE user_id = ? AND status IN ('paid', 'approved')),
                     '1970-01-01'
                 )
                ) as tasks_since_last,
                (SELECT COUNT(*) FROM referrals 
                 WHERE referrer_id = ? AND validation_status = 'verified'
                 AND created_at > COALESCE(
                     (SELECT MAX(created_at) FROM withdrawals 
                      WHERE user_id = ? AND status IN ('paid', 'approved')),
                     '1970-01-01'
                 )
                ) as referrals_since_last
        ");
        $stmt->execute([$userId, $userId, $userId, $userId]);
        $stats = $stmt->fetch();
        
        $tasksMet = $stats['tasks_since_last'] >= $requiredTasks;
        $referralsMet = $stats['referrals_since_last'] >= $requiredReferrals;
        
        return [
            'met' => $tasksMet && $referralsMet,
            'tasks_required' => $requiredTasks,
            'referrals_required' => $requiredReferrals,
            'tasks_completed' => intval($stats['tasks_since_last']),
            'referrals_completed' => intval($stats['referrals_since_last']),
            'min_withdrawal' => $minWithdrawal,
            'max_withdrawal' => $maxWithdrawal,
            'last_withdrawal_date' => $conn->query("
                SELECT MAX(created_at) FROM withdrawals 
                WHERE user_id = $userId AND status IN ('paid', 'approved')
            ")->fetchColumn() ?: 'Never'
        ];
    } catch (Exception $e) {
        error_log('Get requirements error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Validate phone number
 */
function validatePhone($phone) {
    return preg_match('/^[0-9]{10,15}$/', $phone);
}

/**
 * Validate referral code format
 */
function validateReferralCode($code) {
    return preg_match('/^[A-Z0-9]{8}$/', $code);
}

/**
 * Check for fraud
 */
function checkFraud($userId) {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Check for suspicious activity
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as fraud_reports,
                is_fraud_flag
            FROM users u
            LEFT JOIN fraud_reports fr ON u.id = fr.user_id AND fr.status = 'pending'
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        if ($result['fraud_reports'] > 0 || $result['is_fraud_flag']) {
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log('Check fraud error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Generate CSRF token
 */
function generateCsrfToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Validate CSRF token
 */
function validateCsrfToken($token) {
    if (empty($_SESSION[CSRF_TOKEN_NAME]) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Get pagination data
 */
function getPaginationData($page, $perPage, $total) {
    $page = max(1, $page);
    $totalPages = ceil($total / $perPage);
    $offset = ($page - 1) * $perPage;
    
    return [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'offset' => $offset
    ];
}

/**
 * Sanitize input
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Get user by ID with cache
 */
function getUserById($userId) {
    static $userCache = [];
    
    if (isset($userCache[$userId])) {
        return $userCache[$userId];
    }
    
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if ($user) {
            $userCache[$userId] = $user;
        }
        return $user;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Send push notification via Firebase
 */
function sendPushNotification($userId, $title, $message, $data = []) {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Get user's FCM tokens
        $stmt = $conn->prepare("SELECT fcm_token FROM user_devices WHERE user_id = ? AND fcm_token IS NOT NULL");
        $stmt->execute([$userId]);
        $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($tokens)) {
            return false;
        }
        
        $serverKey = defined('FIREBASE_SERVER_KEY') ? FIREBASE_SERVER_KEY : '';
        if (empty($serverKey)) {
            return false;
        }
        
        $payload = [
            'notification' => [
                'title' => $title,
                'body' => $message,
                'icon' => '/icon-192x192.png',
                'click_action' => '/dashboard.html'
            ],
            'data' => array_merge($data, [
                'title' => $title,
                'message' => $message,
                'click_action' => '/dashboard.html'
            ]),
            'registration_ids' => $tokens
        ];
        
        $ch = curl_init('https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: key=' . $serverKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return true;
    } catch (Exception $e) {
        error_log('Push notification error: ' . $e->getMessage());
        return false;
    }
}
?>