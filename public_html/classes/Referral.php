<?php
// backend/classes/Referral.php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Wallet.php';
require_once __DIR__ . '/User.php';

/**
 * Referral Class
 * Handles referral system operations
 */
class Referral {
    /**
     * @var int Referral ID
     */
    private $id;
    
    /**
     * @var array Referral data
     */
    private $data;
    
    /**
     * @var Database Database instance
     */
    private $db;
    
    /**
     * Constructor
     * 
     * @param int|null $referralId Referral ID (optional)
     */
    public function __construct($referralId = null) {
        $this->db = Database::getInstance();
        if ($referralId) {
            $this->id = $referralId;
            $this->load();
        }
    }
    
    /**
     * Load referral data
     * 
     * @throws Exception If referral not found
     */
    private function load() {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM referrals WHERE id = ?");
        $stmt->execute([$this->id]);
        $this->data = $stmt->fetch();
        
        if (!$this->data) {
            throw new Exception('Referral not found');
        }
    }
    
    /**
     * Get referral data
     * 
     * @return array
     */
    public function getData() {
        return $this->data;
    }
    
    /**
     * Create a new referral
     * 
     * @param int $referrerId Referrer user ID
     * @param int $referredUserId Referred user ID
     * @param string $referralCode Referral code used
     * @return int Referral ID
     * @throws Exception
     */
    public static function create($referrerId, $referredUserId, $referralCode) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Check if already referred
        $stmt = $conn->prepare("
            SELECT id FROM referrals 
            WHERE referrer_id = ? AND referred_user_id = ?
        ");
        $stmt->execute([$referrerId, $referredUserId]);
        if ($stmt->fetch()) {
            throw new Exception('User already referred by this referrer');
        }
        
        // Check self-referral
        if ($referrerId == $referredUserId) {
            throw new Exception('Cannot refer yourself');
        }
        
        // Get referral bonus from settings
        $stmt = $conn->prepare("
            SELECT setting_value FROM settings WHERE setting_key = 'referral_bonus'
        ");
        $stmt->execute();
        $bonus = $stmt->fetch()['setting_value'] ?? 100.00;
        
        // Get daily limit
        $stmt = $conn->prepare("
            SELECT setting_value FROM settings WHERE setting_key = 'daily_referral_limit'
        ");
        $stmt->execute();
        $dailyLimit = $stmt->fetch()['setting_value'] ?? 5;
        
        // Check daily limit
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM referrals 
            WHERE referrer_id = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$referrerId]);
        $todayCount = $stmt->fetch()['count'];
        
        if ($todayCount >= $dailyLimit) {
            throw new Exception('Daily referral limit reached');
        }
        
        $db->beginTransaction();
        
        try {
            // Insert referral
            $stmt = $conn->prepare("
                INSERT INTO referrals (
                    referrer_id, referred_user_id, referral_code,
                    reward_amount, is_rewarded, reward_date, created_at
                ) VALUES (?, ?, ?, ?, 1, NOW(), NOW())
            ");
            $stmt->execute([
                $referrerId,
                $referredUserId,
                $referralCode,
                $bonus
            ]);
            
            $referralId = $conn->lastInsertId();
            
            // Give reward to referrer
            $wallet = new Wallet($referrerId);
            $wallet->add(
                $bonus,
                'referral',
                "Referral bonus from user #{$referredUserId}",
                $referralId
            );
            
            // Log activity
            $stmt = $conn->prepare("
                INSERT INTO activity_logs (user_id, action, details, created_at) 
                VALUES (?, 'referral_created', ?, NOW())
            ");
            $stmt->execute([
                $referrerId,
                json_encode([
                    'referral_id' => $referralId,
                    'referred_user_id' => $referredUserId
                ])
            ]);
            
            $db->commit();
            return $referralId;
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
    /**
     * Get referrals by referrer
     * 
     * @param int $referrerId Referrer user ID
     * @param int $limit Limit results
     * @return array
     */
    public static function getByReferrer($referrerId, $limit = 50) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT 
                r.*,
                u.full_name as referred_name,
                u.phone_number as referred_phone,
                u.created_at as referred_joined_at
            FROM referrals r
            JOIN users u ON r.referred_user_id = u.id
            WHERE r.referrer_id = ?
            ORDER BY r.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$referrerId, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get referral statistics
     * 
     * @param int $referrerId Referrer user ID
     * @return array
     */
    public static function getStats($referrerId) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN is_rewarded = 1 THEN 1 ELSE 0 END) as rewarded,
                SUM(CASE WHEN is_valid = 1 THEN 1 ELSE 0 END) as valid,
                SUM(CASE WHEN is_valid = 0 THEN 1 ELSE 0 END) as invalid,
                COALESCE(SUM(reward_amount), 0) as total_reward,
                AVG(reward_amount) as average_reward,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
            FROM referrals 
            WHERE referrer_id = ?
        ");
        $stmt->execute([$referrerId]);
        $stats = $stmt->fetch();
        
        // Get required referrals from settings
        $stmt = $conn->prepare("
            SELECT setting_value FROM settings WHERE setting_key = 'required_referrals'
        ");
        $stmt->execute();
        $required = $stmt->fetch()['setting_value'] ?? 10;
        
        return [
            'total' => intval($stats['total'] ?? 0),
            'rewarded' => intval($stats['rewarded'] ?? 0),
            'valid' => intval($stats['valid'] ?? 0),
            'invalid' => intval($stats['invalid'] ?? 0),
            'total_reward' => floatval($stats['total_reward'] ?? 0),
            'average_reward' => floatval($stats['average_reward'] ?? 0),
            'today' => intval($stats['today'] ?? 0),
            'required' => intval($required),
            'progress' => min(100, (intval($stats['total'] ?? 0) / intval($required)) * 100)
        ];
    }
    
    /**
     * Validate referral code
     * 
     * @param string $code Referral code to validate
     * @return array|null User data or null if invalid
     */
    public static function validateCode($code) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT id, full_name, referral_code 
            FROM users 
            WHERE referral_code = ? AND is_active = 1 AND is_blocked = 0
        ");
        $stmt->execute([$code]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return null;
        }
        
        // Check if code expired
        $stmt = $conn->prepare("
            SELECT setting_value FROM settings WHERE setting_key = 'referral_expiry_days'
        ");
        $stmt->execute();
        $expiryDays = $stmt->fetch()['setting_value'] ?? 30;
        
        $stmt = $conn->prepare("
            SELECT created_at FROM users WHERE referral_code = ?
        ");
        $stmt->execute([$code]);
        $createdAt = $stmt->fetch()['created_at'];
        
        $expiryDate = strtotime($createdAt) + ($expiryDays * 86400);
        if (time() > $expiryDate) {
            return null; // Code expired
        }
        
        return $user;
    }
    
    /**
     * Mark referral as invalid
     * 
     * @param int $referralId Referral ID
     * @param string $reason Reason for invalidation
     * @return bool
     */
    public static function markInvalid($referralId, $reason) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $db->beginTransaction();
        
        try {
            // Get referral
            $stmt = $conn->prepare("SELECT * FROM referrals WHERE id = ?");
            $stmt->execute([$referralId]);
            $referral = $stmt->fetch();
            
            if (!$referral) {
                throw new Exception('Referral not found');
            }
            
            // Update referral
            $stmt = $conn->prepare("
                UPDATE referrals 
                SET is_valid = 0, validation_notes = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$reason, $referralId]);
            
            // If rewarded, reverse reward
            if ($referral['is_rewarded']) {
                $wallet = new Wallet($referral['referrer_id']);
                $wallet->deduct(
                    $referral['reward_amount'],
                    'admin_adjustment',
                    "Referral reward reversed: {$reason}",
                    $referralId
                );
            }
            
            $db->commit();
            return true;
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
}