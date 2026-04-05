<?php
/**
 * device_pairing.php - COMPLETE VERSION
 * Combines Arduino heartbeat support + Single User Blocking
 */

session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Special handling for beacon disconnect (comes through GET with POST body)
if ($action === 'disconnect' && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST)) {
    $action = 'disconnect';
}

// Allow check_pairing without authentication (for Arduino)
if ($action !== 'check_pairing' && $action !== 'check_device_online') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    $userId = $_SESSION['user_id'];
} else {
    $userId = null;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    switch ($action) {
        case 'list_devices':
            listDevices($db);
            break;
            
        case 'connect':
            connectDevice($db, $userId);
            break;
            
        case 'disconnect':
            disconnectDevice($db, $userId);
            break;
            
        case 'status':
            getConnectionStatus($db, $userId);
            break;
            
        case 'user_heartbeat':
            updateUserHeartbeat($db, $userId);
            break;
            
        case 'check_pairing':
            checkDevicePairing($db);
            break;
            
        case 'check_device_online':
            checkDeviceOnlineStatus($db);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function listDevices($db) {
    $query = "SELECT id, device_id, device_name, location, status FROM iot_devices WHERE status = 'active' ORDER BY device_name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($devices as &$device) {
        $device['is_online'] = isDeviceOnline($db, $device['id']);
    }
    
    echo json_encode(['success' => true, 'devices' => $devices]);
}

function isDeviceOnline($db, $deviceId) {
    $query = "SELECT last_heartbeat,
              TIMESTAMPDIFF(SECOND, last_heartbeat, NOW()) as seconds_since_heartbeat
              FROM device_sessions
              WHERE device_id = ? 
              AND last_heartbeat IS NOT NULL
              AND TIMESTAMPDIFF(SECOND, last_heartbeat, NOW()) <= 45
              ORDER BY last_heartbeat DESC
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$deviceId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? true : false;
}

function connectDevice($db, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    $deviceId = $input['device_id'] ?? null;
    
    if (!$deviceId) {
        echo json_encode(['success' => false, 'message' => 'Device ID required']);
        return;
    }
    
    // Start transaction for atomic operations
    $db->beginTransaction();
    
    try {
        // Check if device exists
        $deviceQuery = "SELECT id, device_id, device_name FROM iot_devices WHERE id = ? AND status = 'active'";
        $deviceStmt = $db->prepare($deviceQuery);
        $deviceStmt->execute([$deviceId]);
        $device = $deviceStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$device) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Device not found']);
            return;
        }
        
        // Check if device is online
        if (!isDeviceOnline($db, $deviceId)) {
            $db->rollBack();
            echo json_encode([
                'success' => false, 
                'message' => '⚠️ Arduino device is OFFLINE! Please ensure the Arduino is powered on and connected to the network.',
                'device_offline' => true
            ]);
            return;
        }
        
        // ⭐ CRITICAL: Check if device is already in use by ANOTHER user (SINGLE USER BLOCKING)
        $checkQuery = "SELECT ds.user_id, u.first_name, u.last_name,
                       TIMESTAMPDIFF(MINUTE, ds.started_at, NOW()) as minutes_connected
                       FROM device_sessions ds
                       JOIN users u ON ds.user_id = u.id
                       WHERE ds.device_id = ? AND ds.is_active = 1 AND ds.user_id != ?
                       ORDER BY ds.started_at DESC
                       LIMIT 1";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$deviceId, $userId]);
        $existingSession = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingSession) {
            $db->rollBack();
            
            $userName = $existingSession['first_name'] . ' ' . $existingSession['last_name'];
            $minutes = $existingSession['minutes_connected'];
            
            $time_text = $minutes < 1 ? 'just now' : 
                        ($minutes < 60 ? "{$minutes} minute" . ($minutes > 1 ? 's' : '') . " ago" :
                        floor($minutes / 60) . " hour" . (floor($minutes / 60) > 1 ? 's' : '') . " ago");
            
            echo json_encode([
                'success' => false, 
                'message' => "⚠️ Device is currently in use by {$userName} (connected {$time_text})",
                'in_use' => true,
                'current_user' => $userName,
                'minutes_connected' => $minutes,
                'blocked' => true
            ]);
            return;
        }
        
        // Check if current user already connected to this device
        $checkOwnQuery = "SELECT id FROM device_sessions 
                         WHERE device_id = ? AND user_id = ? AND is_active = 1";
        $checkOwnStmt = $db->prepare($checkOwnQuery);
        $checkOwnStmt->execute([$deviceId, $userId]);
        $ownSession = $checkOwnStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ownSession) {
            // Already connected, just update heartbeat
            $updateQuery = "UPDATE device_sessions SET last_heartbeat = NOW() WHERE id = ?";
            $db->prepare($updateQuery)->execute([$ownSession['id']]);
            
            $db->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => '✅ You are already connected to this device',
                'device_name' => $device['device_name'],
                'already_connected' => true
            ]);
            return;
        }
        
        // End any previous connections by this user
        $endPreviousQuery = "UPDATE device_sessions SET is_active = 0, ended_at = NOW() WHERE user_id = ? AND is_active = 1";
        $db->prepare($endPreviousQuery)->execute([$userId]);
        
        // Create new connection
        $insertQuery = "INSERT INTO device_sessions (device_id, user_id, started_at, last_heartbeat, is_active) 
                        VALUES (?, ?, NOW(), NOW(), 1)";
        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->execute([$deviceId, $userId]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "✅ Connected to {$device['device_name']}! Arduino will start collecting data.",
            'device_name' => $device['device_name'],
            'device_id' => $deviceId
        ]);
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Connect error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to connect to device']);
    }
}

