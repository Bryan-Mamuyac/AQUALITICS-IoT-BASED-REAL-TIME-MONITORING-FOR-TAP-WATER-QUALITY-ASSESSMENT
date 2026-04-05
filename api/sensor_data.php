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
$action = $_GET['action'] ?? 'latest';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    switch ($action) {
        case 'latest':
            handleLatestRequest($db, $userId);
            break;
            
        case 'chart':
            handleChartRequest($db, $userId);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

function handleLatestRequest($db, $userId) {
    // Get the average of all sensor readings for this user
    // This will show data regardless of connection status
    $query = "SELECT 
                AVG(ph_level) as ph_level,
                AVG(tds_value) as tds_value,
                AVG(ec_value) as ec_value,
                AVG(turbidity) as turbidity,
                AVG(temperature) as temperature,
                MAX(reading_timestamp) as reading_timestamp,
                COUNT(*) as total_readings
              FROM sensor_readings 
              WHERE user_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$userId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($data && $data['total_readings'] > 0) {
        // Round the averages to 2 decimal places
        $data['ph_level'] = round($data['ph_level'], 2);
        $data['tds_value'] = round($data['tds_value'], 2);
        $data['ec_value'] = round($data['ec_value'], 2);
        $data['turbidity'] = round($data['turbidity'], 2);
        $data['temperature'] = round($data['temperature'], 2);
        
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        // No data available - return zeros
        echo json_encode([
            'success' => true, 
            'data' => [
                'ph_level' => 0,
                'tds_value' => 0,
                'ec_value' => 0,
                'turbidity' => 0,
                'temperature' => 0,
                'reading_timestamp' => null,
                'total_readings' => 0
            ],
            'message' => 'No data available yet'
        ]);
    }
}

function handleChartRequest($db, $userId) {
    $range = $_GET['range'] ?? 'all';
    
    // Determine time range
    $whereClause = '';
    switch ($range) {
        case '24h':
            $whereClause = "AND reading_timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            break;
        case '7d':
            $whereClause = "AND reading_timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case '30d':
            $whereClause = "AND reading_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case 'all':
        default:
            $whereClause = ""; // No time restriction - show all data
            break;
    }
    
    // Get chart data
    $query = "SELECT 
                reading_timestamp,
                ph_level,
                tds_value,
                ec_value,
                turbidity,
                temperature
              FROM sensor_readings 
              WHERE user_id = ? $whereClause
              ORDER BY reading_timestamp ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$userId]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($data) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        // If no data in the specified range, get the latest few records
        $fallbackQuery = "SELECT 
                            reading_timestamp,
                            ph_level,
                            tds_value,
                            ec_value,
                            turbidity,
                            temperature
                          FROM sensor_readings 
                          WHERE user_id = ?
                          ORDER BY reading_timestamp DESC
                          LIMIT 10";
        
        $fallbackStmt = $db->prepare($fallbackQuery);
        $fallbackStmt->execute([$userId]);
        $fallbackData = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($fallbackData) {
            // Reverse the order to show chronologically
            $fallbackData = array_reverse($fallbackData);
            echo json_encode(['success' => true, 'data' => $fallbackData]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No chart data available']);
        }
    }
}
?>