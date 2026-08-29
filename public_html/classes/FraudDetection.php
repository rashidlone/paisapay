<?php
// backend/classes/FraudDetection.php

require_once __DIR__ . '/Database.php';

/**
 * Fraud Detection Class
 * Detects and prevents fraudulent activities
 */
class FraudDetection {
    /**
     * @var Database Database instance
     */
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Check for multiple accounts from same device
     * 
     * @param string $deviceId Device fingerprint
     * @param int $userId User ID to exclude
     * @return array
     */
    public function checkDeviceAccounts($deviceId, $userId = null) {
        $conn = $this->db->getConnection();
        
        $sql = "
            SELECT 
                u.id, u.full_name, u.phone_number, u.created_at,
                ud.device_id, ud.created_at as device_created_at
            FROM user_devices ud
            JOIN users u ON ud.user_id = u.id
            WHERE ud.device_id = ?
        ";
        
        $params = [$deviceId];
        
        if ($userId) {
            $sql .= " AND u.id != ?";
            $params[] = $userId;
        }
        
        $sql .= " ORDER BY ud.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $accounts = $stmt->fetchAll();
        
        return [
            'count' => count($accounts),
            'accounts' => $accounts,
            'suspicious' => count($accounts) > 3
        ];
    }
    
    /**
     * Check for duplicate phone numbers
     * 
     * @param string $phone Phone number
     * @param int $userId User ID to exclude
     * @return array
     */
    public function checkDuplicatePhone($phone, $userId = null) {
        $conn = $this->db->getConnection();
        
        $sql = "SELECT id, full_name, phone_number, created_at FROM users WHERE phone_number = ?";
        $params = [$phone];
        
        if ($userId) {
            $sql .= " AND id != ?";
            $params[] = $userId;
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        
        return [
            'count' => count($users),
            'users' => $users,
            'suspicious' => count($users) > 0
        ];
    }
    
    /**
     * Check for rapid registrations from same IP
     * 
     * @param string $ip IP address
     * @param int $timeWindow Time window in seconds (default: 3600)
     * @return array
     */
    public function checkRapidRegistrations($ip, $timeWindow = 3600) {
        $conn = $this->db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as count,
                MIN(created_at) as first_registration,
                MAX(created_at) as last_registration
            FROM users 
            WHERE ip_address = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$ip, $timeWindow]);
        $result = $stmt->fetch();
        
        $count = intval($result['count'] ?? 0);
        
