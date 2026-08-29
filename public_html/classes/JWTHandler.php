<?php
// backend/classes/JWTHandler.php

/**
 * JWT Handler Class
 * Handles JSON Web Token generation, verification, and management
 * Uses HMAC SHA256 algorithm for signing
 */
class JWTHandler {
    /**
     * @var string Secret key for signing tokens
     */
    private static $secret = 'YOUR_JWT_SECRET_KEY_CHANGE_THIS';
    
    /**
     * @var string Algorithm used for signing
     */
    private static $algorithm = 'HS256';
    
    /**
     * @var int Token expiry time in seconds (default: 7 days)
     */
    private static $expiry = 604800;
    
    /**
     * @var string Issuer of the token
     */
    private static $issuer = 'paisapay';
    
    /**
     * Initialize JWT handler with configuration
     * 
     * @param array $config Configuration array
     */
    public static function init($config = []) {
        if (isset($config['secret'])) {
            self::$secret = $config['secret'];
        }
        if (isset($config['algorithm'])) {
            self::$algorithm = $config['algorithm'];
        }
        if (isset($config['expiry'])) {
            self::$expiry = $config['expiry'];
        }
        if (isset($config['issuer'])) {
            self::$issuer = $config['issuer'];
        }
    }
    
    /**
     * Generate a JWT token
     * 
     * @param array $payload Data to encode in token
     * @param int|null $expiry Custom expiry time in seconds
     * @return string JWT token
     */
    public static function generate($payload, $expiry = null) {
        $issuedAt = time();
        $expireTime = $expiry ?? self::$expiry;
        
        // Prepare header
        $header = [
            'typ' => 'JWT',
            'alg' => self::$algorithm
        ];
        
        // Prepare payload
        $payload = array_merge($payload, [
            'iat' => $issuedAt,
            'exp' => $issuedAt + $expireTime,
            'iss' => self::$issuer,
            'jti' => uniqid() // Unique token ID
        ]);
        
        // Encode header and payload
        $base64UrlHeader = self::base64UrlEncode(json_encode($header));
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));
        
        // Create signature
        $signature = hash_hmac(
            'sha256',
            $base64UrlHeader . '.' . $base64UrlPayload,
            self::$secret,
            true
        );
        $base64UrlSignature = self::base64UrlEncode($signature);
        
        // Combine all parts
        $token = $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
        
        return $token;
    }
    
    /**
     * Verify and decode a JWT token
     * 
     * @param string $token JWT token to verify
     * @return array|null Decoded payload or null if invalid
     */
    public static function verify($token) {
        // Split token into parts
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        
        list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;
        
        // Verify signature
        $signature = self::base64UrlDecode($base64UrlSignature);
        $expectedSignature = hash_hmac(
            'sha256',
            $base64UrlHeader . '.' . $base64UrlPayload,
            self::$secret,
            true
        );
        
        if (!hash_equals($signature, $expectedSignature)) {
            return null;
        }
        
        // Decode payload
        $payload = json_decode(self::base64UrlDecode($base64UrlPayload), true);
        if (!$payload) {
            return null;
        }
        
        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }
        
        // Check issuer
        if (isset($payload['iss']) && $payload['iss'] !== self::$issuer) {
            return null;
        }
        
        return $payload;
    }
    
    /**
     * Get user ID from JWT token
     * 
     * @param string|null $token JWT token (null to get from Authorization header)
     * @return int|null User ID or null if invalid
     */
    public static function getUserId($token = null) {
        if (!$token) {
            $token = self::getTokenFromHeader();
        }
        
        if (!$token) {
            return null;
        }
        
        $decoded = self::verify($token);
        return $decoded ? $decoded['user_id'] ?? null : null;
    }
    
    /**
     * Get token from Authorization header
     * 
     * @return string|null Token or null if not found
     */
    public static function getTokenFromHeader() {
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        if (empty($authHeader)) {
            // Try lowercase
            $authHeader = isset($headers['authorization']) ? $headers['authorization'] : '';
        }
        
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Refresh a token (extend expiry)
     * 
     * @param string $token Current token
     * @return string|null New token or null if invalid
     */
    public static function refresh($token) {
        $decoded = self::verify($token);
        if (!$decoded) {
            return null;
        }
        
        // Remove expiry fields
        unset($decoded['exp']);
        unset($decoded['iat']);
        
        // Generate new token
        return self::generate($decoded);
    }
    
    /**
     * Check if token is about to expire
     * 
     * @param string $token JWT token
     * @param int $threshold Seconds before expiry to consider as 'about to expire'
     * @return bool
     */
    public static function isExpiringSoon($token, $threshold = 3600) {
        $decoded = self::verify($token);
        if (!$decoded || !isset($decoded['exp'])) {
            return false;
        }
        
        $timeLeft = $decoded['exp'] - time();
        return $timeLeft < $threshold;
    }
    
    /**
     * Get token expiry time
     * 
     * @param string $token JWT token
     * @return int|null Unix timestamp or null if invalid
     */
    public static function getExpiryTime($token) {
        $decoded = self::verify($token);
        return $decoded ? $decoded['exp'] ?? null : null;
    }
    
    /**
     * Get token payload without verification (for debugging only)
     * 
     * @param string $token JWT token
     * @return array|null
     */
    public static function getPayload($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        
        $payload = json_decode(self::base64UrlDecode($parts[1]), true);
        return $payload;
    }
    
    /**
     * Base64 URL encode
     * 
     * @param string $data Data to encode
     * @return string Encoded data
     */
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL decode
     * 
     * @param string $data Data to decode
     * @return string Decoded data
     */
    private static function base64UrlDecode($data) {
        $data = strtr($data, '-_', '+/');
        $data = str_pad($data, strlen($data) % 4, '=', STR_PAD_RIGHT);
        return base64_decode($data);
    }
}

// Auto-initialize with config if defined
if (defined('JWT_SECRET')) {
    JWTHandler::init(['secret' => JWT_SECRET]);
}