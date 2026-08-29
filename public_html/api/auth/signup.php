<?php
// /api/auth/signup.php - COMPLETE FIXED VERSION

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../helpers/mailer.php';
require_once __DIR__ . '/../../helpers/functions.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
$full_name = trim($input['full_name'] ?? '');
$referral_code = trim($input['referral_code'] ?? '');

if (empty($email) || empty($password) || empty($full_name)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

try {
    $conn = Database::getInstance()->getConnection();
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        exit;
    }
    
    // Get dynamic values from settings
    $referral_bonus = floatval(getSetting('referral_bonus', 100));
    $joining_bonus = floatval(getSetting('joining_bonus', 100));
    
    // Check referral code
    $referrer_id = null;
    $is_valid_referral = false;
    
    if (!empty($referral_code)) {
        $stmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE referral_code = ? AND is_active = 1");
        $stmt->execute([$referral_code]);
        $referrer = $stmt->fetch();
        
        if ($referrer) {
            // Check if referrer is trying to refer themselves
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if (!$stmt->fetch()) {
                $referrer_id = $referrer['id'];
                $is_valid_referral = true;
            }
        }
    }
    
    // Generate verification token
    $verification_token = bin2hex(random_bytes(32));
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $referral_code_generated = generateReferralCode($conn);
    
    $conn->beginTransaction();
    
    // Insert user
    $stmt = $conn->prepare("
        INSERT INTO users (full_name, email, password_hash, referral_code, verification_token, is_verified, email_verified, referred_by, created_at) 
        VALUES (?, ?, ?, ?, ?, 0, 0, ?, NOW())
    ");
    $stmt->execute([$full_name, $email, $password_hash, $referral_code_generated, $verification_token, $referrer_id]);
    $user_id = $conn->lastInsertId();
    
    // Process referral
    $referral_recorded = false;
    if ($is_valid_referral && $referrer_id) {
        // Check duplicate referral
        $stmt = $conn->prepare("SELECT id FROM referrals WHERE referrer_id = ? AND referred_user_id = ?");
        $stmt->execute([$referrer_id, $user_id]);
        if (!$stmt->fetch()) {
            $stmt = $conn->prepare("
                INSERT INTO referrals (
                    referrer_id, referred_user_id, referred_user_name, referral_code, 
                    reward_amount, is_rewarded, is_valid, validation_status, created_at
                ) VALUES (?, ?, ?, ?, ?, 0, 1, 'pending', NOW())
            ");
            $stmt->execute([
                $referrer_id,
                $user_id,
                $full_name,
                $referral_code,
                $referral_bonus
            ]);
            $referral_recorded = true;
            
            // Notify referrer
            sendNotification(
                $referrer_id,
                '🎉 Someone Joined Using Your Code!',
                $full_name . ' signed up using your referral code. Bonus will be credited after email verification.'
            );
        }
    }
    
    $conn->commit();
    
    // Send verification email
    try {
        $mailer = new Mailer();
        $mailer->sendVerification($email, $full_name, $verification_token);
    } catch (Exception $e) {
        error_log('Email error: ' . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Account created! Please check your email to verify your account.',
        'user' => [
            'id' => $user_id,
            'full_name' => $full_name,
            'email' => $email,
            'referral_code' => $referral_code_generated,
            'is_verified' => false
        ],
        'referral' => [
            'recorded' => $referral_recorded,
            'credited' => false,
            'message' => $referral_recorded ? 'Referral recorded! Bonus will be credited after email verification.' : 'No referral recorded.'
        ]
    ]);
    
} catch (PDOException $e) {
    if (isset($conn)) $conn->rollBack();
    error_log('Signup error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again later.']);
} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    error_log('Signup error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again later.']);
}
?>