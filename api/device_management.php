<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    switch ($action) {
        case 'list':
            handleListDevices($db);
            break;
            
        case 'check_status':
            handleCheckStatus($db, $userId);
            break;
            
        case 'claim_device':
            handleClaimDevice($db, $userId);
            break;
            
        case 'release_device':
            handleReleaseDevice($db, $userId);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

function handleListDevices($db) {
    $query = "SELECT id, device_name, device_id, location, status FROM iot_devices WHERE status = 'active' ORDER BY device_name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'devices' => $devices]);
}

function handleCheckStatus($db, $userId) {
    // First, auto-end sessions older than 2 hours (inactive users)
    $cleanupQuery = "UPDATE device_sessions 
                     SET is_active = 0, ended_at = NOW() 
                     WHERE is_active = 1 
                     AND TIMESTAMPDIFF(HOUR, started_at, NOW()) >= 2";
    $db->prepare($cleanupQuery)->execute();
    
    // Check if any device is currently being actively used
    $query = "SELECT ds.*, u.first_name, u.last_name, d.device_name, d.device_id,
              TIMESTAMPDIFF(MINUTE, ds.started_at, NOW()) as minutes_active
              FROM device_sessions ds
              JOIN users u ON ds.user_id = u.id
              JOIN iot_devices d ON ds.device_id = d.id
              WHERE ds.is_active = 1
              ORDER BY ds.started_at DESC
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session) {
        $isCurrentUser = ($session['user_id'] == $userId);
        $minutesActive = intval($session['minutes_active']);
        
        // Format duration
        $duration = '';
        if ($minutesActive >= 60) {
            $hours = floor($minutesActive / 60);
            $minutes = $minutesActive % 60;
            $duration = $hours . ' hour' . ($hours > 1 ? 's' : '');
            if ($minutes > 0) {
                $duration .= ' ' . $minutes . ' min';
            }
        } elseif ($minutesActive > 0) {
            $duration = $minutesActive . ' minute' . ($minutesActive > 1 ? 's' : '');
        } else {
            $duration = 'just now';
        }
        
        echo json_encode([
            'success' => true,
            'status' => [
                'in_use' => true,
                'is_current_user' => $isCurrentUser,
                'used_by' => $session['first_name'] . ' ' . $session['last_name'],
                'device_name' => $session['device_name'],
                'device_id' => $session['device_id'],
                'started_at' => $session['started_at'],
                'duration' => $duration,
                'minutes_active' => $minutesActive
            ]
        ]);
    } else {
        // No active sessions - device is free to use
        echo json_encode([
            'success' => true,
            'status' => [
                'in_use' => false,
                'is_current_user' => false,
                'device_available' => true
            ]
        ]);
    }
}

function handleClaimDevice($db, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    $deviceId = $input['device_id'] ?? null;
    
    if (!$deviceId) {
        echo json_encode(['success' => false, 'message' => 'Device ID required']);
        return;
    }
    
    // Clean up old sessions first
    $cleanupQuery = "UPDATE device_sessions 
                     SET is_active = 0, ended_at = NOW() 
                     WHERE is_active = 1 
                     AND TIMESTAMPDIFF(HOUR, started_at, NOW()) >= 2";
    $db->prepare($cleanupQuery)->execute();
    
    // Check if device is already in use by someone else
    $checkQuery = "SELECT ds.user_id, u.first_name, u.last_name 
                   FROM device_sessions ds
                   JOIN users u ON ds.user_id = u.id
                   WHERE ds.device_id = ? AND ds.is_active = 1";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$deviceId]);
    $existingSession = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingSession) {
        if ($existingSession['user_id'] == $userId) {
            echo json_encode(['success' => true, 'message' => 'You already have this device claimed']);
        } else {
            $userName = $existingSession['first_name'] . ' ' . $existingSession['last_name'];
            echo json_encode([
                'success' => false, 
                'message' => "Device is currently in use by {$userName}. Please wait until they finish or try another device."
            ]);
        }
        return;
    }
    
    // End any previous sessions for this user
    $endPreviousQuery = "UPDATE device_sessions SET is_active = 0, ended_at = NOW() WHERE user_id = ? AND is_active = 1";
    $db->prepare($endPreviousQuery)->execute([$userId]);
    
    // Claim the device
    $insertQuery = "INSERT INTO device_sessions (device_id, user_id, started_at, is_active) VALUES (?, ?, NOW(), 1)";
    $insertStmt = $db->prepare($insertQuery);
    $result = $insertStmt->execute([$deviceId, $userId]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Device claimed successfully! You can now collect data.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to claim device. Please try again.']);
    }
}

function handleReleaseDevice($db, $userId) {
    // End all active sessions for this user
    $updateQuery = "UPDATE device_sessions SET is_active = 0, ended_at = NOW() WHERE user_id = ? AND is_active = 1";
    $updateStmt = $db->prepare($updateQuery);
    $result = $updateStmt->execute([$userId]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Device released successfully. Other users can now use it.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to release device']);
    }
}
?>