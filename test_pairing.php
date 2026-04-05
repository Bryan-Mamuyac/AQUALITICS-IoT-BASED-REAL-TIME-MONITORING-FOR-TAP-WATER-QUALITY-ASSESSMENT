<?php
/**
 * test_pairing.php
 * Test script to verify device_pairing.php works
 * Place in: /Aqualitics_Official/test_pairing.php
 */

require_once 'config/database.php';

echo "<h1>Device Pairing API Test</h1>";
echo "<pre>";

// Test 1: Check database connection
echo "\n=== TEST 1: Database Connection ===\n";
try {
    $database = new Database();
    $db = $database->getConnection();
    echo "✓ Database connected successfully\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    exit;
}

// Test 2: Check if device exists
echo "\n=== TEST 2: Device Registration ===\n";
$deviceQuery = "SELECT * FROM iot_devices WHERE device_id = 'AQUA_001'";
$stmt = $db->prepare($deviceQuery);
$stmt->execute();
$device = $stmt->fetch(PDO::FETCH_ASSOC);

if ($device) {
    echo "✓ Device AQUA_001 found in database\n";
    echo "  - ID: " . $device['id'] . "\n";
    echo "  - Name: " . $device['device_name'] . "\n";
    echo "  - Status: " . $device['status'] . "\n";
} else {
    echo "✗ Device AQUA_001 NOT FOUND!\n";
    echo "  Run this SQL to add it:\n";
    echo "  INSERT INTO iot_devices (device_name, device_id, location, status)\n";
    echo "  VALUES ('Aqualitics Main Device', 'AQUA_001', 'San Fernando, La Union', 'active');\n";
    exit;
}

// Test 3: Check device_sessions table structure
echo "\n=== TEST 3: Device Sessions Table ===\n";
try {
    $checkTable = "DESCRIBE device_sessions";
    $stmt = $db->prepare($checkTable);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasHeartbeat = false;
    foreach ($columns as $col) {
        if ($col['Field'] == 'last_heartbeat') {
            $hasHeartbeat = true;
            break;
        }
    }
    
    if ($hasHeartbeat) {
        echo "✓ last_heartbeat column exists\n";
    } else {
        echo "✗ last_heartbeat column MISSING!\n";
        echo "  Run this SQL:\n";
        echo "  ALTER TABLE device_sessions ADD COLUMN last_heartbeat TIMESTAMP NULL DEFAULT NULL AFTER ended_at;\n";
        exit;
    }
} catch (Exception $e) {
    echo "✗ Error checking table: " . $e->getMessage() . "\n";
    exit;
}

// Test 4: Simulate Arduino heartbeat
echo "\n=== TEST 4: Simulate Arduino Heartbeat ===\n";
$deviceId = $device['id'];

// Check for existing session
$checkSession = "SELECT * FROM device_sessions WHERE device_id = ? ORDER BY started_at DESC LIMIT 1";
$stmt = $db->prepare($checkSession);
$stmt->execute([$deviceId]);
$existingSession = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existingSession) {
    echo "Found existing session (ID: " . $existingSession['id'] . ")\n";
    echo "Updating heartbeat...\n";
    
    $updateHeartbeat = "UPDATE device_sessions SET last_heartbeat = NOW() WHERE id = ?";
    $stmt = $db->prepare($updateHeartbeat);
    $stmt->execute([$existingSession['id']]);
    
    echo "✓ Heartbeat updated\n";
} else {
    echo "No existing session found\n";
    echo "Creating tracking session...\n";
    
    $createSession = "INSERT INTO device_sessions (device_id, user_id, started_at, last_heartbeat, ended_at, is_active) 
                     VALUES (?, 0, NOW(), NOW(), NOW(), 0)";
    $stmt = $db->prepare($createSession);
    $stmt->execute([$deviceId]);
    
    echo "✓ Tracking session created\n";
}

// Test 5: Check if device appears online
echo "\n=== TEST 5: Online Status Check ===\n";
$onlineCheck = "SELECT last_heartbeat,
                TIMESTAMPDIFF(SECOND, last_heartbeat, NOW()) as seconds_ago
                FROM device_sessions
                WHERE device_id = ? 
                AND last_heartbeat IS NOT NULL
                AND TIMESTAMPDIFF(SECOND, last_heartbeat, NOW()) <= 45
                ORDER BY last_heartbeat DESC
                LIMIT 1";

$stmt = $db->prepare($onlineCheck);
$stmt->execute([$deviceId]);
$online = $stmt->fetch(PDO::FETCH_ASSOC);

if ($online) {
    echo "✓ Device appears ONLINE\n";
    echo "  - Last heartbeat: " . $online['last_heartbeat'] . "\n";
    echo "  - Seconds ago: " . $online['seconds_ago'] . "\n";
} else {
    echo "✗ Device appears OFFLINE\n";
    echo "  This means the heartbeat check failed\n";
}

// Test 6: Test the API endpoint directly
echo "\n=== TEST 6: API Endpoint Test ===\n";
$apiUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/api/device_pairing.php?action=check_pairing&device_id=AQUA_001';
echo "Testing URL: $apiUrl\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response:\n";
echo $response . "\n";

if ($httpCode == 200 && $response) {
    $json = json_decode($response, true);
    if ($json && isset($json['paired'])) {
        echo "✓ API returned valid JSON\n";
        echo "  - Success: " . ($json['success'] ? 'true' : 'false') . "\n";
        echo "  - Paired: " . ($json['paired'] ? 'true' : 'false') . "\n";
        echo "  - Message: " . $json['message'] . "\n";
    } else {
        echo "✗ API returned invalid JSON\n";
    }
} else {
    echo "✗ API request failed\n";
}

echo "\n=== ALL TESTS COMPLETE ===\n";
echo "\nIf all tests pass, your Arduino should be able to:\n";
echo "1. Send heartbeat to the API\n";
echo "2. Appear as ONLINE on the website\n";
echo "3. Receive pairing status\n";
echo "\nNext step: Upload Arduino code and check Serial Monitor\n";

echo "</pre>";
?>