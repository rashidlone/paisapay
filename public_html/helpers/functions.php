<?php
// backend/helpers/functions.php

function generateReferralCode($length = 8) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}


function formatCurrency($amount) {
    $amount = floatval($amount);
    return '₹' . number_format($amount, 2);
}

function getClientIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    
    return $ip;
}

function getDeviceFingerprint() {
    $data = [
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
        $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? ''
    ];
    
    return md5(implode('||', $data));
}

function logActivity($userId, $action, $details = null) {
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
}

function getSetting($key, $default = null) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    
    return $result ? $result['setting_value'] : $default;
}

function updateSetting($key, $value) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        UPDATE settings 
        SET setting_value = ?, updated_at = NOW() 
        WHERE setting_key = ?
    ");
    return $stmt->execute([$value, $key]);
}

function validatePhone($phone) {
    return preg_match('/^[0-9]{10,15}$/', $phone);
}

function validateReferralCode($code) {
    return preg_match('/^[A-Z0-9]{8}$/', $code);
}

function sendNotification($userId, $title, $message, $type = 'in_app') {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, type, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    return $stmt->execute([$userId, $title, $message, $type]);
}

function checkFraud($userId) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check for multiple accounts from same device
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT u.id) as count
        FROM users u
        JOIN user_devices ud ON u.id = ud.user_id
        WHERE ud.device_id IN (
            SELECT device_id FROM user_devices WHERE user_id = ?
        )
    ");
    $stmt->execute([$userId]);
    $deviceAccounts = $stmt->fetch()['count'];
    
    if ($deviceAccounts > 3) {
        // Flag for fraud
        $stmt = $conn->prepare("
            INSERT INTO fraud_reports (user_id, fraud_type, description, status, created_at) 
            VALUES (?, 'multiple_accounts', ?, 'pending', NOW())
        ");
        $stmt->execute([
            $userId,
            "User has $deviceAccounts accounts from same device"
        ]);
        return true;
    }
    
    return false;
}

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