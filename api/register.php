<?php
// api/register.php
require_once '../config/database.php';
require_once '../config/email_config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Log the received input for debugging
error_log("Registration input: " . json_encode($input));

// Validate input with null coalescing - CHANGED FROM GENDER TO LOCATION
$firstName = trim($input['firstName'] ?? '');
$lastName = trim($input['lastName'] ?? '');
$location = trim($input['location'] ?? '');
$email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);
$password = $input['password'] ?? '';

// Validation checks
if (!$firstName || !$lastName || !$location || !$email || !$password) {
    error_log("Missing required fields - First: $firstName, Last: $lastName, Location: $location, Email: $email, Password: " . (!empty($password) ? 'provided' : 'missing'));
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    error_log("Invalid email format: $email");
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

if (strlen($password) < 6) {
    error_log("Password too short: " . strlen($password) . " characters");
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

// Validate location enum
$validLocations = [
    'Metro San Fernando',
    'Sevilla',
    'Catbangen',
    'Lingsat',
    'Poro',
    'Tanqui',
    'Pagdalagan'
];

if (!in_array($location, $validLocations)) {
    error_log("Invalid location value: $location");
    echo json_encode(['success' => false, 'message' => 'Invalid location selection']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if email already exists
    $checkQuery = "SELECT id FROM users WHERE email = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$email]);
    
    if ($checkStmt->rowCount() > 0) {
        error_log("Email already exists: $email");
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        exit;
    }
    
    // Generate verification code
    $verificationCode = sprintf('%06d', mt_rand(100000, 999999));
    error_log("Generated verification code: $verificationCode for email: $email");
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user with explicit column specification - CHANGED GENDER TO LOCATION
    $insertQuery = "INSERT INTO users (first_name, last_name, location, email, password, verification_code, is_verified, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 0, NOW())";
    $insertStmt = $db->prepare($insertQuery);
    $insertResult = $insertStmt->execute([$firstName, $lastName, $location, $email, $hashedPassword, $verificationCode]);
    
    if ($insertResult) {
        $userId = $db->lastInsertId();
        error_log("User created successfully with ID: $userId");
        
        // Also insert into email_verifications table for backup/tracking
        $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        // Clean up any existing verification codes for this email first
        $cleanupQuery = "DELETE FROM email_verifications WHERE email = ?";
        $cleanupStmt = $db->prepare($cleanupQuery);
        $cleanupStmt->execute([$email]);
        
        // Insert new verification record
        $verificationQuery = "INSERT INTO email_verifications (email, verification_code, expires_at, created_at) 
                             VALUES (?, ?, ?, NOW())";
        $verificationStmt = $db->prepare($verificationQuery);
        $verificationResult = $verificationStmt->execute([$email, $verificationCode, $expiresAt]);
        
        error_log("Email verification record created: " . ($verificationResult ? 'success' : 'failed'));
        
        // Send verification email
        $emailSent = false;
        try {
            $emailSent = sendVerificationEmail($email, $verificationCode);
            error_log("Email sending result: " . ($emailSent ? 'success' : 'failed'));
        } catch (Exception $emailError) {
            error_log("Email sending exception: " . $emailError->getMessage());
        }
        
        if ($emailSent) {
            echo json_encode([
                'success' => true, 
                'message' => 'Registration successful! Please check your email for verification code.',
                'debug' => [
                    'email' => $email,
                    'code_length' => strlen($verificationCode),
                    'user_id' => $userId
                ]
            ]);
        } else {
            // Still successful registration, but show code for debugging
            echo json_encode([
                'success' => true, 
                'message' => 'Registration successful! Email sending failed. Your verification code is: ' . $verificationCode,
                'debug' => [
                    'email' => $email,
                    'code' => $verificationCode,
                    'user_id' => $userId
                ]
            ]);
        }
    } else {
        error_log("Failed to insert user into database");
        echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
    }
    
} catch (PDOException $e) {
    error_log("Database error during registration: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.']);
} catch (Exception $e) {
    error_log("General error during registration: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Registration failed due to server error']);
}
?>