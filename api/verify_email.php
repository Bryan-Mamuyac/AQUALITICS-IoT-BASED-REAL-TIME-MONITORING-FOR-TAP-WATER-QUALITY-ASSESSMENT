<?php
// api/verify_email.php
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);
$code = trim($input['code'] ?? '');

if (!$email || !$code) {
    echo json_encode(['success' => false, 'message' => 'Email and verification code required']);
    exit;
}

if (strlen($code) !== 6 || !ctype_digit($code)) {
    echo json_encode(['success' => false, 'message' => 'Invalid verification code format']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // First, check if user exists and get their current verification code
    $userQuery = "SELECT id, verification_code, is_verified FROM users WHERE email = ?";
    $userStmt = $db->prepare($userQuery);
    $userStmt->execute([$email]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User account not found']);
        exit;
    }
    
    if ($user['is_verified']) {
        echo json_encode(['success' => false, 'message' => 'Email is already verified']);
        exit;
    }
    
    // Check if the verification code matches
    if ($user['verification_code'] !== $code) {
        // Also check in email_verifications table as backup
        $backupQuery = "SELECT id FROM email_verifications 
                       WHERE email = ? AND verification_code = ? AND expires_at > NOW()";
        $backupStmt = $db->prepare($backupQuery);
        $backupStmt->execute([$email, $code]);
        
        if (!$backupStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code']);
            exit;
        }
    }
    
    // Update user verification status
    $updateQuery = "UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = ?";
    $updateStmt = $db->prepare($updateQuery);
    $updateResult = $updateStmt->execute([$user['id']]);
    
    if ($updateResult) {
        // Clean up email_verifications table
        $cleanupQuery = "DELETE FROM email_verifications WHERE email = ?";
        $cleanupStmt = $db->prepare($cleanupQuery);
        $cleanupStmt->execute([$email]);
        
        echo json_encode(['success' => true, 'message' => 'Email verified successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to verify email. Please try again.']);
    }
    
} catch (Exception $e) {
    error_log("Verification error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Verification failed due to server error']);
}
?>