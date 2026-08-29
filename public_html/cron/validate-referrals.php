<?php
// /cron/validate-referrals.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all referrals pending validation
        $referralStmt = $conn->prepare("
        SELECT r.id, r.referrer_id, r.referred_user_id, r.reward_amount,
               u.full_name, u.email, u.is_verified as email_verified,
               u.last_login  // ✅ USE last_login
        FROM referrals r
        JOIN users u ON r.referred_user_id = u.id
        WHERE r.validation_status IN ('pending', 'under_review')
    ");
    $referralStmt->execute();
    $referrals = $referralStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($referrals as $referral) {
        // Get task count for referred user
        $activityStmt = $conn->prepare("
            SELECT COUNT(*) as task_count 
            FROM task_history 
            WHERE user_id = ? AND is_claimed = 1
        ");
        $activityStmt->execute([$referral['referred_user_id']]);
        $activity = $activityStmt->fetch(PDO::FETCH_ASSOC);
        $task_count = intval($activity['task_count']);
        
        // Criteria for verified referral:
        // 1. Email verified
        // 2. At least 3 tasks completed
        // 3. Active (logged in within 30 days)
        $email_verified = $referral['email_verified'] == 1;
        $tasks_completed = $task_count >= 3;
        $is_active = $referral['last_login'] && 
             strtotime($referral['last_login']) > strtotime('-30 days');
        
        $validation_notes = [];
        $status = 'verified';
        
        if (!$email_verified) {
            $validation_notes[] = 'Email not verified';
            $status = 'pending';
        }
        
        if (!$tasks_completed) {
            $validation_notes[] = "Only {$task_count} tasks completed (need 3)";
            $status = 'pending';
        }
        
        if (!$is_active) {
            $validation_notes[] = 'No recent activity (30+ days)';
            if ($status == 'verified') {
                $status = 'pending';
            }
        }
        
        // Update referral record
        $updateStmt = $conn->prepare("
            UPDATE referrals 
            SET 
                referred_user_verified = ?,
                referred_user_active = ?,
                referred_user_tasks_completed = ?,
                is_genuine = ?,
                validation_status = ?,
                validation_notes = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([
            $email_verified ? 1 : 0,
            $is_active ? 1 : 0,
            $task_count,
            $status == 'verified' ? 1 : 0,
            $status,
            implode('; ', $validation_notes),
            $referral['id']
        ]);
        
        // Log the update
        error_log("Referral #{$referral['id']} - User: {$referral['full_name']} - Status: {$status} - Tasks: {$task_count}");
    }
    
    echo "✅ Referral validation completed\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>