        return [
            'count' => $count,
            'first_registration' => $result['first_registration'],
            'last_registration' => $result['last_registration'],
            'suspicious' => $count > 5
        ];
    }
    
    /**
     * Check for self-referral
     * 
     * @param int $userId User ID
     * @param string $referralCode Referral code used
     * @return bool
     */
    public function checkSelfReferral($userId, $referralCode) {
        $conn = $this->db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT id FROM users 
            WHERE id = ? AND referral_code = ?
        ");
        $stmt->execute([$userId, $referralCode]);
        
        return $stmt->fetch() !== false;
    }
    
    /**
     * Check for referral chain loops
     * 
     * @param int $userId User ID
     * @param int $referrerId Referrer ID
     * @return bool
     */
    public function checkReferralLoop($userId, $referrerId) {
        // Check if user is already in referrer's chain
        $currentReferrer = $referrerId;
        $visited = [];
        $maxDepth = 10;
        $depth = 0;
        
        while ($currentReferrer && $depth < $maxDepth) {
            if (in_array($currentReferrer, $visited)) {
                return true; // Loop detected
            }
            
            if ($currentReferrer == $userId) {
                return true; // Self-referral loop
            }
            
            $visited[] = $currentReferrer;
            
            // Get referrer's referrer
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT referred_by FROM users WHERE id = ?");
            $stmt->execute([$currentReferrer]);
            $user = $stmt->fetch();
            
            $currentReferrer = $user ? $user['referred_by'] : null;
            $depth++;
        }
        
        return false;
    }
    
    /**
     * Check for suspicious withdrawal patterns
     * 
     * @param int $userId User ID
     * @param float $amount Withdrawal amount
     * @return array
     */
    public function checkSuspiciousWithdrawal($userId, $amount) {
        $conn = $this->db->getConnection();
        
        // Check recent withdrawals
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as count,
                COALESCE(SUM(amount), 0) as total,
                MAX(created_at) as last_withdrawal
            FROM withdrawals 
            WHERE user_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND status != 'rejected'
        ");
        $stmt->execute([$userId]);
        $recent = $stmt->fetch();
        
        // Check withdrawal frequency
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as count,
                COALESCE(SUM(amount), 0) as total
            FROM withdrawals 
            WHERE user_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND status = 'paid'
        ");
        $stmt->execute([$userId]);
        $weekly = $stmt->fetch();
        
        $isSuspicious = false;
        $reasons = [];
        
        // Check for multiple withdrawals in short time
        if (intval($recent['count'] ?? 0) > 3) {
            $isSuspicious = true;
            $reasons[] = 'Multiple withdrawals in last 24 hours';
        }
        
        // Check for large amount
        if ($amount > 50000) {
            $isSuspicious = true;
            $reasons[] = 'Large withdrawal amount';
        }
        
        // Check for high frequency
        if (intval($weekly['count'] ?? 0) > 10) {
            $isSuspicious = true;
            $reasons[] = 'High withdrawal frequency';
        }
        
        return [
            'suspicious' => $isSuspicious,
            'reasons' => $reasons,
            'recent_withdrawals' => $recent,
            'weekly_withdrawals' => $weekly
        ];
    }
    
    /**
     * Check for task farming
     * 
     * @param int $userId User ID
     * @return array
     */
    public function checkTaskFarming($userId) {
        $conn = $this->db->getConnection();
        
        // Check task completion speed
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as count,
                AVG(TIMESTAMPDIFF(SECOND, completed_at, claimed_at)) as avg_time
            FROM task_history 
            WHERE user_id = ? 
            AND is_claimed = 1
            AND DATE(completed_at) = CURDATE()
        ");
        $stmt->execute([$userId]);
        $today = $stmt->fetch();
        
        // Check if tasks are being completed too quickly
        if (intval($today['count'] ?? 0) > 20) {
            return [
                'suspicious' => true,
                'reason' => 'Too many tasks completed today',
                'count' => intval($today['count'])
            ];
        }
        
        if (floatval($today['avg_time'] ?? 0) < 10 && intval($today['count'] ?? 0) > 5) {
            return [
                'suspicious' => true,
                'reason' => 'Tasks being completed too quickly',
                'avg_time' => floatval($today['avg_time'])
            ];
        }
        
        return [
            'suspicious' => false,
            'reason' => null
        ];
    }
    
    /**
     * Run full fraud check on user
     * 
     * @param int $userId User ID
     * @param array $context Additional context (device_id, ip, etc.)
     * @return array
     */
    public function runFullCheck($userId, $context = []) {
        $conn = $this->db->getConnection();
        
        // Get user data
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['fraud_detected' => true, 'reason' => 'User not found'];
        }
        
        $alerts = [];
        $score = 0;
        
        // 1. Check device accounts
        if (!empty($context['device_id'])) {
            $deviceCheck = $this->checkDeviceAccounts($context['device_id'], $userId);
            if ($deviceCheck['suspicious']) {
                $alerts[] = 'Multiple accounts from same device';
                $score += 30;
            }
        }
        
        // 2. Check duplicate phone
        $phoneCheck = $this->checkDuplicatePhone($user['phone_number'], $userId);
        if ($phoneCheck['suspicious']) {
            $alerts[] = 'Duplicate phone number detected';
            $score += 50;
        }
        
        // 3. Check rapid registrations
        if (!empty($context['ip'])) {
            $ipCheck = $this->checkRapidRegistrations($context['ip']);
            if ($ipCheck['suspicious']) {
                $alerts[] = 'Rapid registrations from same IP';
                $score += 20;
            }
        }
        
        // 4. Check self referral
        if (!empty($context['referral_code'])) {
            if ($this->checkSelfReferral($userId, $context['referral_code'])) {
                $alerts[] = 'Self-referral detected';
                $score += 100;
            }
        }
        
        // 5. Check task farming
        $taskCheck = $this->checkTaskFarming($userId);
        if ($taskCheck['suspicious']) {
            $alerts[] = $taskCheck['reason'];
            $score += 40;
        }
        
        // 6. Check suspicious withdrawal patterns
        if (!empty($context['withdrawal_amount'])) {
            $withdrawalCheck = $this->checkSuspiciousWithdrawal($userId, $context['withdrawal_amount']);
            if ($withdrawalCheck['suspicious']) {
                $alerts = array_merge($alerts, $withdrawalCheck['reasons']);
                $score += 30;
            }
        }
        
        // Determine if fraud is detected
        $fraudDetected = $score >= 50;
        
        // Log fraud report if detected
        if ($fraudDetected) {
            $this->createFraudReport($userId, $alerts, $score);
        }
        
        return [
            'fraud_detected' => $fraudDetected,
            'score' => $score,
            'alerts' => $alerts,
            'is_suspicious' => $score >= 30 && $score < 50
        ];
    }
    
    /**
     * Create fraud report
     * 
     * @param int $userId User ID
     * @param array $alerts Alerts/messages
     * @param int $score Fraud score
     * @return int Report ID
     */
    private function createFraudReport($userId, $alerts, $score) {
        $conn = $this->db->getConnection();
        
        $description = "Fraud detected with score: {$score}\n";
        $description .= "Alerts:\n- " . implode("\n- ", $alerts);
        
        $stmt = $conn->prepare("
            INSERT INTO fraud_reports (
                user_id, fraud_type, description, status, created_at
            ) VALUES (?, 'auto_detected', ?, 'pending', NOW())
        ");
        $stmt->execute([$userId, $description]);
        
        return $conn->lastInsertId();
    }
    
    /**
     * Get fraud reports
     * 
     * @param string $status Status filter
     * @param int $limit Limit results
     * @return array
     */
    public function getFraudReports($status = 'pending', $limit = 50) {
        $conn = $this->db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT 
                fr.*,
                u.full_name,
                u.phone_number,
                u.wallet_balance,
                u.is_blocked
            FROM fraud_reports fr
            JOIN users u ON fr.user_id = u.id
            WHERE fr.status = ?
            ORDER BY fr.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$status, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Update fraud report status
     * 
     * @param int $reportId Report ID
     * @param string $status New status
     * @param int $resolvedBy Admin ID
     * @return bool
     */
    public function updateFraudReport($reportId, $status, $resolvedBy) {
        $conn = $this->db->getConnection();
        
        $validStatuses = ['investigating', 'confirmed', 'dismissed'];
        if (!in_array($status, $validStatuses)) {
            throw new Exception('Invalid status');
        }
        
        $stmt = $conn->prepare("
            UPDATE fraud_reports 
            SET status = ?, resolved_by = ?, resolved_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $resolvedBy, $reportId]);
        
        // If confirmed, block user
        if ($status === 'confirmed') {
            $stmt = $conn->prepare("
                SELECT user_id FROM fraud_reports WHERE id = ?
            ");
            $stmt->execute([$reportId]);
            $report = $stmt->fetch();
            
            if ($report) {
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET is_blocked = 1, is_fraud_flag = 1, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$report['user_id']]);
            }
        }
        
        return true;
    }
}