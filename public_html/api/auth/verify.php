<?php
// /api/auth/verify.php - COMPLETE FIXED VERSION

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: text/html');
header('Access-Control-Allow-Origin: *');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../helpers/mailer.php';
require_once __DIR__ . '/../../helpers/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';

function showErrorPage($message, $title = 'Verification Failed') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $title; ?> - <?php echo APP_NAME; ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { background: #0a0e1a; color: #ffffff; font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
            .container { background: #111827; padding: 40px; border-radius: 16px; border: 1px solid #1e293b; text-align: center; max-width: 500px; width: 100%; }
            .icon { font-size: 64px; margin-bottom: 20px; }
            .icon.error { color: #ef4444; }
            h1 { color: #ef4444; margin-bottom: 12px; font-size: 28px; }
            p { color: #94a3b8; line-height: 1.7; font-size: 15px; margin-bottom: 20px; }
            .btn { display: inline-block; background: linear-gradient(135deg, #6C3CE1, #9B59B6); color: white; padding: 12px 30px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: transform 0.2s; }
            .btn:hover { transform: translateY(-2px); }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon error">❌</div>
            <h1><?php echo $title; ?></h1>
            <p><?php echo htmlspecialchars($message); ?></p>
            <a href="<?php echo APP_URL; ?>/login.html" class="btn">Go to Login</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if (empty($token)) {
    showErrorPage('Invalid or missing verification token. Please check your email link.');
}

try {
    $conn = Database::getInstance()->getConnection();
    
    // Find user with this token
    $stmt = $conn->prepare("
        SELECT id, full_name, email, referred_by, is_verified, verification_token 
        FROM users 
        WHERE verification_token = ? AND is_verified = 0
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        showErrorPage('Invalid or expired verification token. Please request a new verification email.');
    }
    
    $conn->beginTransaction();
    
    // Verify user
    $update = $conn->prepare("
        UPDATE users 
        SET is_verified = 1, email_verified = 1, verification_token = NULL, email_verified_at = NOW() 
        WHERE id = ?
    ");
    $update->execute([$user['id']]);
    
    // Get dynamic values
    $joining_bonus = floatval(getSetting('joining_bonus', 100));
    $referral_bonus = floatval(getSetting('referral_bonus', 100));
    
    // Credit joining bonus
    $checkBonus = $conn->prepare("SELECT id FROM wallet_transactions WHERE user_id = ? AND description LIKE '%joining%'");
    $checkBonus->execute([$user['id']]);
    
    if (!$checkBonus->fetch()) {
        $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ?, total_earnings = total_earnings + ? WHERE id = ?")
             ->execute([$joining_bonus, $joining_bonus, $user['id']]);
        
        $conn->prepare("INSERT INTO wallet_transactions (user_id, amount, transaction_type, description, balance_after, created_at) 
                        SELECT ?, ?, 'bonus', '🎉 Welcome joining bonus!', wallet_balance + ?, NOW() FROM users WHERE id = ?")
             ->execute([$user['id'], $joining_bonus, $joining_bonus, $user['id']]);
        
        sendNotification(
            $user['id'],
            '🎉 Welcome Bonus Credited',
            'You received a ' . formatCurrency($joining_bonus) . ' welcome bonus for verifying your email!'
        );
    }
    
    // Process referral bonus
    $referral_bonus_credited = false;
    if ($user['referred_by']) {
        $referrer_id = $user['referred_by'];
        
        $stmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE id = ?");
        $stmt->execute([$referrer_id]);
        $referrer = $stmt->fetch();
        
        if ($referrer) {
            $stmt = $conn->prepare("
                SELECT id, is_rewarded FROM referrals 
                WHERE referred_user_id = ? AND referrer_id = ?
            ");
            $stmt->execute([$user['id'], $referrer_id]);
            $referral = $stmt->fetch();
            
            if ($referral && !$referral['is_rewarded']) {
                // Update referral
                $stmt = $conn->prepare("
                    UPDATE referrals 
                    SET is_rewarded = 1, reward_date = NOW(), validation_status = 'verified', updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$referral['id']]);
                
                // Credit referrer
                $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ?, total_earnings = total_earnings + ?, referral_earnings = referral_earnings + ? WHERE id = ?")
                     ->execute([$referral_bonus, $referral_bonus, $referral_bonus, $referrer_id]);
                
                $conn->prepare("
                    INSERT INTO wallet_transactions (user_id, amount, transaction_type, description, balance_after, status, created_at) 
                    SELECT ?, ?, 'referral', ?, (SELECT wallet_balance FROM users WHERE id = ?), 'completed', NOW()
                ")->execute([$referrer_id, $referral_bonus, "Referral bonus from " . $user['full_name'] . " (verified)", $referrer_id]);
                
                $referral_bonus_credited = true;
                
                sendNotification(
                    $referrer_id,
                    '💰 Referral Bonus Credited!',
                    'You earned ' . formatCurrency($referral_bonus) . ' — ' . $user['full_name'] . ' verified their email using your referral code.'
                );
                
                try {
                    $mailer = new Mailer();
                    $mailer->sendReferralBonus(
                        $referrer['email'],
                        $referrer['full_name'],
                        $user['full_name'],
                        $referral_bonus
                    );
                } catch (Exception $e) {
                    error_log("Referral email error: " . $e->getMessage());
                }
            }
        }
    }
    
    $conn->commit();
    
    // Show success page
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Email Verified - <?php echo APP_NAME; ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                background: #0a0e1a; 
                color: #ffffff; 
                font-family: Arial, sans-serif; 
                display: flex; 
                justify-content: center; 
                align-items: center; 
                min-height: 100vh; 
                margin: 0; 
                padding: 20px; 
                background: radial-gradient(ellipse at 20% 50%, rgba(108,60,225,0.08), transparent 50%);
            }
            .container { 
                background: #111827; 
                padding: 40px; 
                border-radius: 16px; 
                border: 1px solid #1e293b; 
                text-align: center; 
                max-width: 500px; 
                width: 100%;
                box-shadow: 0 8px 40px rgba(0,0,0,0.4);
            }
            .icon { font-size: 64px; margin-bottom: 20px; }
            .icon.success { color: #10b981; }
            h1 { color: #10b981; margin-bottom: 12px; font-size: 28px; }
            p { color: #94a3b8; line-height: 1.7; font-size: 15px; margin-bottom: 6px; }
            .btn { 
                display: inline-block; 
                background: linear-gradient(135deg, #6C3CE1, #9B59B6); 
                color: white; 
                padding: 12px 30px; 
                border-radius: 8px; 
                text-decoration: none; 
                font-weight: 600; 
                margin-top: 20px;
                transition: transform 0.2s;
            }
            .btn:hover { transform: translateY(-2px); }
            .bonus-box { 
                background: rgba(16,185,129,0.08); 
                border: 1px solid rgba(16,185,129,0.15); 
                border-radius: 12px; 
                padding: 16px; 
                margin: 12px 0; 
            }
            .bonus-box .amount { font-size: 32px; font-weight: 800; color: #fbbf24; }
            .bonus-box .label { color: #94a3b8; font-size: 13px; }
            .divider { height: 1px; background: #1a2234; margin: 16px 0; }
            .name { color: #ffffff; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon success">✅</div>
            <h1>Email Verified! 🎉</h1>
            <p>Welcome, <span class="name"><?php echo htmlspecialchars($user['full_name']); ?></span>!</p>
            
            <div class="bonus-box">
                <div class="label">🎁 Welcome Bonus!</div>
                <div class="amount"><?php echo formatCurrency($joining_bonus); ?></div>
                <div class="label">Credited to your wallet</div>
            </div>
            
            <?php if ($referral_bonus_credited): ?>
            <div class="bonus-box" style="border-color: rgba(108,60,225,0.2);">
                <div class="label">🎉 Referral Bonus Credited!</div>
                <div class="amount"><?php echo formatCurrency($referral_bonus); ?></div>
                <div class="label">to <?php echo htmlspecialchars($referrer['full_name']); ?></div>
            </div>
            <?php endif; ?>
            
            <div class="divider"></div>
            
            <p style="font-size: 14px; color: #94a3b8;">
                You can now login and start earning rewards!
            </p>
            
            <a href="<?php echo APP_URL; ?>/login.html" class="btn">Login Now</a>
            
            <div style="margin-top: 16px;">
                <a href="<?php echo APP_URL; ?>/dashboard.html" style="color: #64748b; font-size: 13px; text-decoration: none;">Go to Dashboard →</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    
} catch (PDOException $e) {
    if (isset($conn)) $conn->rollBack();
    error_log("Verification error: " . $e->getMessage());
    showErrorPage('Database error occurred. Please contact support.');
} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    error_log("Verification error: " . $e->getMessage());
    showErrorPage('An error occurred. Please contact support.');
}
?>