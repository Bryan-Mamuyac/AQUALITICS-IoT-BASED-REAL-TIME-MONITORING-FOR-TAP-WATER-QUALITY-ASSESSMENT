<?php
header('Content-Type: application/json');
require_once '../config/database.php';

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email']) || !isset($data['code']) || !isset($data['newPassword'])) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

$email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
$code = $data['code'];
$newPassword = $data['newPassword'];

// Validate inputs
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

if (strlen($code) !== 6 || !ctype_digit($code)) {
    echo json_encode(['success' => false, 'message' => 'Invalid reset code']);
    exit;
}

if (strlen($newPassword) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
    exit;
}

try {
    // Verify reset code
    $stmt = $pdo->prepare("
        SELECT id, expires_at, used 
        FROM password_resets 
        WHERE email = ? AND reset_code = ? AND used = 0
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$email, $code]);
    $resetRecord = $stmt->fetch();
    
    if (!$resetRecord) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired reset code']);
        exit;
    }
    
    // Check if code has expired
    if (strtotime($resetRecord['expires_at']) < time()) {
        echo json_encode(['success' => false, 'message' => 'Reset code has expired. Please request a new one.']);
        exit;
    }
    
    // Hash the new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update user password
    $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE email = ?");
    $updateSuccess = $stmt->execute([$hashedPassword, $email]);
    
    if ($updateSuccess) {
        // Mark reset code as used
        $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
        $stmt->execute([$resetRecord['id']]);
        
        // Delete all other reset codes for this email
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ? AND id != ?");
        $stmt->execute([$email, $resetRecord['id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Password reset successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update password. Please try again.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Reset password error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ]);
}
?>