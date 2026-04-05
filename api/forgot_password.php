<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/email_config.php';

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email']) || empty($data['email'])) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

$email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

try {
    // Check if user exists and is verified
    $stmt = $pdo->prepare("SELECT id, first_name, is_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Don't reveal if email exists or not for security
        echo json_encode(['success' => true, 'message' => 'If your email exists, you will receive a reset code']);
        exit;
    }
    
    if (!$user['is_verified']) {
        echo json_encode(['success' => false, 'message' => 'Please verify your email first']);
        exit;
    }
    
    // Generate 6-digit reset code
    $resetCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // Set expiration time (15 minutes from now)
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    // Delete any existing reset codes for this email
    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
    $stmt->execute([$email]);
    
    // Insert new reset code
    $stmt = $pdo->prepare("INSERT INTO password_resets (email, reset_code, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$email, $resetCode, $expiresAt]);
    
    // Send reset email
    $emailSent = sendPasswordResetEmail($email, $resetCode, $user['first_name']);
    
    if ($emailSent) {
        echo json_encode([
            'success' => true,
            'message' => 'Reset code sent to your email'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send email. Please try again.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Forgot password error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ]);
}
?>