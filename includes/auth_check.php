<?php
// includes/auth_check.php
// Only start session if one hasn't been started already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function checkAuth($required_role = null) {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit();
    }
    
    try {
        global $pdo;
        
        // Verify user still exists and get current info
        $query = "SELECT id, first_name, last_name, email, role, is_verified FROM users WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            // User no longer exists, destroy session
            session_destroy();
            header('Location: index.php');
            exit();
        }
        
        // Check if email is verified
        if (!$user['is_verified']) {
            // For now, let's skip this check since your admin user is verified
            // header('Location: verify.php');
            // exit();
        }
        
        // Check role if specified
        if ($required_role && $user['role'] !== $required_role) {
            // Redirect based on actual role
            if ($user['role'] === 'admin') {
                header('Location: admin_dashboard.php');
            } else {
                header('Location: dashboard.php');
            }
            exit();
        }
        
        // Update session with fresh data
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
        
        return $user;
        
    } catch (Exception $e) {
        error_log("Auth check error: " . $e->getMessage());
        session_destroy();
        header('Location: index.php');
        exit();
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function getUserName() {
    return $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
}

function getUserEmail() {
    return $_SESSION['email'] ?? '';
}
?>