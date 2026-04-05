<?php
/**
 * AQUALITICS - Arduino Upload API (DEBUG VERSION)
 * This version shows detailed errors to help debug the 500 error
 */

// ENABLE ERROR DISPLAY FOR DEBUGGING
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Setup logging
$logFile = __DIR__ . '/../logs/arduino_uploads.log';
$logDir = dirname($logFile);
if (!file_exists($logDir)) {
    @mkdir($logDir, 0755, true);
}

function logMessage($message) {
    global $logFile;
    $timestamp = date('[Y-m-d H:i:s] ');
    @file_put_contents($logFile, $timestamp . $message . "\n", FILE_APPEND);
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

logMessage("=== NEW UPLOAD REQUEST ===");
logMessage("Method: " . $_SERVER['REQUEST_METHOD']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $jsonInput = file_get_contents('php://input');
    logMessage("Raw JSON: " . $jsonInput);
    
    $data = json_decode($jsonInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    // Validate required fields
    $requiredFields = ['ph_level', 'tds_value', 'ec_value', 'turbidity', 'temperature'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Missing field: $field");
        }
    }
    
    logMessage("✓ JSON validation passed");
    
    // Check if database config exists
    $dbConfigPath = __DIR__ . '/../config/database.php';
    if (!file_exists($dbConfigPath)) {
        throw new Exception("Database config not found at: " . $dbConfigPath);
    }
    
    require_once $dbConfigPath;
    logMessage("✓ Database config loaded");
    
    $database = new Database();
    $db = $database->getConnection();
    logMessage("✓ Database connected");
    
    // Get device ID
    $deviceQuery = "SELECT id FROM iot_devices WHERE device_id = 'AQUA_001' LIMIT 1";
    $deviceStmt = $db->prepare($deviceQuery);
    $deviceStmt->execute();
    $device = $deviceStmt->fetch(PDO::FETCH_ASSOC);
    $deviceId = $device ? $device['id'] : 1;
    logMessage("✓ Device ID: " . $deviceId);
    
    // ============================================
    // CHECK IF device_sessions TABLE EXISTS
    // ============================================
    
    $tableCheckQuery = "SHOW TABLES LIKE 'device_sessions'";
    $tableCheckStmt = $db->prepare($tableCheckQuery);
    $tableCheckStmt->execute();
    $tableExists = $tableCheckStmt->fetch();
    
    $userId = null;
    $userName = 'System';
    $detectionMethod = 'unknown';
    
    if ($tableExists) {
        logMessage("✓ device_sessions table exists");
        
        // Try to get active claim
        try {
            // First, expire old sessions
            $expireQuery = "UPDATE device_sessions 
                           SET is_active = 0, ended_at = NOW() 
                           WHERE is_active = 1 
                           AND TIMESTAMPDIFF(HOUR, started_at, NOW()) >= 2";
            $db->prepare($expireQuery)->execute();
            logMessage("✓ Expired old sessions");
            
            // Get active claim
            $claimQuery = "SELECT ds.user_id, u.first_name, u.last_name
                          FROM device_sessions ds
                          JOIN users u ON ds.user_id = u.id
                          WHERE ds.device_id = ? 
                          AND ds.is_active = 1
                          ORDER BY ds.started_at DESC 
                          LIMIT 1";
            
            $claimStmt = $db->prepare($claimQuery);
            $claimStmt->execute([$deviceId]);
            $activeClaim = $claimStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($activeClaim) {
                $userId = $activeClaim['user_id'];
                $userName = $activeClaim['first_name'] . ' ' . $activeClaim['last_name'];
                $detectionMethod = 'device_claim';
                logMessage("✓ Device claimed by: {$userName} (ID: {$userId})");
                
                // Update heartbeat
                $heartbeatQuery = "UPDATE device_sessions 
                                  SET last_heartbeat = NOW() 
                                  WHERE user_id = ? AND is_active = 1";
                $db->prepare($heartbeatQuery)->execute([$userId]);
            }
        } catch (Exception $e) {
            logMessage("⚠ Error checking device claim: " . $e->getMessage());
        }
    } else {
        logMessage("⚠ WARNING: device_sessions table does NOT exist!");
        logMessage("  └─ Please run the SQL to create this table");
    }
    
    // If no claim found, use YOUR account (Bryan - ID: 16) as default
    if (!$userId) {
        logMessage("→ No device claim found, using default account (ID: 16)");
        
        // ⭐ CONFIGURATION: Default user when no one has claimed device
        $DEFAULT_USER_ID = 16; // Bryan Mamuyac - Change this if needed
        
        $defaultQuery = "SELECT id, first_name, last_name 
                        FROM users 
                        WHERE id = ? AND is_verified = 1 
                        LIMIT 1";
        $defaultStmt = $db->prepare($defaultQuery);
        $defaultStmt->execute([$DEFAULT_USER_ID]);
        $defaultUser = $defaultStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($defaultUser) {
            $userId = $defaultUser['id'];
            $userName = $defaultUser['first_name'] . ' ' . $defaultUser['last_name'];
            $detectionMethod = 'default_user';
            logMessage("✓ Using default user: {$userName} (ID: {$userId})");
        } else {
            // Fallback to admin if default user not found
            logMessage("⚠ Default user ID {$DEFAULT_USER_ID} not found, using admin...");
            
            $adminQuery = "SELECT id, first_name, last_name 
                          FROM users 
                          WHERE role = 'admin' AND is_verified = 1 
                          ORDER BY id ASC 
                          LIMIT 1";
            $adminStmt = $db->prepare($adminQuery);
            $adminStmt->execute();
            $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admin) {
                $userId = $admin['id'];
                $userName = $admin['first_name'] . ' ' . $admin['last_name'];
                $detectionMethod = 'admin_fallback';
                logMessage("✓ Using admin fallback: {$userName} (ID: {$userId})");
            } else {
                // Ultimate fallback - any verified user
                $ultimateQuery = "SELECT id, first_name, last_name 
                                 FROM users 
                                 WHERE is_verified = 1 
                                 ORDER BY id ASC 
                                 LIMIT 1";
                $ultimateStmt = $db->prepare($ultimateQuery);
                $ultimateStmt->execute();
                $ultimateUser = $ultimateStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($ultimateUser) {
                    $userId = $ultimateUser['id'];
                    $userName = $ultimateUser['first_name'] . ' ' . $ultimateUser['last_name'];
                    $detectionMethod = 'emergency_fallback';
                    logMessage("✓ Using emergency fallback: {$userName} (ID: {$userId})");
                } else {
                    throw new Exception("No verified users found in database!");
                }
            }
        }
    }
    
    // Sanitize data
    $ph_level = floatval($data['ph_level']);
    $tds_value = floatval($data['tds_value']);
    $ec_value = floatval($data['ec_value']);
    $turbidity = floatval($data['turbidity']);
    $temperature = floatval($data['temperature']);
    
    // Validate ranges
    if ($ph_level < 0 || $ph_level > 14) $ph_level = 0;
    if ($tds_value < 0 || $tds_value > 10000) $tds_value = 0;
    if ($ec_value < 0 || $ec_value > 10000) $ec_value = 0;
    if ($turbidity < 0 || $turbidity > 1000) $turbidity = 0;
    if ($temperature < -50 || $temperature > 100) $temperature = 0;
    
    logMessage("✓ Data validated - pH:{$ph_level} TDS:{$tds_value} EC:{$ec_value} Turb:{$turbidity} Temp:{$temperature}");
    
    // Insert into database
    $insertQuery = "INSERT INTO sensor_readings 
                    (device_id, user_id, ph_level, tds_value, ec_value, turbidity, temperature, reading_timestamp) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $insertStmt = $db->prepare($insertQuery);
    $result = $insertStmt->execute([
        $deviceId,
        $userId,
        $ph_level,
        $tds_value,
        $ec_value,
        $turbidity,
        $temperature
    ]);
    
    if ($result) {
        $insertedId = $db->lastInsertId();
        
        logMessage("✓✓✓ SUCCESS - Record ID: {$insertedId} assigned to {$userName} (ID: {$userId})");
        logMessage("---");
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Data stored successfully',
            'id' => $insertedId,
            'device_id' => $deviceId,
            'user_id' => $userId,
            'user_name' => $userName,
            'detection_method' => $detectionMethod,
            'device_sessions_table_exists' => $tableExists ? true : false,
            'data' => [
                'ph_level' => $ph_level,
                'tds_value' => $tds_value,
                'ec_value' => $ec_value,
                'turbidity' => $turbidity,
                'temperature' => $temperature
            ]
        ]);
    } else {
        throw new Exception("Failed to insert data into database");
    }
    
} catch (Exception $e) {
    logMessage("✗ ERROR: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    logMessage("---");
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>