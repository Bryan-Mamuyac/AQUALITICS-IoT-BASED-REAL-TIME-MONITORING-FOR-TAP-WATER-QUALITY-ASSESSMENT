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

$method = $_SERVER['REQUEST_METHOD'];
$userId = $_SESSION['user_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    switch ($method) {
        case 'GET':
            handleGetRequest($db, $userId);
            break;
            
        case 'POST':
            handlePostRequest($db, $userId);
            break;
            
        case 'PUT':
            handlePutRequest($db, $userId);
            break;
            
        case 'DELETE':
            handleDeleteRequest($db, $userId);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

function handleGetRequest($db, $userId) {
    $action = $_GET['action'] ?? 'list';
    
    if ($action === 'get' && isset($_GET['id'])) {
        // Get single record
        $id = intval($_GET['id']);
        $query = "SELECT * FROM sensor_readings WHERE id = ? AND user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$id, $userId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($data) {
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Record not found']);
        }
        return;
    }
    
    // Get paginated list
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 10);
    $search = $_GET['search'] ?? '';
    $offset = ($page - 1) * $limit;
    
    // Build search condition
    $searchCondition = '';
    $searchParams = [$userId];
    
    if (!empty($search)) {
        $searchCondition = " AND (
            ph_level LIKE ? OR 
            tds_value LIKE ? OR 
            ec_value LIKE ? OR 
            turbidity LIKE ? OR 
            temperature LIKE ? OR
            reading_timestamp LIKE ?
        )";
        $searchTerm = "%$search%";
        $searchParams = array_merge($searchParams, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM sensor_readings WHERE user_id = ? $searchCondition";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($searchParams);
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $limit);
    
    // Get data
    $dataQuery = "SELECT sr.*, d.device_name 
                  FROM sensor_readings sr 
                  LEFT JOIN iot_devices d ON sr.device_id = d.id 
                  WHERE sr.user_id = ? $searchCondition 
                  ORDER BY sr.reading_timestamp DESC 
                  LIMIT ? OFFSET ?";
    
    $dataParams = array_merge($searchParams, [$limit, $offset]);
    $dataStmt = $db->prepare($dataQuery);
    $dataStmt->execute($dataParams);
    $data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'pagination' => [
            'current' => $page,
            'total' => $totalRecords,
            'pages' => $totalPages,
            'limit' => $limit
        ]
    ]);
}

function handlePostRequest($db, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        return;
    }
    
    // Check if this is a delete all request
    if (isset($input['action']) && $input['action'] === 'delete_all') {
        handleDeleteAllRequest($db, $userId, $input);
        return;
    }
    
    // Validate required fields
    $required_fields = ['ph_level', 'tds_value', 'ec_value', 'turbidity', 'temperature', 'reading_timestamp'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            echo json_encode(['success' => false, 'message' => "Field '$field' is required"]);
            return;
        }
    }
    
    // Validate data types and ranges
    $ph_level = floatval($input['ph_level']);
    $tds_value = floatval($input['tds_value']);
    $ec_value = floatval($input['ec_value']);
    $turbidity = floatval($input['turbidity']);
    $temperature = floatval($input['temperature']);
    $reading_timestamp = $input['reading_timestamp'];
    
    // Basic validation
    if ($ph_level < 0 || $ph_level > 14) {
        echo json_encode(['success' => false, 'message' => 'pH level must be between 0 and 14']);
        return;
    }
    
    if ($tds_value < 0) {
        echo json_encode(['success' => false, 'message' => 'TDS value cannot be negative']);
        return;
    }
    
    if ($ec_value < 0) {
        echo json_encode(['success' => false, 'message' => 'EC value cannot be negative']);
        return;
    }
    
    if ($turbidity < 0) {
        echo json_encode(['success' => false, 'message' => 'Turbidity cannot be negative']);
        return;
    }
    
    // Get default device ID
    $deviceQuery = "SELECT id FROM iot_devices WHERE device_id = 'AQUA_001' LIMIT 1";
    $deviceStmt = $db->prepare($deviceQuery);
    $deviceStmt->execute();
    $device = $deviceStmt->fetch(PDO::FETCH_ASSOC);
    $deviceId = $device ? $device['id'] : 1;
    
    // Insert new record
    $insertQuery = "INSERT INTO sensor_readings (device_id, user_id, ph_level, tds_value, ec_value, turbidity, temperature, reading_timestamp) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $insertStmt = $db->prepare($insertQuery);
    $result = $insertStmt->execute([$deviceId, $userId, $ph_level, $tds_value, $ec_value, $turbidity, $temperature, $reading_timestamp]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Record added successfully', 'id' => $db->lastInsertId()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add record']);
    }
}

