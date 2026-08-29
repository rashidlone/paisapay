<?php
// backend/classes/Withdrawal.php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Wallet.php';

/**
 * Withdrawal Class
 * Handles withdrawal operations
 */
class Withdrawal {
    /**
     * @var int Withdrawal ID
     */
    private $id;
    
    /**
     * @var array Withdrawal data
     */
    private $data;
    
    /**
     * @var Database Database instance
     */
    private $db;
    
    /**
     * Constructor
     * 
     * @param int|null $withdrawalId Withdrawal ID (optional)
     */
    public function __construct($withdrawalId = null) {
        $this->db = Database::getInstance();
        if ($withdrawalId) {
            $this->id = $withdrawalId;
            $this->load();
        }
    }
    
    /**
     * Load withdrawal data
     * 
     * @throws Exception If withdrawal not found
     */
    private function load() {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM withdrawals WHERE id = ?");
        $stmt->execute([$this->id]);
        $this->data = $stmt->fetch();
        
        if (!$this->data) {
            throw new Exception('Withdrawal not found');
        }
    }
    
    /**
     * Get withdrawal data
     * 
     * @return array
     */
    public function getData() {
        return $this->data;
    }
    
    /**
     * Create a withdrawal request
     * 
     * @param int $userId User ID
     * @param float $amount Withdrawal amount
     * @param string $paymentMethod Payment method
     * @param array $accountDetails Account details
     * @return int Withdrawal ID
     * @throws Exception
     */
    public static function create($userId, $amount, $paymentMethod, $accountDetails) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Validate user
        $stmt = $conn->prepare("
            SELECT id, full_name, wallet_balance, is_verified, is_active, is_blocked 
            FROM users WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        if ($user['is_blocked']) {
            throw new Exception('User is blocked');
        }
        
        if (!$user['is_active']) {
            throw new Exception('User is inactive');
        }
        
        if (!$user['is_verified']) {
            throw new Exception('User not verified');
        }
        
        // Get settings
        $stmt = $conn->prepare("
            SELECT setting_key, setting_value 
            FROM settings 
            WHERE setting_key IN ('min_withdrawal', 'max_withdrawal', 'required_referrals', 'required_tasks', 'daily_withdrawal_limit')
        ");
        $stmt->execute();
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        // Validate amount
        $min = floatval($settings['min_withdrawal'] ?? 2000);
        $max = floatval($settings['max_withdrawal'] ?? 50000);
        
        if ($amount < $min) {
            throw new Exception("Minimum withdrawal amount is ₹" . number_format($min, 2));
        }
        
        if ($amount > $max) {
            throw new Exception("Maximum withdrawal amount is ₹" . number_format($max, 2));
        }
        
        if ($amount > $user['wallet_balance']) {
            throw new Exception("Insufficient balance. Available: ₹" . number_format($user['wallet_balance'], 2));
        }
        
        // Check requirements
        self::checkRequirements($userId, $settings);
        
        // Check daily limit
        $dailyLimit = intval($settings['daily_withdrawal_limit'] ?? 3);
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM withdrawals 
            WHERE user_id = ? 
            AND DATE(created_at) = CURDATE()
            AND status NOT IN ('rejected', 'cancelled')
        ");
        $stmt->execute([$userId]);
        $dailyCount = $stmt->fetch()['count'];
        
        if ($dailyCount >= $dailyLimit) {
            throw new Exception("Daily withdrawal limit reached. Maximum {$dailyLimit} withdrawals per day.");
        }
        
        // Check pending
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM withdrawals 
            WHERE user_id = ? 
            AND status IN ('pending', 'under_review')
        ");
        $stmt->execute([$userId]);
        $pendingCount = $stmt->fetch()['count'];
        
        if ($pendingCount > 0) {
            throw new Exception("You have a pending withdrawal request");
        }
        
        // Validate payment method
        $stmt = $conn->prepare("
            SELECT id, method_name, display_name 
            FROM payment_methods 
            WHERE method_name = ? AND is_enabled = 1
        ");
        $stmt->execute([$paymentMethod]);
        $method = $stmt->fetch();
        
        if (!$method) {
            throw new Exception("Payment method not available");
        }
        
        // Validate account details
        self::validateAccountDetails($paymentMethod, $accountDetails);
        
        $db->beginTransaction();
        
        try {
            // Create withdrawal
            $stmt = $conn->prepare("
                INSERT INTO withdrawals (
                    user_id, amount, payment_method, account_details,
                    status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, 'pending', NOW(), NOW())
            ");
            $stmt->execute([
                $userId,
                $amount,
                $paymentMethod,
                json_encode($accountDetails)
            ]);
            
            $withdrawalId = $conn->lastInsertId();
            
            // Deduct from wallet
            $wallet = new Wallet($userId);
            $wallet->deduct(
                $amount,
                'withdrawal',
                "Withdrawal request #{$withdrawalId} via {$paymentMethod}",
                $withdrawalId
            );
            
            // Log activity
            $stmt = $conn->prepare("
                INSERT INTO activity_logs (user_id, action, details, created_at) 
                VALUES (?, 'withdrawal_request', ?, NOW())
            ");
            $stmt->execute([
                $userId,
                json_encode([
                    'withdrawal_id' => $withdrawalId,
                    'amount' => $amount,
                    'payment_method' => $paymentMethod
                ])
            ]);
            
            // Send notification
            $stmt = $conn->prepare("
                INSERT INTO notifications (user_id, title, message, type, created_at) 
                VALUES (?, 'Withdrawal Request Submitted', ?, 'in_app', NOW())
            ");
            $stmt->execute([
                $userId,
                "Your withdrawal request of ₹" . number_format($amount, 2) . " has been submitted. We'll process it within 24-48 hours."
            ]);
            
            $db->commit();
            return $withdrawalId;
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
    /**
     * Check withdrawal requirements
     * 
     * @param int $userId User ID
     * @param array $settings Settings array
     * @throws Exception
     */
    private static function checkRequirements($userId, $settings) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Check referrals
        $requiredReferrals = intval($settings['required_referrals'] ?? 10);
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM referrals 
            WHERE referrer_id = ? AND is_rewarded = 1
        ");
        $stmt->execute([$userId]);
        $referralCount = $stmt->fetch()['count'];
        
        if ($referralCount < $requiredReferrals) {
            throw new Exception("Need {$requiredReferrals} referrals to withdraw. You have {$referralCount}");
        }
        
        // Check tasks
        $requiredTasks = intval($settings['required_tasks'] ?? 10);
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM task_history 
            WHERE user_id = ? AND is_claimed = 1
        ");
        $stmt->execute([$userId]);
        $taskCount = $stmt->fetch()['count'];
        
        if ($taskCount < $requiredTasks) {
            throw new Exception("Need {$requiredTasks} tasks to withdraw. You have completed {$taskCount}");
        }
    }
    
    /**
     * Validate account details based on payment method
     * 
     * @param string $method Payment method
     * @param array $details Account details
     * @throws Exception
     */
    private static function validateAccountDetails($method, $details) {
        switch ($method) {
            case 'upi':
                if (empty($details['upi_id'])) {
                    throw new Exception('UPI ID is required');
                }
                if (!preg_match('/^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+$/', $details['upi_id'])) {
                    throw new Exception('Invalid UPI ID format');
                }
                break;
                
            case 'phonepe':
            case 'paytm':
            case 'googlepay':
                if (empty($details['phone'])) {
                    throw new Exception('Phone number is required');
                }
                if (!preg_match('/^[0-9]{10}$/', $details['phone'])) {
                    throw new Exception('Invalid phone number. Must be 10 digits.');
                }
                break;
                
            case 'bank_transfer':
                if (empty($details['account_number'])) {
                    throw new Exception('Account number is required');
                }
                if (empty($details['ifsc'])) {
                    throw new Exception('IFSC code is required');
                }
                if (empty($details['account_holder'])) {
                    throw new Exception('Account holder name is required');
                }
                if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $details['ifsc'])) {
                    throw new Exception('Invalid IFSC code format');
                }
                break;
                
            case 'usdt':
                if (empty($details['wallet_address'])) {
                    throw new Exception('USDT wallet address is required');
                }
                if (strlen($details['wallet_address']) < 20) {
                    throw new Exception('Invalid USDT wallet address');
                }
                break;
                
            default:
                throw new Exception("Unsupported payment method");
        }
    }
    
    /**
     * Update withdrawal status
     * 
     * @param int $withdrawalId Withdrawal ID
     * @param string $status New status
     * @param string $notes Admin notes
     * @param int $adminId Admin ID
     * @return bool
     * @throws Exception
     */
    public static function updateStatus($withdrawalId, $status, $notes, $adminId) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $validStatuses = ['under_review', 'approved', 'rejected', 'paid'];
        if (!in_array($status, $validStatuses)) {
            throw new Exception('Invalid status');
        }
        
        $db->beginTransaction();
        
        try {
            // Get withdrawal
            $stmt = $conn->prepare("SELECT * FROM withdrawals WHERE id = ?");
            $stmt->execute([$withdrawalId]);
            $withdrawal = $stmt->fetch();
            
            if (!$withdrawal) {
                throw new Exception('Withdrawal not found');
            }
            
            // Update withdrawal
            $stmt = $conn->prepare("
                UPDATE withdrawals 
                SET status = ?, admin_notes = ?, processed_by = ?,
                    processed_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $notes, $adminId, $withdrawalId]);
            
            // If rejected, refund money
            if ($status === 'rejected' && $withdrawal['status'] !== 'rejected') {
                $wallet = new Wallet($withdrawal['user_id']);
                $wallet->add(
                    $withdrawal['amount'],
                    'credit',
                    "Refund for rejected withdrawal #{$withdrawalId}",
                    $withdrawalId
                );
                
                // Send notification
                $stmt = $conn->prepare("
                    INSERT INTO notifications (user_id, title, message, type, created_at) 
                    VALUES (?, 'Withdrawal Rejected', ?, 'in_app', NOW())
                ");
                $stmt->execute([
                    $withdrawal['user_id'],
                    "Your withdrawal request #{$withdrawalId} for ₹" . number_format($withdrawal['amount'], 2) . " has been rejected. Reason: {$notes}"
                ]);
            }
            
            // If paid, send notification
            if ($status === 'paid' && $withdrawal['status'] !== 'paid') {
                $stmt = $conn->prepare("
                    INSERT INTO notifications (user_id, title, message, type, created_at) 
                    VALUES (?, 'Withdrawal Completed', ?, 'in_app', NOW())
                ");
                $stmt->execute([
                    $withdrawal['user_id'],
                    "Your withdrawal request #{$withdrawalId} for ₹" . number_format($withdrawal['amount'], 2) . " has been paid successfully."
                ]);
            }
            
            // Log activity
            $stmt = $conn->prepare("
                INSERT INTO activity_logs (admin_id, user_id, action, details, created_at) 
                VALUES (?, ?, 'withdrawal_status', ?, NOW())
            ");
            $stmt->execute([
                $adminId,
                $withdrawal['user_id'],
                json_encode([
                    'withdrawal_id' => $withdrawalId,
                    'old_status' => $withdrawal['status'],
                    'new_status' => $status,
                    'notes' => $notes
                ])
            ]);
            
            $db->commit();
            return true;
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
    /**
     * Get withdrawals by user
     * 
     * @param int $userId User ID
     * @param int $limit Limit results
     * @return array
     */
    public static function getByUser($userId, $limit = 50) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT * FROM withdrawals 
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get withdrawals by status
     * 
     * @param string $status Status filter
     * @param int $limit Limit results
     * @return array
     */
    public static function getByStatus($status, $limit = 50) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT w.*, u.full_name, u.phone_number 
            FROM withdrawals w
            JOIN users u ON w.user_id = u.id
            WHERE w.status = ?
            ORDER BY w.created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$status, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get withdrawal statistics
     * 
     * @return array
     */
    public static function getStats() {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
                COALESCE(SUM(CASE WHEN status IN ('pending', 'under_review') THEN amount ELSE 0 END), 0) as pending_amount,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) as paid_amount,
                COALESCE(SUM(CASE WHEN status = 'rejected' THEN amount ELSE 0 END), 0) as rejected_amount,
                COALESCE(SUM(amount), 0) as total_amount
            FROM withdrawals
        ");
        $stmt->execute();
        $stats = $stmt->fetch();
        
        return [
            'total' => intval($stats['total'] ?? 0),
            'pending' => intval($stats['pending'] ?? 0),
            'under_review' => intval($stats['under_review'] ?? 0),
            'approved' => intval($stats['approved'] ?? 0),
            'rejected' => intval($stats['rejected'] ?? 0),
            'paid' => intval($stats['paid'] ?? 0),
            'pending_amount' => floatval($stats['pending_amount'] ?? 0),
            'paid_amount' => floatval($stats['paid_amount'] ?? 0),
            'rejected_amount' => floatval($stats['rejected_amount'] ?? 0),
            'total_amount' => floatval($stats['total_amount'] ?? 0)
        ];
    }
}