function disconnectDevice($db, $userId) {
    $updateQuery = "UPDATE device_sessions SET is_active = 0, ended_at = NOW() WHERE user_id = ? AND is_active = 1";
    $result = $db->prepare($updateQuery)->execute([$userId]);
    
    echo json_encode([
        'success' => true, 
        'message' => '✅ Disconnected successfully.'
    ]);
}

function getConnectionStatus($db, $userId) {
    $query = "SELECT ds.*, d.device_name, d.device_id,
              TIMESTAMPDIFF(MINUTE, ds.started_at, NOW()) as minutes_connected,
              TIMESTAMPDIFF(SECOND, ds.last_heartbeat, NOW()) as seconds_since_heartbeat
              FROM device_sessions ds
              JOIN iot_devices d ON ds.device_id = d.id
              WHERE ds.user_id = ? AND ds.is_active = 1
              ORDER BY ds.started_at DESC
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$userId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session) {
        $isOnline = $session['seconds_since_heartbeat'] <= 45;
        
        echo json_encode([
            'success' => true,
            'connected' => true,
            'device_name' => $session['device_name'],
            'device_id' => $session['device_id'],
            'started_at' => $session['started_at'],
            'minutes_connected' => $session['minutes_connected'],
            'is_online' => $isOnline
        ]);
    } else {
        echo json_encode(['success' => true, 'connected' => false]);
    }
}

