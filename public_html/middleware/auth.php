<?php
// backend/middleware/auth.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/JWTHandler.php';

/**
 * Authenticate user using JWT token
 * 
 * @return array Decoded user data from token
 * @throws Exception If authentication fails
 */
function authenticate() {
    // Get token from header
    $token = getTokenFromHeader();
    
    if (!$token) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized - No token provided',
            'code' => 'NO_TOKEN'
        ]);
        exit;
    }
    
    // Verify token
    $decoded = JWTHandler::verify($token);
    
    if (!$decoded) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized - Invalid or expired token',
            'code' => 'INVALID_TOKEN'
        ]);
        exit;
    }
    
    // Check if user exists and is active
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT id, full_name, phone_number, is_active, is_blocked, is_verified 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$decoded['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized - User not found',
            'code' => 'USER_NOT_FOUND'
        ]);
        exit;
    }
    
    if (!$user['is_active']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Account is inactive. Please contact support.',
            'code' => 'ACCOUNT_INACTIVE'
        ]);
        exit;
    }
    
    if ($user['is_blocked']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Account is blocked. Please contact support.',
            'code' => 'ACCOUNT_BLOCKED'
        ]);
        exit;
    }
    
    // Return user data
    return [
        'user_id' => $user['id'],
        'full_name' => $user['full_name'],
        'phone_number' => $user['phone_number'],
        'is_verified' => $user['is_verified'],
        'is_active' => $user['is_active'],
        'is_blocked' => $user['is_blocked']
    ];
}

/**
 * Get token from Authorization header
 * 
 * @return string|null Token or null if not found
 */
function getTokenFromHeader() {
    $headers = getallheaders();
    
    // Try different header formats
    $authHeader = '';
    
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    } elseif (isset($headers['authorization'])) {
        $authHeader = $headers['authorization'];
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    
    // Check if it's a Bearer token
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        return $matches[1];
    }
    
    // Also check for token in query string (for debugging)
    if (isset($_GET['token'])) {
        return $_GET['token'];
    }
    
    return null;
}

/**
 * Get user ID from token without full authentication
 * 
 * @return int|null User ID or null if invalid
 */
function getUserIdFromToken() {
    $token = getTokenFromHeader();
    
    if (!$token) {
        return null;
    }
    
    $decoded = JWTHandler::verify($token);
    
    if (!$decoded) {
        return null;
    }
    
    return $decoded['user_id'] ?? null;
}

/**
 * Check if user is authenticated (returns boolean)
 * 
 * @return bool
 */
function isAuthenticated() {
    try {
        $token = getTokenFromHeader();
        if (!$token) {
            return false;
        }
        
        $decoded = JWTHandler::verify($token);
        if (!$decoded) {
            return false;
        }
        
        // Check if user exists
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1 AND is_blocked = 0");
        $stmt->execute([$decoded['user_id']]);
        
        return $stmt->fetch() !== false;
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Require verification status
 * 
 * @param bool $verified Whether user must be verified
 * @throws Exception
 */
function requireVerification($verified = true) {
    $user = authenticate();
    
    if ($verified && !$user['is_verified']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Phone number not verified. Please verify your phone number first.',
            'code' => 'NOT_VERIFIED'
        ]);
        exit;
    }
}

/**
 * Get current user data
 * 
 * @return array|null User data or null
 */
function getCurrentUser() {
    try {
        $token = getTokenFromHeader();
        if (!$token) {
            return null;
        }
        
        $decoded = JWTHandler::verify($token);
        if (!$decoded) {
            return null;
        }
        
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT id, full_name, phone_number, email, referral_code, 
                   wallet_balance, total_earnings, is_verified, is_active, is_blocked
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$decoded['user_id']]);
        
        return $stmt->fetch();
        
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Rate limiting middleware
 * 
 * @param string $key Rate limit key (e.g., 'api_login')
 * @param int $limit Number of requests allowed
 * @param int $window Time window in seconds
 * @return bool
 */
