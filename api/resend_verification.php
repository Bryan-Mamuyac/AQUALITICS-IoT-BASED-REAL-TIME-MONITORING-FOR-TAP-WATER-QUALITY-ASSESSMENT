<?php
// api/resend_verification.php
require_once '../config/database.php';
require_once '../config/email_config.php';

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

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Email required']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if user exists and is not verified
    $userQuery = "SELECT id, is_verified FROM users WHERE email = ?";
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
    
    // Generate new verification code
    $verificationCode = sprintf('%06d', mt_rand(100000, 999999));
    
    // Update user's verification code
    $updateQuery = "UPDATE users SET verification_code = ? WHERE id = ?";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute([$verificationCode, $user['id']]);
    
    // Clean up old email_verifications entries and insert new one
    $cleanupQuery = "DELETE FROM email_verifications WHERE email = ?";
    $cleanupStmt = $db->prepare($cleanupQuery);
    $cleanupStmt->execute([$email]);
    
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    $insertQuery = "INSERT INTO email_verifications (email, verification_code, expires_at) VALUES (?, ?, ?)";
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->execute([$email, $verificationCode, $expiresAt]);
    
    // Send verification email
    if (sendVerificationEmail($email, $verificationCode)) {
        echo json_encode(['success' => true, 'message' => 'Verification code resent successfully']);
    } else {
        echo json_encode([
            'success' => true, 
            'message' => 'New verification code generated. Code: ' . $verificationCode
        ]);
    }
    
} catch (Exception $e) {
    error_log("Resend verification error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to resend verification code']);
}
?>