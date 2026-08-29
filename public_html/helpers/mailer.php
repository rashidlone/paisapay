<?php
// /helpers/mailer.php

// ============================================
// ✅ LOAD COMPOSER AUTOLOAD
// ============================================

$composer_autoload = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($composer_autoload)) {
    die(json_encode([
        'success' => false,
        'message' => 'Composer autoload not found. Run: composer require phpmailer/phpmailer',
        'debug' => 'Missing: ' . $composer_autoload
    ]));
}

require_once $composer_autoload;
require_once __DIR__ . '/../config/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mail;
    private $debug;
    
    public function __construct() {
        $this->debug = defined('DEBUG_MODE') ? DEBUG_MODE : false;
        $this->mail = new PHPMailer(true);
        
        try {
            $this->mail->isSMTP();
            $this->mail->Host = SMTP_HOST;
            $this->mail->SMTPAuth = true;
            $this->mail->Username = SMTP_USER;
            $this->mail->Password = SMTP_PASS;
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port = SMTP_PORT;
            $this->mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $this->mail->isHTML(true);
            $this->mail->CharSet = 'UTF-8';
            
            if ($this->debug) {
                $this->mail->SMTPDebug = 2;
            }
        } catch (Exception $e) {
            error_log('Mailer init error: ' . $e->getMessage());
        }
    }
    
    /**
     * Send email verification
     */
    public function sendVerification($email, $name, $token) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($email, $name);
            $this->mail->Subject = 'Verify Your Email - ' . APP_NAME;
            
            $app_url = APP_URL;
            $verificationLink = $app_url . '/verify-email.php?token=' . $token;
            
            $this->mail->Body = $this->getVerificationEmail($name, $verificationLink);
            $this->mail->AltBody = "Welcome to " . APP_NAME . "!\n\nHello $name,\n\nPlease verify your email by visiting: $verificationLink\n\nThis link expires in 24 hours.\n\nIf you didn't create an account, please ignore this email.";
            
            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Mail error: " . $this->mail->ErrorInfo);
            if ($this->debug) {
                error_log("Mail debug: " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Send referral bonus email
     */
    public function sendReferralBonus($email, $name, $referred_name, $amount) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($email, $name);
            $this->mail->Subject = '🎉 You Earned a Referral Bonus! - ' . APP_NAME;
            
            $app_url = APP_URL;
            $symbol = getCurrencySymbol();
            
            $this->mail->Body = $this->getReferralEmail($name, $referred_name, $amount, $symbol, $app_url);
            $this->mail->AltBody = "🎉 You Earned a Referral Bonus!\n\nHello $name,\n\nYour friend $referred_name just signed up using your referral link!\n\nYou earned: $symbol$amount\n\nYour referral bonus has been added to your wallet.\n\nKeep sharing your referral link to earn more!\n\nCheck your wallet: $app_url/dashboard.html";
            
            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Referral email error: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordReset($email, $name, $token) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($email, $name);
            $this->mail->Subject = 'Reset Your Password - ' . APP_NAME;
            
            $app_url = APP_URL;
            $resetLink = $app_url . '/reset-password.php?token=' . $token;
            
            $this->mail->Body = $this->getPasswordResetEmail($name, $resetLink);
            $this->mail->AltBody = "Password Reset Request\n\nHello $name,\n\nReset your password by visiting: $resetLink\n\nThis link expires in 1 hour.\n\nIf you didn't request this, please ignore this email.";
            
            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Mail error: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Send welcome email
     */
    public function sendWelcome($email, $name) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($email, $name);
            $this->mail->Subject = 'Welcome to ' . APP_NAME . '!';
            
            $app_url = APP_URL;
            
            $this->mail->Body = $this->getWelcomeEmail($name, $app_url);
            $this->mail->AltBody = "Welcome to " . APP_NAME . "!\n\nHello $name,\n\nYour account has been successfully created and verified!\n\nStart earning now at: $app_url";
            
            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Mail error: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Send withdrawal notification
     */
    public function sendWithdrawalNotification($email, $name, $amount, $withdrawal_id, $status = 'submitted') {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($email, $name);
            $this->mail->Subject = '💰 Withdrawal Update - ' . APP_NAME;
            
            $app_url = APP_URL;
            $symbol = getCurrencySymbol();
            
            $this->mail->Body = $this->getWithdrawalEmail($name, $amount, $withdrawal_id, $status, $symbol, $app_url);
            $this->mail->AltBody = "Withdrawal Update\n\nHello $name,\n\nYour withdrawal request #$withdrawal_id for $symbol$amount has been $status.\n\nCheck your account for more details.";
            
            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Mail error: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    // ============================================
    // EMAIL TEMPLATES
    // ============================================
    
    private function getVerificationEmail($name, $link) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; color: #1a1a2e; }
                .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 16px; padding: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid #e8e8e8; }
                .header { text-align: center; margin-bottom: 30px; }
                .logo { font-size: 28px; font-weight: 800; color: #6C3CE1; }
                .logo span { color: #9B59B6; }
                .content { color: #333333; line-height: 1.7; font-size: 15px; }
                .btn { display: inline-block; background: linear-gradient(135deg, #6C3CE1, #9B59B6); color: #ffffff !important; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 15px; margin: 20px 0; box-shadow: 0 4px 16px rgba(108,60,225,0.25); }
                .btn:hover { box-shadow: 0 6px 24px rgba(108,60,225,0.35); }
                .link-text { color: #555555; font-size: 13px; word-break: break-all; background: #f8f8f8; padding: 12px 16px; border-radius: 8px; border: 1px solid #e8e8e8; margin: 10px 0; }
                .footer { color: #888888; font-size: 12px; text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e8e8e8; }
                .expiry { color: #f59e0b; font-weight: 600; }
                @media (prefers-color-scheme: dark) {
                    body { background-color: #0a0e1a; }
                    .container { background-color: #1a1a2e; border-color: #2a2a4a; }
                    .content { color: #d0d0d0; }
                    .content strong { color: #ffffff; }
                    .link-text { background: #0d1524; border-color: #1e293b; color: #94a3b8; }
                    .footer { border-color: #2a2a4a; color: #64748b; }
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>💰 <span>PaisaPay</span></div>
                </div>
                <div class='content'>
                    <h2 style='color: #1a1a2e;'>Welcome to PaisaPay! 🎉</h2>
                    <p>Hello <strong>$name</strong>,</p>
                    <p>Thank you for signing up! Please verify your email address to activate your account and start earning rewards.</p>
                    <div style='text-align: center;'>
                        <a href='$link' class='btn'>✅ Verify Email Address</a>
                    </div>
                    <p style='color: #777777; font-size: 13px; text-align: center;'>Or copy and paste this link in your browser:</p>
                    <div class='link-text'>$link</div>
                    <p style='color: #777777; font-size: 13px; margin-top: 16px;'>
                        ⏳ This link expires in <span class='expiry'>24 hours</span>.
                    </p>
                    <p style='color: #777777; font-size: 13px;'>
                        If you didn't create an account, please ignore this email.
                    </p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " " . APP_NAME . ". All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getReferralEmail($name, $referred_name, $amount, $symbol, $app_url) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; color: #1a1a2e; }
                .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 16px; padding: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid #e8e8e8; }
                .header { text-align: center; margin-bottom: 30px; }
                .logo { font-size: 28px; font-weight: 800; color: #6C3CE1; }
                .logo span { color: #9B59B6; }
                .content { color: #333333; line-height: 1.7; font-size: 15px; }
                .bonus-box { background: linear-gradient(135deg, #f0ebff, #f8f0ff); border: 1px solid #d4c4f0; border-radius: 12px; padding: 24px; text-align: center; margin: 20px 0; }
                .bonus-amount { font-size: 36px; font-weight: 900; color: #f59e0b; }
                .bonus-label { color: #666666; font-size: 14px; }
                .btn { display: inline-block; background: linear-gradient(135deg, #6C3CE1, #9B59B6); color: #ffffff !important; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 15px; margin: 20px 0; box-shadow: 0 4px 16px rgba(108,60,225,0.25); }
                .footer { color: #888888; font-size: 12px; text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e8e8e8; }
                @media (prefers-color-scheme: dark) {
                    body { background-color: #0a0e1a; }
                    .container { background-color: #1a1a2e; border-color: #2a2a4a; }
                    .content { color: #d0d0d0; }
                    .bonus-box { background: linear-gradient(135deg, rgba(108,60,225,0.15), rgba(155,89,182,0.08)); border-color: rgba(108,60,225,0.2); }
                    .bonus-label { color: #94a3b8; }
                    .footer { border-color: #2a2a4a; color: #64748b; }
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>💰 <span>PaisaPay</span></div>
                </div>
                <div class='content'>
                    <h2 style='color: #1a1a2e;'>🎉 You Earned a Referral Bonus!</h2>
                    <p>Hello <strong>$name</strong>,</p>
                    <p>Great news! Your friend <strong>$referred_name</strong> just signed up using your referral link!</p>
                    <div class='bonus-box'>
                        <div class='bonus-label'>💰 You Earned</div>
                        <div class='bonus-amount'>$symbol$amount</div>
                        <div class='bonus-label'>Referral Bonus</div>
                    </div>
                    <p>Your referral bonus has been added to your wallet. Keep sharing your referral link to earn more!</p>
                    <div style='text-align: center;'>
                        <a href='$app_url/dashboard.html' class='btn'>💰 Check Your Wallet</a>
                    </div>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " " . APP_NAME . ". All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getPasswordResetEmail($name, $link) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; color: #1a1a2e; }
                .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 16px; padding: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid #e8e8e8; }
                .header { text-align: center; margin-bottom: 30px; }
                .logo { font-size: 28px; font-weight: 800; color: #6C3CE1; }
                .logo span { color: #9B59B6; }
                .content { color: #333333; line-height: 1.7; font-size: 15px; }
                .btn { display: inline-block; background: linear-gradient(135deg, #6C3CE1, #9B59B6); color: #ffffff !important; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 15px; margin: 20px 0; box-shadow: 0 4px 16px rgba(108,60,225,0.25); }
                .footer { color: #888888; font-size: 12px; text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e8e8e8; }
                @media (prefers-color-scheme: dark) {
                    body { background-color: #0a0e1a; }
                    .container { background-color: #1a1a2e; border-color: #2a2a4a; }
                    .content { color: #d0d0d0; }
                    .footer { border-color: #2a2a4a; color: #64748b; }
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>💰 <span>PaisaPay</span></div>
                </div>
                <div class='content'>
                    <h2 style='color: #1a1a2e;'>Password Reset Request</h2>
                    <p>Hello <strong>$name</strong>,</p>
                    <p>We received a request to reset your password. Click the button below to set a new password.</p>
                    <div style='text-align: center;'>
                        <a href='$link' class='btn'>🔑 Reset Password</a>
                    </div>
                    <p style='color: #777777; font-size: 13px;'>This link expires in <strong>1 hour</strong>.</p>
                    <p style='color: #ef4444; font-size: 13px;'>⚠️ If you didn't request this, please ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " " . APP_NAME . ". All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getWelcomeEmail($name, $app_url) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; color: #1a1a2e; }
                .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 16px; padding: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid #e8e8e8; }
                .header { text-align: center; margin-bottom: 30px; }
                .logo { font-size: 28px; font-weight: 800; color: #6C3CE1; }
                .logo span { color: #9B59B6; }
                .content { color: #333333; line-height: 1.7; font-size: 15px; }
                .features { background: #f8f8f8; border-radius: 8px; padding: 16px 20px; border: 1px solid #e8e8e8; margin: 16px 0; }
                .features ul { color: #555555; padding-left: 20px; margin: 8px 0; }
                .features li { padding: 4px 0; }
                .btn { display: inline-block; background: linear-gradient(135deg, #6C3CE1, #9B59B6); color: #ffffff !important; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 15px; margin: 20px 0; box-shadow: 0 4px 16px rgba(108,60,225,0.25); }
                .footer { color: #888888; font-size: 12px; text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e8e8e8; }
                @media (prefers-color-scheme: dark) {
                    body { background-color: #0a0e1a; }
                    .container { background-color: #1a1a2e; border-color: #2a2a4a; }
                    .content { color: #d0d0d0; }
                    .features { background: #0d1524; border-color: #1e293b; }
                    .features ul { color: #94a3b8; }
                    .footer { border-color: #2a2a4a; color: #64748b; }
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>💰 <span>PaisaPay</span></div>
                </div>
                <div class='content'>
                    <h2 style='color: #1a1a2e;'>Welcome to PaisaPay! 🎉</h2>
                    <p>Hello <strong>$name</strong>,</p>
                    <p>Your account has been successfully created and verified!</p>
                    <div class='features'>
                        <p style='font-weight: 600; color: #1a1a2e; margin-bottom: 8px;'>You can now:</p>
                        <ul>
                            <li>💰 Earn rewards by completing tasks</li>
                            <li>👥 Invite friends and earn referral bonuses</li>
                            <li>💳 Withdraw your earnings directly to UPI or bank</li>
                        </ul>
                    </div>
                    <div style='text-align: center;'>
                        <a href='$app_url/dashboard.html' class='btn'>🚀 Start Earning Now</a>
                    </div>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " " . APP_NAME . ". All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getWithdrawalEmail($name, $amount, $withdrawal_id, $status, $symbol, $app_url) {
        $statusText = ucfirst(str_replace('_', ' ', $status));
        $statusColor = '#60a5fa';
        if ($status === 'approved' || $status === 'paid') $statusColor = '#10b981';
        elseif ($status === 'rejected') $statusColor = '#ef4444';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; color: #1a1a2e; }
                .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 16px; padding: 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid #e8e8e8; }
                .header { text-align: center; margin-bottom: 30px; }
                .logo { font-size: 28px; font-weight: 800; color: #6C3CE1; }
                .logo span { color: #9B59B6; }
                .content { color: #333333; line-height: 1.7; font-size: 15px; }
                .status-box { background: rgba($statusColor, 0.1); border: 1px solid rgba($statusColor, 0.2); border-radius: 12px; padding: 24px; text-align: center; margin: 20px 0; }
                .status-text { font-size: 24px; font-weight: 700; color: $statusColor; }
                .amount-text { font-size: 32px; font-weight: 800; color: #f59e0b; }
                .btn { display: inline-block; background: linear-gradient(135deg, #6C3CE1, #9B59B6); color: #ffffff !important; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 15px; margin: 20px 0; box-shadow: 0 4px 16px rgba(108,60,225,0.25); }
                .footer { color: #888888; font-size: 12px; text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e8e8e8; }
                @media (prefers-color-scheme: dark) {
                    body { background-color: #0a0e1a; }
                    .container { background-color: #1a1a2e; border-color: #2a2a4a; }
                    .content { color: #d0d0d0; }
                    .footer { border-color: #2a2a4a; color: #64748b; }
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>💰 <span>PaisaPay</span></div>
                </div>
                <div class='content'>
                    <h2 style='color: #1a1a2e;'>Withdrawal Update</h2>
                    <p>Hello <strong>$name</strong>,</p>
                    <div class='status-box'>
                        <div style='margin-bottom: 8px;'>Withdrawal Status</div>
                        <div class='status-text'>$statusText</div>
                        <div style='margin-top: 12px;'>
                            <div class='amount-text'>$symbol$amount</div>
                            <div style='color: #888888; font-size: 14px;'>Request #$withdrawal_id</div>
                        </div>
                    </div>
                    <div style='text-align: center;'>
                        <a href='$app_url/withdrawal-status.html' class='btn'>📊 View Details</a>
                    </div>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " " . APP_NAME . ". All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
?>