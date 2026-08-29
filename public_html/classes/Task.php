<?php
// backend/classes/Task.php

require_once __DIR__ . '/Database.php';

/**
 * Task Class
 * Handles task management operations
 */
class Task {
    /**
     * @var int Task ID
     */
    private $id;
    
    /**
     * @var array Task data
     */
    private $data;
    
    /**
     * @var Database Database instance
     */
    private $db;
    
    /**
     * Constructor
     * 
     * @param int|null $taskId Task ID (optional)
     */
    public function __construct($taskId = null) {
        $this->db = Database::getInstance();
        if ($taskId) {
            $this->id = $taskId;
            $this->load();
        }
    }
    
    /**
     * Load task data
     * 
     * @throws Exception If task not found
     */
    private function load() {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$this->id]);
        $this->data = $stmt->fetch();
        
        if (!$this->data) {
            throw new Exception('Task not found');
        }
    }
    
    /**
     * Get task data
     * 
     * @return array
     */
    public function getData() {
        return $this->data;
    }
    
    /**
     * Get task by ID
     * 
     * @param int $taskId Task ID
     * @return Task|null
     */
    public static function getById($taskId) {
        try {
            return new self($taskId);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get all active tasks
     * 
     * @param int $limit Limit results
     * @return array
     */
    public static function getActiveTasks($limit = 50) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT * FROM tasks 
            WHERE is_active = 1 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get user's completed tasks
     * 
     * @param int $userId User ID
     * @param int $limit Limit results
     * @return array
     */
    public static function getUserTasks($userId, $limit = 50) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT 
                th.*, t.title, t.task_type, t.icon
            FROM task_history th
            JOIN tasks t ON th.task_id = t.id
            WHERE th.user_id = ? AND th.is_claimed = 1
            ORDER BY th.completed_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get user's today task count
     * 
     * @param int $userId User ID
     * @param int $taskId Task ID (optional)
     * @return int
     */
    public static function getTodayCount($userId, $taskId = null) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $sql = "
            SELECT COUNT(*) as count 
            FROM task_history 
            WHERE user_id = ? 
            AND DATE(completed_at) = CURDATE()
            AND is_claimed = 1
        ";
        
        if ($taskId) {
            $sql .= " AND task_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$userId, $taskId]);
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->execute([$userId]);
        }
        
        return $stmt->fetch()['count'] ?? 0;
    }
    
    /**
     * Get user's total task earnings
     * 
     * @param int $userId User ID
     * @return float
     */
    public static function getTotalEarnings($userId) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(reward_amount), 0) as total
            FROM task_history 
            WHERE user_id = ? AND is_claimed = 1
        ");
        $stmt->execute([$userId]);
        return floatval($stmt->fetch()['total'] ?? 0);
    }
    
    /**
     * Check if user can claim a task
     * 
     * @param int $userId User ID
     * @param int $taskId Task ID
     * @return array Status and message
     */
    public static function canClaim($userId, $taskId) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Get task
        $stmt = $conn->prepare("
            SELECT * FROM tasks WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();
        
        if (!$task) {
            return ['can' => false, 'message' => 'Task not found or inactive'];
        }
        
        // Check daily limit
        $todayCount = self::getTodayCount($userId, $taskId);
        if ($task['daily_limit'] > 0 && $todayCount >= $task['daily_limit']) {
            return ['can' => false, 'message' => 'Daily task limit reached'];
        }
        
        // Check one-time task
        if ($task['is_one_time']) {
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM task_history 
                WHERE user_id = ? AND task_id = ? AND is_claimed = 1
            ");
            $stmt->execute([$userId, $taskId]);
            if ($stmt->fetch()['count'] > 0) {
                return ['can' => false, 'message' => 'Task already completed'];
            }
        }
        
        // Check pending tasks
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM task_history 
            WHERE user_id = ? AND task_id = ? 
            AND is_claimed = 0
            AND completed_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
        $stmt->execute([$userId, $taskId]);
        if ($stmt->fetch()['count'] > 0) {
            return ['can' => false, 'message' => 'You have a pending task'];
        }
        
        return ['can' => true, 'message' => 'Task available'];
    }
    
    /**
     * Claim a task
     * 
     * @param int $userId User ID
     * @param int $taskId Task ID
     * @return array Result with reward and balance
     * @throws Exception
     */
    public static function claim($userId, $taskId) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Check if can claim
        $check = self::canClaim($userId, $taskId);
        if (!$check['can']) {
            throw new Exception($check['message']);
        }
        
        // Get task
        $stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();
        
        // Start transaction
        $db->beginTransaction();
        
        try {
            // Get user current balance
            $stmt = $conn->prepare("
                SELECT wallet_balance, total_earnings, task_earnings 
                FROM users WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            // Calculate new balances
            $reward = floatval($task['reward_amount']);
            $newBalance = $user['wallet_balance'] + $reward;
            $newTotalEarnings = $user['total_earnings'] + $reward;
            $newTaskEarnings = $user['task_earnings'] + $reward;
            
            // Insert task history
            $stmt = $conn->prepare("
                INSERT INTO task_history (
                    user_id, task_id, reward_amount, is_claimed,
                    completed_at, ip_address, device_id
                ) VALUES (?, ?, ?, 1, NOW(), ?, ?)
            ");
            $stmt->execute([
                $userId,
                $taskId,
                $reward,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            $historyId = $conn->lastInsertId();
            
            // Update user
            $stmt = $conn->prepare("
                UPDATE users 
                SET 
                    wallet_balance = ?,
                    total_earnings = ?,
                    task_earnings = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $newBalance,
                $newTotalEarnings,
                $newTaskEarnings,
                $userId
            ]);
            
            // Add transaction
            $stmt = $conn->prepare("
                INSERT INTO wallet_transactions (
                    user_id, amount, transaction_type, description,
                    balance_after, status, reference_id, created_at
                ) VALUES (?, ?, 'task', ?, ?, 'completed', ?, NOW())
            ");
            $stmt->execute([
                $userId,
                $reward,
                "Completed task: {$task['title']}",
                $newBalance,
                $historyId
            ]);
            
            // Update leaderboard
            self::updateLeaderboard($userId, $reward);
            
            // Send notification
            $stmt = $conn->prepare("
                INSERT INTO notifications (user_id, title, message, type, created_at) 
                VALUES (?, 'Task Completed!', ?, 'in_app', NOW())
            ");
            $stmt->execute([
                $userId,
                "You earned ₹{$reward} for completing task: {$task['title']}"
            ]);
            
            $db->commit();
            
            return [
                'success' => true,
                'reward' => $reward,
                'balance_before' => $user['wallet_balance'],
                'balance_after' => $newBalance,
                'history_id' => $historyId
            ];
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }
    
    /**
     * Update leaderboard
     * 
     * @param int $userId User ID
     * @param float $amount Amount to add
     */
    private static function updateLeaderboard($userId, $amount) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // All-time
        $stmt = $conn->prepare("
            INSERT INTO leaderboard (user_id, period, total_earnings, updated_at)
            VALUES (?, 'all_time', ?, NOW())
            ON DUPLICATE KEY UPDATE 
                total_earnings = total_earnings + ?,
                updated_at = NOW()
        ");
        $stmt->execute([$userId, $amount, $amount]);
        
        // Weekly
        $stmt = $conn->prepare("
            INSERT INTO leaderboard (user_id, period, week_start, total_earnings, updated_at)
            VALUES (?, 'weekly', DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), ?, NOW())
            ON DUPLICATE KEY UPDATE 
                total_earnings = total_earnings + ?,
                updated_at = NOW()
        ");
        $stmt->execute([$userId, $amount, $amount]);
        
        // Monthly
        $stmt = $conn->prepare("
            INSERT INTO leaderboard (user_id, period, month_start, total_earnings, updated_at)
            VALUES (?, 'monthly', DATE_SUB(CURDATE(), INTERVAL DAY(CURDATE())-1 DAY), ?, NOW())
            ON DUPLICATE KEY UPDATE 
                total_earnings = total_earnings + ?,
                updated_at = NOW()
        ");
        $stmt->execute([$userId, $amount, $amount]);
    }
    
    /**
     * Create a new task
     * 
     * @param array $data Task data
     * @return int Task ID
     */
    public static function create($data) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            INSERT INTO tasks (
                title, description, icon, url, reward_amount,
                timer_seconds, daily_limit, is_one_time, is_repeatable,
                is_active, task_type, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $data['title'],
            $data['description'] ?? '',
            $data['icon'] ?? 'fa-link',
            $data['url'],
            $data['reward_amount'],
            $data['timer_seconds'] ?? 30,
            $data['daily_limit'] ?? 5,
            $data['is_one_time'] ?? 0,
            $data['is_repeatable'] ?? 0,
            $data['is_active'] ?? 1,
            $data['task_type'] ?? 'website'
        ]);
        
        return $conn->lastInsertId();
    }
    
    /**
     * Update task
     * 
     * @param int $taskId Task ID
     * @param array $data Task data
     * @return bool
     */
    public static function update($taskId, $data) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            UPDATE tasks 
            SET 
                title = ?,
                description = ?,
                icon = ?,
                url = ?,
                reward_amount = ?,
                timer_seconds = ?,
                daily_limit = ?,
                is_one_time = ?,
                is_repeatable = ?,
                is_active = ?,
                task_type = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['title'],
            $data['description'] ?? '',
            $data['icon'] ?? 'fa-link',
            $data['url'],
            $data['reward_amount'],
            $data['timer_seconds'] ?? 30,
            $data['daily_limit'] ?? 5,
            $data['is_one_time'] ?? 0,
            $data['is_repeatable'] ?? 0,
            $data['is_active'] ?? 1,
            $data['task_type'] ?? 'website',
            $taskId
        ]);
    }
    
    /**
     * Delete task
     * 
     * @param int $taskId Task ID
     * @return bool
     */
    public static function delete($taskId) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
        return $stmt->execute([$taskId]);
    }
}