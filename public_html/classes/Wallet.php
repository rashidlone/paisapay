<?php
// backend/classes/Wallet.php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/User.php';

/**
 * Wallet Class
 * Handles wallet operations for users
 */
class Wallet {
    /**
     * @var int User ID
     */
    private $userId;
    
    /**
     * @var array Wallet data
     */
    private $data;
    
    /**
     * @var Database Database instance
     */
    private $db;
    
    /**
     * Constructor
     * 
     * @param int $userId User ID
     */
    public function __construct($userId) {
        $this->userId = $userId;
        $this->db = Database::getInstance();
        $this->load();
    }
    
    /**
     * Load wallet data
     */
    private function load() {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            SELECT 
                wallet_balance,
                total_earnings,
                referral_earnings,
                task_earnings
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$this->userId]);
        $this->data = $stmt->fetch();
        
        if (!$this->data) {
            throw new Exception('User not found');
        }
    }
    
    /**
     * Get current balance
     * 
     * @return float
     */
    public function getBalance() {
        return floatval($this->data['wallet_balance'] ?? 0);
    }
    
    /**
     * Get total earnings
     * 
     * @return float
     */
    public function getTotalEarnings() {
        return floatval($this->data['total_earnings'] ?? 0);
    }
    
    /**
     * Get referral earnings
     * 
     * @return float
     */
    public function getReferralEarnings() {
        return floatval($this->data['referral_earnings'] ?? 0);
    }
    
    /**
     * Get task earnings
     * 
     * @return float
     */
    public function getTaskEarnings() {
        return floatval($this->data['task_earnings'] ?? 0);
    }
    
    /**
     * Add amount to wallet
     * 
     * @param float $amount Amount to add
     * @param string $type Transaction type
     * @param string $description Transaction description
     * @param string $referenceId Reference ID (optional)
     * @return float New balance
     * @throws Exception
     */
    public function add($amount, $type, $description, $referenceId = null) {
        if ($amount <= 0) {
            throw new Exception('Amount must be greater than 0');
        }
        
        $conn = $this->db->getConnection();
        $this->db->beginTransaction();
        
        try {
            $currentBalance = $this->getBalance();
            $newBalance = $currentBalance + $amount;
            
            // Update user
            $updateFields = 'wallet_balance = ?';
            $params = [$newBalance];
            
            if ($type === 'referral') {
                $updateFields .= ', referral_earnings = referral_earnings + ?';
                $params[] = $amount;
            } elseif ($type === 'task') {
                $updateFields .= ', task_earnings = task_earnings + ?';
                $params[] = $amount;
            } elseif ($type === 'bonus') {
                $updateFields .= ', total_earnings = total_earnings + ?';
                $params[] = $amount;
            }
            
            $updateFields .= ', updated_at = NOW()';
            $params[] = $this->userId;
            
            $stmt = $conn->prepare("UPDATE users SET {$updateFields} WHERE id = ?");
            $stmt->execute($params);
            
            // Add transaction
            $stmt = $conn->prepare("
                INSERT INTO wallet_transactions (
                    user_id, amount, transaction_type, description,
                    balance_after, status, reference_id, created_at
                ) VALUES (?, ?, ?, ?, ?, 'completed', ?, NOW())
            ");
            $stmt->execute([
                $this->userId,
                $amount,
                $type,
                $description,
                $newBalance,
                $referenceId
            ]);
            
            // Update local data
            $this->data['wallet_balance'] = $newBalance;
            
            $this->db->commit();
            return $newBalance;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Deduct amount from wallet
     * 
     * @param float $amount Amount to deduct
     * @param string $type Transaction type
     * @param string $description Transaction description
     * @param string $referenceId Reference ID (optional)
     * @return float New balance
     * @throws Exception
     */
    public function deduct($amount, $type, $description, $referenceId = null) {
        if ($amount <= 0) {
            throw new Exception('Amount must be greater than 0');
        }
        
        $currentBalance = $this->getBalance();
        if ($amount > $currentBalance) {
            throw new Exception('Insufficient balance');
        }
        
        $conn = $this->db->getConnection();
        $this->db->beginTransaction();
        
        try {
            $newBalance = $currentBalance - $amount;
            
            // Update user
            $stmt = $conn->prepare("
                UPDATE users 
                SET wallet_balance = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$newBalance, $this->userId]);
            
            // Add transaction
            $stmt = $conn->prepare("
                INSERT INTO wallet_transactions (
                    user_id, amount, transaction_type, description,
                    balance_after, status, reference_id, created_at
                ) VALUES (?, ?, ?, ?, ?, 'completed', ?, NOW())
            ");
            $stmt->execute([
                $this->userId,
                $amount,
                $type,
                $description,
                $newBalance,
                $referenceId
            ]);
            
            // Update local data
            $this->data['wallet_balance'] = $newBalance;
            
            $this->db->commit();
            return $newBalance;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Get transaction history
     * 
     * @param int $limit Number of transactions to get
     * @param int $offset Offset for pagination
     * @return array
     */
    public function getHistory($limit = 50, $offset = 0) {
        $conn = $this->db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT 
                id, amount, transaction_type, description,
                balance_after, status, reference_id, created_at
            FROM wallet_transactions 
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$this->userId, $limit, $offset]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get transaction count
     * 
     * @return int
     */
    public function getHistoryCount() {
        $conn = $this->db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM wallet_transactions 
            WHERE user_id = ?
        ");
        $stmt->execute([$this->userId]);
        return $stmt->fetch()['count'] ?? 0;
    }
    
    /**
     * Get earnings summary
     * 
     * @param string $period 'today', 'week', 'month', 'all'
     * @return array
     */
    public function getEarningsSummary($period = 'all') {
        $conn = $this->db->getConnection();
        
        $dateCondition = '';
        switch ($period) {
            case 'today':
                $dateCondition = 'AND DATE(created_at) = CURDATE()';
                break;
            case 'week':
                $dateCondition = 'AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
                break;
            case 'month':
                $dateCondition = 'AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
                break;
            default:
                $dateCondition = '';
        }
        
        $stmt = $conn->prepare("
            SELECT 
                transaction_type,
                COALESCE(SUM(amount), 0) as total
            FROM wallet_transactions 
            WHERE user_id = ? 
            AND status = 'completed'
            {$dateCondition}
            GROUP BY transaction_type
        ");
        $stmt->execute([$this->userId]);
        
        $results = $stmt->fetchAll();
        $summary = [
            'total' => 0,
            'referral' => 0,
            'task' => 0,
            'bonus' => 0,
            'withdrawal' => 0,
            'credit' => 0,
            'debit' => 0
        ];
        
        foreach ($results as $row) {
            $type = $row['transaction_type'];
            $amount = floatval($row['total']);
            
            if (in_array($type, ['referral', 'task', 'bonus', 'credit'])) {
                $summary['total'] += $amount;
            }
            
            if (isset($summary[$type])) {
                $summary[$type] = $amount;
            }
        }
        
        return $summary;
    }
}