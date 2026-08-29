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

// ============================================
// ✅ LOAD CONFIG
// ============================================

$config_file = __DIR__ . '/../config/config.php';

if (!file_exists($config_file)) {
    die(json_encode([
        'success' => false,
        'message' => 'Config file not found',
        'debug' => 'Missing: ' . $config_file
    ]));
}

require_once $config_file;

// ============================================
// ✅ MAILER CLASS
// ============================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mail;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        
        try {
            $this->mail->isSMTP();
            $this->mail->Host = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
            $this->mail->SMTPAuth = true;
            $this->mail->Username = defined('SMTP_USER') ? SMTP_USER : 'noreply.paisapay@gmail.com';
            $this->mail->Password = defined('SMTP_PASS') ? SMTP_PASS : 'avfitjicggmmolsc';
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port = defined('SMTP_PORT') ? SMTP_PORT : 587;
            $this->mail->setFrom(
                defined('SMTP_FROM') ? SMTP_FROM : 'noreply.paisapay@gmail.com',
                defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'PaisaPay'
            );
            $this->mail->isHTML(true);
            $this->mail->CharSet = 'UTF-8';
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
            
            $app_url = defined('APP_URL') ? APP_URL : 'https://paisa-pay.online';
            $verificationLink = $app_url . '/verify-email.php?token=' . $token;
            
            $this->mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <style>
                    /* Base styles */
                    body {
                        font-family: Arial, Helvetica, sans-serif;
                        margin: 0;
                        padding: 0;
                        background-color: #f4f4f4;
                        color: #1a1a2e;
                    }
                    .container {
                        max-width: 600px;
                        margin: 20px auto;
                        background-color: #ffffff;
                        border-radius: 16px;
                        padding: 40px;
                        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
                        border: 1px solid #e8e8e8;
                    }
                    .header {
                        text-align: center;
                        margin-bottom: 30px;
                    }
                    .logo {
                        font-size: 28px;
                        font-weight: 800;
                        color: #6C3CE1;
                    }
                    .logo span {
                        color: #9B59B6;
                    }
                    .content {
                        color: #333333;
                        line-height: 1.7;
                        font-size: 15px;
                    }
                    .content strong {
                        color: #1a1a2e;
                    }
                    .btn {
                        display: inline-block;
                        background: linear-gradient(135deg, #6C3CE1, #9B59B6);
                        color: #ffffff !important;
                        padding: 14px 32px;
                        border-radius: 8px;
                        text-decoration: none;
                        font-weight: 700;
                        font-size: 15px;
                        margin: 20px 0;
                        box-shadow: 0 4px 16px rgba(108, 60, 225, 0.25);
                    }
                    .btn:hover {
                        box-shadow: 0 6px 24px rgba(108, 60, 225, 0.35);
                    }
                    .link-text {
                        color: #555555;
                        font-size: 13px;
                        word-break: break-all;
                        background: #f8f8f8;
                        padding: 12px 16px;
                        border-radius: 8px;
                        border: 1px solid #e8e8e8;
                        margin: 10px 0;
                    }
                    .footer {
                        color: #888888;
                        font-size: 12px;
                        text-align: center;
                        margin-top: 30px;
                        padding-top: 20px;
                        border-top: 1px solid #e8e8e8;
                    }
                    .footer a {
                        color: #6C3CE1;
                        text-decoration: none;
                    }
                    .text-muted {
                        color: #888888;
                    }
                    .expiry {
                        color: #f59e0b;
                        font-weight: 600;
                    }
                    .divider {
                        height: 1px;
                        background: #e8e8e8;
                        margin: 20px 0;
                    }
                    /* Dark mode support */
                    @media (prefers-color-scheme: dark) {
                        body { background-color: #0a0e1a; }
                        .container { background-color: #1a1a2e; border-color: #2a2a4a; }
                        .content { color: #d0d0d0; }
                        .content strong { color: #ffffff; }
                        .link-text { background: #0d1524; border-color: #1e293b; color: #94a3b8; }
                        .footer { border-color: #2a2a4a; color: #64748b; }
                        .divider { background: #2a2a4a; }
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <!-- Header -->
                    <div class='header'>
                        <div class='logo'>💰 <span>PaisaPay</span></div>
                    </div>
                    
                    <!-- Content -->
                    <div class='content'>
                        <h2 style='color: #1a1a2e; margin-bottom: 16px;'>Welcome to PaisaPay! 🎉</h2>
                        
                        <p>Hello <strong>$name</strong>,</p>
                        
                        <p>Thank you for signing up! Please verify your email address to activate your account and start earning rewards.</p>
                        
                        <!-- Button -->
                        <div style='text-align: center;'>
                            <a href='$verificationLink' class='btn'>✅ Verify Email Address</a>
                        </div>
                        
                        <!-- Or copy link -->
                        <p style='color: #777777; font-size: 13px; text-align: center;'>Or copy and paste this link in your browser:</p>
                        <div class='link-text'>$verificationLink</div>
                        
                        <p style='color: #777777; font-size: 13px; margin-top: 16px;'>
                            ⏳ This link expires in <span class='expiry'>24 hours</span>.
                        </p>
                        
                        <p style='color: #777777; font-size: 13px;'>
                            If you didn't create an account, please ignore this email.
                        </p>
                    </div>
                    
                    <!-- Footer -->
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " PaisaPay. All rights reserved.</p>
                        <p><a href='" . APP_URL . "'>" . APP_URL . "</a></p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $this->mail->AltBody = "Welcome to PaisaPay!\n\nHello $name,\n\nPlease verify your email by visiting: $verificationLink\n\nThis link expires in 24 hours.\n\nIf you didn't create an account, please ignore this email.";
            
            return $this->mail->send();
            
        } catch (Exception $e) {
            error_log("Mail error: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Send referral bonus email to referrer
     */
    public function sendReferralBonus($email, $name, $referred_name, $amount) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($email, $name);
            $this->mail->Subject = '🎉 You Earned a Referral Bonus! - ' . APP_NAME;
            
            $app_url = defined('APP_URL') ? APP_URL : 'https://paisa-pay.online';
            
            $this->mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <style>
                    body {
                        font-family: Arial, Helvetica, sans-serif;
                        margin: 0;
                        padding: 0;
                        background-color: #f4f4f4;
                        color: #1a1a2e;
                    }
                    .container {
                        max-width: 600px;
                        margin: 20px auto;
                        background-color: #ffffff;
                        border-radius: 16px;
                        padding: 40px;
                        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
                        border: 1px solid #e8e8e8;
                    }
                    .header { text-align: center; margin-bottom: 30px; }
                    .logo { font-size: 28px; font-weight: 800; color: #6C3CE1; }
                    .logo span { color: #9B59B6; }
                    .content { color: #333333; line-height: 1.7; font-size: 15px; }
                    .content strong { color: #1a1a2e; }
                    .bonus-box {
                        background: linear-gradient(135deg, #f0ebff, #f8f0ff);
                        border: 1px solid #d4c4f0;
                        border-radius: 12px;
                        padding: 24px;
                        text-align: center;
                        margin: 20px 0;
                    }
                    .bonus-amount { font-size: 36px; font-weight: 900; color: #f59e0b; }
                    .bonus-label { color: #666666; font-size: 14px; }
                    .btn {
                        display: inline-block;
                        background: linear-gradient(135deg, #6C3CE1, #9B59B6);
                        color: #ffffff !important;
                        padding: 14px 32px;
                        border-radius: 8px;
                        text-decoration: none;
                        font-weight: 700;
                        font-size: 15px;
                        margin: 20px 0;
                        box-shadow: 0 4px 16px rgba(108, 60, 225, 0.25);
                    }
                    .footer {
                        color: #888888;
                        font-size: 12px;
                        text-align: center;
                        margin-top: 30px;
                        padding-top: 20px;
                        border-top: 1px solid #e8e8e8;
                    }
                    .footer a { color: #6C3CE1; text-decoration: none; }
                    .highlight { color: #6C3CE1; }
                    @media (prefers-color-scheme: dark) {
                        body { background-color: #0a0e1a; }
                        .container { background-color: #1a1a2e; border-color: #2a2a4a; }
                        .content { color: #d0d0d0; }
                        .content strong { color: #ffffff; }
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
                            <div class='bonus-amount'>₹$amount</div>
                            <div class='bonus-label'>Referral Bonus</div>
                        </div>
                        
                        <p>Your referral bonus has been added to your wallet. Keep sharing your referral link to earn more!</p>
                        
                        <div style='text-align: center;'>
                            <a href='$app_url/dashboard.html' class='btn'>💰 Check Your Wallet</a>
                        </div>
                        
                        <p style='color: #777777; font-size: 13px; margin-top: 16px;'>
                            💡 You earn ₹$amount for every friend who signs up using your referral link.
                        </p>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " PaisaPay. All rights reserved.</p>
                        <p><a href='" . APP_URL . "'>" . APP_URL . "</a></p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $this->mail->AltBody = "🎉 You Earned a Referral Bonus!\n\nHello $name,\n\nYour friend $referred_name just signed up using your referral link!\n\nYou earned: ₹$amount\n\nYour referral bonus has been added to your wallet.\n\nKeep sharing your referral link to earn more!\n\nCheck your wallet: $app_url/dashboard.html";
            
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
            
            $app_url = defined('APP_URL') ? APP_URL : 'https://paisa-pay.online';
            $resetLink = $app_url . '/reset-password.php?token=' . $token;
            
            $this->mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <style>
                    body {
                        font-family: Arial, Helvetica, sans-serif;
                        margin: 0;
                        padding: 0;
                        background-color: #f4f4f4;
                        color: #1a1a2e;
                    }
                    .container {
                        max-width: 600px;
                        margin: 20px auto;
                        background-color: #ffffff;
                        border-radius: 16px;
                        padding: 40px;
                        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
                        border: 1px solid #e8e8e8;
                    }
                    .header { text-align: center; margin-bottom: 30px; }
                    .logo { font-size: 28px; font-weight: 800; color: #6C3CE1; }
                    .logo span { color: #9B59B6; }
                    .content { color: #333333; line-height: 1.7; font-size: 15px; }
                    .content strong { color: #1a1a2e; }
                    .btn {
                        display: inline-block;
                        background: linear-gradient(135deg, #6C3CE1, #9B59B6);
                        color: #ffffff !important;
                        padding: 14px 32px;
                        border-radius: 8px;
                        text-decoration: none;
                        font-weight: 700;
                        font-size: 15px;
                        margin: 20px 0;
                        box-shadow: 0 4px 16px rgba(108, 60, 225, 0.25);
                    }
                    .footer {
                        color: #888888;
                        font-size: 12px;
                        text-align: center;
                        margin-top: 30px;
                        padding-top: 20px;
                        border-top: 1px solid #e8e8e8;
                    }
                    .footer a { color: #6C3CE1; text-decoration: none; }
                    .warning { color: #ef4444; }
                    @media (prefers-color-scheme: dark) {
                        body { background-color: #0a0e1a; }
                        .container { background-color: #1a1a2e; border-color: #2a2a4a; }
                        .content { color: #d0d0d0; }
                        .content strong { color: #ffffff; }
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
                            <a href='$resetLink' class='btn'>🔑 Reset Password</a>
                        </div>
                        <p style='color: #777777; font-size: 13px;'>This link expires in <strong>1 hour</strong>.</p>
                        <p style='color: #ef4444; font-size: 13px;'>⚠️ If you didn't request this, please ignore this email.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " PaisaPay. All rights reserved.</p>
                        <p><a href='" . APP_URL . "'>" . APP_URL . "</a></p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
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
            
            $app_url = defined('APP_URL') ? APP_URL : 'https://paisa-pay.online';
            
            $this->mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <style>
                    body {
                        font-family: Arial, Helvetica, sans-serif;
                        margin: 0;
                        padding: 0;
                        background-color: #f4f4f4;
                        color: #1a1a2e;
                    }
                    .container {
                        max-width: 600px;
                        margin: 20px auto;
                        background-color: #ffffff;
                        border-radius: 16px;
                        padding: 40px;
                        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
                        border: 1px solid #e8e8e8;
                    }
                    .header { text-align: center; margin-bottom: 30px; }
                    .logo { font-size: 28px; font-weight: 800; color: #6C3CE1; }
                    .logo span { color: #9B59B6; }
                    .content { color: #333333; line-height: 1.7; font-size: 15px; }
                    .content strong { color: #1a1a2e; }
                    .btn {
                        display: inline-block;
                        background: linear-gradient(135deg, #6C3CE1, #9B59B6);
                        color: #ffffff !important;
                        padding: 14px 32px;
                        border-radius: 8px;
                        text-decoration: none;
                        font-weight: 700;
                        font-size: 15px;
                        margin: 20px 0;
                        box-shadow: 0 4px 16px rgba(108, 60, 225, 0.25);
                    }
                    .features {
                        background: #f8f8f8;
                        border-radius: 8px;
                        padding: 16px 20px;
                        border: 1px solid #e8e8e8;
                        margin: 16px 0;
                    }
                    .features ul { color: #555555; padding-left: 20px; margin: 8px 0; }
                    .features li { padding: 4px 0; }
                    .footer {
                        color: #888888;
                        font-size: 12px;
                        text-align: center;
                        margin-top: 30px;
                        padding-top: 20px;
                        border-top: 1px solid #e8e8e8;
                    }
                    .footer a { color: #6C3CE1; text-decoration: none; }
                    @media (prefers-color-scheme: dark) {
                        body { background-color: #0a0e1a; }
                        .container { background-color: #1a1a2e; border-color: #2a2a4a; }
                        .content { color: #d0d0d0; }
                        .content strong { color: #ffffff; }
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
                        <p>&copy; " . date('Y') . " PaisaPay. All rights reserved.</p>
                        <p><a href='" . APP_URL . "'>" . APP_URL . "</a></p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $this->mail->AltBody = "Welcome to PaisaPay!\n\nHello $name,\n\nYour account has been successfully created and verified!\n\nStart earning now at: $app_url";
            
            return $this->mail->send();
            
        } catch (Exception $e) {
            error_log("Mail error: " . $this->mail->ErrorInfo);
            return false;
        }
    }
}
?>