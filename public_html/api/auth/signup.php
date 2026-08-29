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

// ============================================
// ✅ INCLUDE FILES
// ============================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../helpers/mailer.php';

// ============================================
// ✅ GET INPUT
// ============================================

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
$full_name = trim($input['full_name'] ?? '');
$referral_code = trim($input['referral_code'] ?? '');

if (empty($email) || empty($password) || empty($full_name)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Validate password (min 6 chars)
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
    
    // ✅ CHECK REFERRAL CODE
    $referrer_id = null;
    $referral_bonus = 100.00;
    $referrer_email = '';
    $referrer_name = '';
    $is_valid_referral = false;
    
    if (!empty($referral_code)) {
        // Check if referral code exists
        $stmt = $conn->prepare("SELECT id, full_name, email, wallet_balance FROM users WHERE referral_code = ? AND is_active = 1");
        $stmt->execute([$referral_code]);
        $referrer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($referrer) {
            $referrer_id = $referrer['id'];
            $referrer_name = $referrer['full_name'];
            $referrer_email = $referrer['email'];
            
            // Get referral bonus from settings
            $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'referral_bonus'");
            $stmt->execute();
            $bonus_row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($bonus_row) {
                $referral_bonus = floatval($bonus_row['setting_value']);
            }
            
            $is_valid_referral = true;
        }
    }
    
    // Generate verification token
    $verification_token = bin2hex(random_bytes(32));
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $referral_code_generated = generateReferralCode($conn);
    
    // Begin transaction
    $conn->beginTransaction();
    
    // Insert user
    $stmt = $conn->prepare("
        INSERT INTO users (full_name, email, password_hash, referral_code, verification_token, is_verified, referred_by, created_at) 
        VALUES (?, ?, ?, ?, ?, 0, ?, NOW())
    ");
    $stmt->execute([$full_name, $email, $password_hash, $referral_code_generated, $verification_token, $referrer_id]);
    $user_id = $conn->lastInsertId();
    
    // ✅ PROCESS REFERRAL - DON'T CREDIT YET, JUST RECORD
    $referral_recorded = false;
    $referral_error = null;
    
    if ($is_valid_referral && $referrer_id) {
        try {
            // CHECK: Self-referral
            if ($referrer_id == $user_id) {
                $referral_error = "You cannot refer yourself";
            } else {
                // CHECK: Duplicate referral
                $stmt = $conn->prepare("SELECT id FROM referrals WHERE referrer_id = ? AND referred_user_id = ?");
                $stmt->execute([$referrer_id, $user_id]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    $referral_error = "Referral already recorded";
                } else {
                    // ✅ 1. ADD REFERRAL RECORD - BONUS NOT CREDITED YET
                    $stmt = $conn->prepare("
                    INSERT INTO referrals (
                        referrer_id, 
                        referred_user_id, 
                        referred_user_name,  -- ✅ ADD THIS
                        referral_code, 
                        reward_amount, 
                        is_rewarded, 
                        is_valid, 
                        validation_status,
                        created_at
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
                    
                    error_log("📝 Referral recorded for user {$user_id} (pending email verification)");
                    
                    // Inline INSERT rather than helpers/functions.php's sendNotification() —
                    // this file declares its own generateReferralCode($conn), which has a
                    // different signature than the one in functions.php. Requiring that file
                    // here would redeclare the function name and fatal-error the whole signup.
                    $notifyStmt = $conn->prepare("
                        INSERT INTO notifications (user_id, title, message, type, created_at)
                        VALUES (?, ?, ?, 'in_app', NOW())
                    ");
                    $notifyStmt->execute([
                        $referrer_id,
                        '🎉 Someone Joined Using Your Code!',
                        $full_name . ' signed up using your referral code. Your bonus credits once they verify their email.'
                    ]);
                }
            }
        } catch (PDOException $e) {
            error_log('PDO error in ./api/auth/signup.php: ' . $e->getMessage());
            if ($e->getCode() == 23000 && strpos($e->getMessage(), 'unique_referral') !== false) {
                $referral_error = "Referral already exists";
            } else {
                throw $e;
            }
        }
    }
    
    $conn->commit();
    
    // Send verification email to new user
    try {
        $mailer = new Mailer();
        $mailer->sendVerification($email, $full_name, $verification_token);
        error_log("✅ Verification email sent to: " . $email);
    } catch (Exception $e) {
        error_log('Email error: ' . $e->getMessage());
    }
    
    // ✅ RESPONSE
    $response = [
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
    ];
    
    if ($referral_error) {
        $response['referral']['error'] = $referral_error;
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log('PDO error in ./api/auth/signup.php: ' . $e->getMessage());
    if (isset($conn)) $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again later.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again later.']);
}

function generateReferralCode($conn) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    do {
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        $stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ?");
        $stmt->execute([$code]);
        $exists = $stmt->fetch();
    } while ($exists);
    return $code;
}
?>