function rateLimit($key, $limit = 60, $window = 60) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $cacheKey = "rate_limit_{$key}_{$ip}";
    
    // Simple file-based rate limiting (for shared hosting)
    $cacheDir = __DIR__ . '/../cache/';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $cacheFile = $cacheDir . md5($cacheKey) . '.cache';
    $currentTime = time();
    
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        
        // Reset if window has passed
        if ($currentTime - $data['time'] > $window) {
            $data = ['count' => 1, 'time' => $currentTime];
        } else {
            $data['count']++;
        }
    } else {
        $data = ['count' => 1, 'time' => $currentTime];
    }
    
    file_put_contents($cacheFile, json_encode($data));
    
    if ($data['count'] > $limit) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Too many requests. Please try again later.',
            'code' => 'RATE_LIMIT_EXCEEDED',
            'retry_after' => $window - ($currentTime - $data['time'])
        ]);
        exit;
    }
    
    return true;
}

/**
 * CORS middleware
 */
function handleCORS() {
    // Allow from any origin
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400'); // 24 hours
    
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

/**
 * Security headers middleware
 */
function securityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: https:; connect-src 'self' https:;");
}

/**
 * Input validation middleware
 * 
 * @param array $data Input data
 * @param array $rules Validation rules
 * @return bool
 * @throws Exception
 */
function validateInput($data, $rules) {
    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? null;
        
        // Required check
        if (strpos($rule, 'required') !== false && empty($value)) {
            throw new Exception("Field '{$field}' is required");
        }
        
        // Type checks
        if (strpos($rule, 'numeric') !== false && !is_numeric($value)) {
            throw new Exception("Field '{$field}' must be numeric");
        }
        
        if (strpos($rule, 'email') !== false && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Field '{$field}' must be a valid email address");
        }
        
        if (strpos($rule, 'phone') !== false && !preg_match('/^[0-9]{10,15}$/', $value)) {
            throw new Exception("Field '{$field}' must be a valid phone number");
        }
        
        // Min length
        if (preg_match('/min:(\d+)/', $rule, $matches)) {
            $min = intval($matches[1]);
            if (strlen($value) < $min) {
                throw new Exception("Field '{$field}' must be at least {$min} characters");
            }
        }
        
        // Max length
        if (preg_match('/max:(\d+)/', $rule, $matches)) {
            $max = intval($matches[1]);
            if (strlen($value) > $max) {
                throw new Exception("Field '{$field}' must not exceed {$max} characters");
            }
        }
        
        // In array
        if (preg_match('/in:([^,]+(?:,[^,]+)*)/', $rule, $matches)) {
            $allowed = array_map('trim', explode(',', $matches[1]));
            if (!in_array($value, $allowed)) {
                throw new Exception("Field '{$field}' must be one of: " . implode(', ', $allowed));
            }
        }
    }
    
    return true;
}

/**
 * Sanitize input data
 * 
 * @param mixed $data Input data
 * @return mixed Sanitized data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitizeInput($value);
        }
        return $data;
    }
    
    // Remove HTML tags
    $data = strip_tags($data);
    
    // Convert special characters to HTML entities
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    
    // Trim whitespace
    $data = trim($data);
    
    return $data;
}

/**
 * Log authentication events
 * 
 * @param int $userId User ID
 * @param string $action Action performed
 * @param array $details Additional details
 */
function logAuthEvent($userId, $action, $details = []) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $userId,
        $action,
        json_encode($details),
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
}

// Auto-handle CORS and security headers
handleCORS();
securityHeaders();

// Check maintenance mode (only for non-admin requests)
function checkMaintenanceMode() {
    // Skip for admin routes
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/api/admin/') !== false) {
        return;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode'");
    $stmt->execute();
    $maintenance = $stmt->fetch()['setting_value'] ?? 'false';
    
    if ($maintenance === 'true') {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'message' => 'Application is under maintenance. Please try again later.',
            'code' => 'MAINTENANCE_MODE'
        ]);
        exit;
    }
}

// Check maintenance mode
checkMaintenanceMode();