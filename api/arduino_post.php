<?php
/**
 * AQUALITICS - Arduino Data Receiver API
 * Receives sensor data from Arduino via HTTP POST
 * 
 * Location: /api/arduino_post.php
 * 
 * SETUP INSTRUCTIONS:
 * 1. Place this file in: C:\XAMPP\htdocs\Aqualitics_Official_test\api\arduino_post.php
 * 2. Make sure database.php is in: C:\XAMPP\htdocs\Aqualitics_Official_test\config\database.php
 * 3. Test URL: http://192.168.100.6/Aqualitics_Official_test/api/arduino_post.php
 */

// Allow cross-origin requests (for testing)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Log file for debugging
$logFile = __DIR__ . '/../logs/arduino_posts.log';
$logDir = dirname($logFile);
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

// Function to log messages
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// Log incoming request
logMessage("=== NEW REQUEST ===");
logMessage("Method: " . $_SERVER['REQUEST_METHOD']);
logMessage("Remote IP: " . $_SERVER['REMOTE_ADDR']);
logMessage("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));

try {
    // Get raw POST data
    $rawData = file_get_contents('php://input');
    logMessage("Raw data: " . $rawData);
    
    if (empty($rawData)) {
        throw new Exception('No data received');
    }
    
    // Parse JSON data
    $data = json_decode($rawData, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    logMessage("Parsed JSON: " . print_r($data, true));
    
    // Validate API key
    $expectedApiKey = 'AQUA_SECURE_KEY_2024';  // Must match Arduino code
    if (!isset($data['api_key']) || $data['api_key'] !== $expectedApiKey) {
        throw new Exception('Invalid or missing API key');
    }
    
    // Validate device ID
    if (!isset($data['device_id']) || empty($data['device_id'])) {
        throw new Exception('Missing device_id');
    }
    
    // Validate required sensor fields
    $requiredFields = ['ph_level', 'temperature', 'tds_value', 'ec_value', 'turbidity'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Missing required field: {$field}");
        }
    }
    
    // Extract and validate sensor values
    $deviceId = $data['device_id'];
    $phLevel = floatval($data['ph_level']);
    $temperature = floatval($data['temperature']);
    $tdsValue = floatval($data['tds_value']);
    $ecValue = floatval($data['ec_value']);
    $turbidity = floatval($data['turbidity']);
    
    // Basic validation
    if ($phLevel < 0 || $phLevel > 14) {
        throw new Exception('pH level out of range (0-14)');
    }
    if ($tdsValue < 0 || $tdsValue > 10000) {
        throw new Exception('TDS value out of range');
    }
    if ($ecValue < 0 || $ecValue > 10000) {
        throw new Exception('EC value out of range');
    }
    if ($turbidity < 0 || $turbidity > 10000) {
        throw new Exception('Turbidity value out of range');
    }
    
    logMessage("Validation passed - connecting to database...");
    
    // Connect to database
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    // Get or create device record
    $deviceQuery = "SELECT id, user_id FROM iot_devices WHERE device_id = ? LIMIT 1";
    $deviceStmt = $db->prepare($deviceQuery);
    $deviceStmt->execute([$deviceId]);
    $device = $deviceStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$device) {
        // Create default device entry for testing
        // In production, devices should be pre-registered
        $insertDeviceQuery = "INSERT INTO iot_devices (device_id, device_name, location, status) 
                             VALUES (?, ?, ?, ?)";
        $insertDeviceStmt = $db->prepare($insertDeviceQuery);
        $insertDeviceStmt->execute([
            $deviceId,
            'Arduino Water Sensor',
            'San Fernando, La Union',
            'active'
        ]);
        
        $deviceDbId = $db->lastInsertId();
        
        // Get the admin user ID (user_id = 16 from your database screenshot)
        $userId = 16;  // Change this to match your user_id
        
        logMessage("Created new device entry: {$deviceId} (DB ID: {$deviceDbId})");
    } else {
        $deviceDbId = $device['id'];
        $userId = $device['user_id'] ?? 16;  // Use device's user_id or default to 16
        logMessage("Found existing device: {$deviceId} (DB ID: {$deviceDbId})");
    }
    
    // Insert sensor reading
    $insertQuery = "INSERT INTO sensor_readings 
                    (device_id, user_id, ph_level, tds_value, ec_value, turbidity, temperature, reading_timestamp) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $insertStmt = $db->prepare($insertQuery);
    $result = $insertStmt->execute([
        $deviceDbId,
        $userId,
        $phLevel,
        $tdsValue,
        $ecValue,
        $turbidity,
        $temperature
    ]);
    
    if ($result) {
        $insertedId = $db->lastInsertId();
        logMessage("Data inserted successfully! Record ID: {$insertedId}");
        
        $response = [
            'success' => true,
            'message' => 'Data received and stored successfully',
            'record_id' => $insertedId,
            'device_id' => $deviceId,
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => [
                'ph_level' => $phLevel,
                'temperature' => $temperature,
                'tds_value' => $tdsValue,
                'ec_value' => $ecValue,
                'turbidity' => $turbidity
            ]
        ];
        
        http_response_code(200);
        echo json_encode($response, JSON_PRETTY_PRINT);
        logMessage("Success response sent");
        
    } else {
        throw new Exception('Database insert failed');
    }
    
} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(400);
    echo json_encode($response, JSON_PRETTY_PRINT);
}

logMessage("=== END REQUEST ===\n");
?>