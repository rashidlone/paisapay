<?php
// /verify-email.php - Direct include

error_reporting(E_ALL);
ini_set('display_errors', 1);

$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($token)) {
    die('Invalid verification token');
}

// ✅ Include verify.php directly
include __DIR__ . '/api/auth/verify.php';
exit;
?>