<?php
// logout.php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Store user name for goodbye message (optional)
$userName = isset($_SESSION['name']) ? $_SESSION['name'] : 'User';

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Start a new session for the logout message
session_start();
$_SESSION['logout_message'] = "Goodbye " . htmlspecialchars($userName) . "! You have been logged out successfully.";

// Redirect to login page
header('Location: index.php');
exit;
?>