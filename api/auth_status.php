<?php
// api/auth_status.php
// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['authenticated' => false]);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Verify user still exists and get current info
    $query = "SELECT id, first_name, last_name, email, role, is_verified FROM users WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        echo json_encode(['authenticated' => false]);
        exit;
    }
    
    echo json_encode([
        'authenticated' => true,
        'user' => [
            'id' => $user['id'],
            'name' => $user['first_name'] . ' ' . $user['last_name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'is_verified' => $user['is_verified']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Auth status error: " . $e->getMessage());
    echo json_encode(['authenticated' => false]);
}
?>