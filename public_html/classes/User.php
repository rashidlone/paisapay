<?php
// backend/classes/User.php

require_once __DIR__ . '/Database.php';

class User {
    private $db;
    private $id;
    private $data;
    
    public function __construct($userId = null) {
        $this->db = Database::getInstance();
        if ($userId) {
            $this->id = $userId;
            $this->load();
        }
    }
    
    private function load() {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$this->id]);
        $this->data = $stmt->fetch();
        
        if (!$this->data) {
            throw new Exception('User not found');
        }
    }
    
    public function getData() {
        return $this->data;
    }
    
    public function getBalance() {
        return $this->data['wallet_balance'] ?? 0;
    }
    
    public function addBalance($amount) {
        $conn = $this->db->getConnection();
        $newBalance = $this->getBalance() + $amount;
        
        $stmt = $conn->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
        $stmt->execute([$newBalance, $this->id]);
        
        $this->data['wallet_balance'] = $newBalance;
        return $newBalance;
    }
    
    public function deductBalance($amount) {
        if ($amount > $this->getBalance()) {
            throw new Exception('Insufficient balance');
        }
        
        $conn = $this->db->getConnection();
        $newBalance = $this->getBalance() - $amount;
        
        $stmt = $conn->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
        $stmt->execute([$newBalance, $this->id]);
        
        $this->data['wallet_balance'] = $newBalance;
        return $newBalance;
    }
    
    public function addTransaction($amount, $type, $description, $status = 'completed') {
        $conn = $this->db->getConnection();
        $balanceAfter = $this->getBalance();
        
        $stmt = $conn->prepare("
            INSERT INTO wallet_transactions (
                user_id, amount, transaction_type, description,
                balance_after, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $this->id,
            $amount,
            $type,
            $description,
            $balanceAfter,
            $status
        ]);
        
        return $conn->lastInsertId();
    }
    
    public function getReferralCode() {
        return $this->data['referral_code'] ?? null;
    }
    
    public function getReferralCount() {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM referrals WHERE referrer_id = ?");
        $stmt->execute([$this->id]);
        return $stmt->fetch()['count'] ?? 0;
    }
    
    public function isBlocked() {
        return $this->data['is_blocked'] ?? false;
    }
    
    public function isActive() {
        return $this->data['is_active'] ?? false;
    }
}