function handlePutRequest($db, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data or missing ID']);
        return;
    }
    
    $id = intval($input['id']);
    
    // Check if record exists and belongs to user
    $checkQuery = "SELECT id FROM sensor_readings WHERE id = ? AND user_id = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$id, $userId]);
    
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Record not found or access denied']);
        return;
    }
    
    // Validate required fields
    $required_fields = ['ph_level', 'tds_value', 'ec_value', 'turbidity', 'temperature', 'reading_timestamp'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            echo json_encode(['success' => false, 'message' => "Field '$field' is required"]);
            return;
        }
    }
    
    // Validate data types and ranges
    $ph_level = floatval($input['ph_level']);
    $tds_value = floatval($input['tds_value']);
    $ec_value = floatval($input['ec_value']);
    $turbidity = floatval($input['turbidity']);
    $temperature = floatval($input['temperature']);
    $reading_timestamp = $input['reading_timestamp'];
    
    // Basic validation
    if ($ph_level < 0 || $ph_level > 14) {
        echo json_encode(['success' => false, 'message' => 'pH level must be between 0 and 14']);
        return;
    }
    
    if ($tds_value < 0) {
        echo json_encode(['success' => false, 'message' => 'TDS value cannot be negative']);
        return;
    }
    
    if ($ec_value < 0) {
        echo json_encode(['success' => false, 'message' => 'EC value cannot be negative']);
        return;
    }
    
    if ($turbidity < 0) {
        echo json_encode(['success' => false, 'message' => 'Turbidity cannot be negative']);
        return;
    }
    
    // Update record
    $updateQuery = "UPDATE sensor_readings 
                    SET ph_level = ?, tds_value = ?, ec_value = ?, turbidity = ?, temperature = ?, reading_timestamp = ?
                    WHERE id = ? AND user_id = ?";
    $updateStmt = $db->prepare($updateQuery);
    $result = $updateStmt->execute([$ph_level, $tds_value, $ec_value, $turbidity, $temperature, $reading_timestamp, $id, $userId]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Record updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update record']);
    }
}

function handleDeleteRequest($db, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data or missing ID']);
        return;
    }
    
    $id = intval($input['id']);
    
    // Check if record exists and belongs to user
    $checkQuery = "SELECT id FROM sensor_readings WHERE id = ? AND user_id = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$id, $userId]);
    
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Record not found or access denied']);
        return;
    }
    
    // Delete record
    $deleteQuery = "DELETE FROM sensor_readings WHERE id = ? AND user_id = ?";
    $deleteStmt = $db->prepare($deleteQuery);
    $result = $deleteStmt->execute([$id, $userId]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Record deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete record']);
    }
}

function handleDeleteAllRequest($db, $userId, $input) {
    // Verify confirmation
    if (!isset($input['confirmed']) || $input['confirmed'] !== true) {
        echo json_encode(['success' => false, 'message' => 'Delete confirmation required']);
        return;
    }
    
    // Get count of records to be deleted
    $countQuery = "SELECT COUNT(*) as total FROM sensor_readings WHERE user_id = ?";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute([$userId]);
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($totalRecords == 0) {
        echo json_encode(['success' => false, 'message' => 'No records found to delete']);
        return;
    }
    
    // Delete all records for the user
    $deleteQuery = "DELETE FROM sensor_readings WHERE user_id = ?";
    $deleteStmt = $db->prepare($deleteQuery);
    $result = $deleteStmt->execute([$userId]);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => "All data deleted successfully. {$totalRecords} records removed.",
            'deleted_count' => $totalRecords
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete all records']);
    }
}
?>