function updateUserHeartbeat($db, $userId) {
    try {
        $updateQuery = "UPDATE device_sessions 
                       SET last_heartbeat = NOW() 
                       WHERE user_id = ? AND is_active = 1";
        $stmt = $db->prepare($updateQuery);
        $result = $stmt->execute([$userId]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Heartbeat updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No active session']);
        }
    } catch (Exception $e) {
        error_log("Heartbeat error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Heartbeat failed']);
    }
}

function checkDevicePairing($db) {
    // CRITICAL: This is called by Arduino - must ALWAYS work
    $deviceIdParam = $_GET['device_id'] ?? 'AQUA_001';
    
    // Get device DB ID
    $deviceQuery = "SELECT id FROM iot_devices WHERE device_id = ? LIMIT 1";
    $deviceStmt = $db->prepare($deviceQuery);
    $deviceStmt->execute([$deviceIdParam]);
    $device = $deviceStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$device) {
        echo json_encode([
            'success' => false,
            'paired' => false,
            'message' => 'Device not registered in database'
        ]);
        return;
    }
    
    $deviceId = $device['id'];
    
    // Cleanup old sessions (5 minutes timeout)
    $cleanupQuery = "UPDATE device_sessions 
                     SET is_active = 0, ended_at = NOW() 
                     WHERE is_active = 1 
                     AND last_heartbeat IS NOT NULL
                     AND TIMESTAMPDIFF(MINUTE, last_heartbeat, NOW()) >= 5";
    $db->prepare($cleanupQuery)->execute();
    
    // Check for active pairing
    $pairingQuery = "SELECT ds.user_id, u.first_name, u.last_name
                     FROM device_sessions ds
                     JOIN users u ON ds.user_id = u.id
                     WHERE ds.device_id = ? AND ds.is_active = 1
                     ORDER BY ds.started_at DESC
                     LIMIT 1";
    
    $pairingStmt = $db->prepare($pairingQuery);
    $pairingStmt->execute([$deviceId]);
    $pairing = $pairingStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pairing) {
        // PAIRED - Update heartbeat on active session
        $updateHeartbeatQuery = "UPDATE device_sessions 
                                 SET last_heartbeat = NOW() 
                                 WHERE device_id = ? AND user_id = ? AND is_active = 1";
        $db->prepare($updateHeartbeatQuery)->execute([$deviceId, $pairing['user_id']]);
        
        $userName = $pairing['first_name'] . ' ' . $pairing['last_name'];
        echo json_encode([
            'success' => true,
            'paired' => true,
            'user_id' => $pairing['user_id'],
            'user_name' => $userName,
            'message' => "Paired with {$userName}"
        ]);
    } else {
        // NOT PAIRED - But create/update heartbeat tracking session
        // This allows website to see device is online
        
        // Check if there's ANY session for this device (to update heartbeat)
        $anySessionQuery = "SELECT id FROM device_sessions 
                           WHERE device_id = ? 
                           ORDER BY started_at DESC 
                           LIMIT 1";
        $anySessionStmt = $db->prepare($anySessionQuery);
        $anySessionStmt->execute([$deviceId]);
        $existingSession = $anySessionStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingSession) {
            // Update existing session's heartbeat
            $updateQuery = "UPDATE device_sessions 
                           SET last_heartbeat = NOW() 
                           WHERE id = ?";
            $db->prepare($updateQuery)->execute([$existingSession['id']]);
        } else {
            // Create tracking session with NULL user (device online but not paired)
            $createQuery = "INSERT INTO device_sessions 
                           (device_id, user_id, started_at, last_heartbeat, ended_at, is_active) 
                           VALUES (?, NULL, NOW(), NOW(), NOW(), 0)";
            $db->prepare($createQuery)->execute([$deviceId]);
        }
        
        echo json_encode([
            'success' => true,
            'paired' => false,
            'message' => 'Device online, no active pairing'
        ]);
    }
}

function checkDeviceOnlineStatus($db) {
    $deviceId = $_GET['device_id'] ?? null;
    
    if (!$deviceId) {
        echo json_encode(['success' => false, 'message' => 'Device ID required']);
        return;
    }
    
    $isOnline = isDeviceOnline($db, $deviceId);
    
    $lastHeartbeatQuery = "SELECT last_heartbeat,
                          TIMESTAMPDIFF(SECOND, last_heartbeat, NOW()) as seconds_ago
                          FROM device_sessions
                          WHERE device_id = ? 
                          AND last_heartbeat IS NOT NULL
                          ORDER BY last_heartbeat DESC
                          LIMIT 1";
    
    $stmt = $db->prepare($lastHeartbeatQuery);
    $stmt->execute([$deviceId]);
    $heartbeatInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'is_online' => $isOnline,
        'message' => $isOnline ? 'Device is online' : 'Device is offline',
        'last_heartbeat' => $heartbeatInfo ? $heartbeatInfo['last_heartbeat'] : null,
        'seconds_since_heartbeat' => $heartbeatInfo ? $heartbeatInfo['seconds_ago'] : null
    ]);
}
?>