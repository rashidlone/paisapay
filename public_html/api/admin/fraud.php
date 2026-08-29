<?php
// /api/admin/fraud.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/classes/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/helpers/functions.php';

$admin_id = $_SESSION['admin_id'];

try {
    $conn = Database::getInstance()->getConnection();
    
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $status = isset($_GET['status']) && $_GET['status'] != 'all' ? $_GET['status'] : 'pending';
        $query = "SELECT fr.*, u.full_name, u.phone_number, u.wallet_balance FROM fraud_reports fr 
                  JOIN users u ON fr.user_id = u.id";
        if ($status != 'all') {
            // PDO::quote() already wraps the value in quotes — see withdrawals.php fix.
            $query .= " WHERE fr.status = " . $conn->quote($status);
        }
        $query .= " ORDER BY fr.created_at DESC";
        
        $stmt = $conn->query($query);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $reports]);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $report_id = isset($input['report_id']) ? intval($input['report_id']) : 0;
        $status = isset($input['status']) ? $input['status'] : '';
        $resolution = isset($input['resolution']) ? trim($input['resolution']) : '';
        
        if (!$report_id || !$status) {
            echo json_encode(['success' => false, 'message' => 'Missing fields']);
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE fraud_reports SET status = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $admin_id, $report_id]);
        
        // Only notify on a confirmed finding — a dismissed report means nothing
        // came of it, and isn't something the user needs to see or act on.
        if ($status === 'confirmed') {
            $userStmt = $conn->prepare("SELECT user_id FROM fraud_reports WHERE id = ?");
            $userStmt->execute([$report_id]);
            $reportUserId = $userStmt->fetchColumn();
            if ($reportUserId) {
                sendNotification(
                    $reportUserId,
                    '⚠️ Account Flagged for Review',
                    'Suspicious activity was found on your account during a review.' . ($resolution ? ' ' . $resolution : '')
                );
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Fraud report updated']);
        exit;
    }
    
} catch (PDOException $e) {
    error_log('PDO error in ./api/admin/fraud.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again later.']);
}
?>