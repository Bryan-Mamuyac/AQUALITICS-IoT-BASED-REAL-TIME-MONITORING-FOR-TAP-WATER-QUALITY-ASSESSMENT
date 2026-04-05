<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$userId = $_SESSION['user_id'];
$file = $_FILES['file'];
$filename = $file['tmp_name'];
$originalName = $file['name'];
$fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get default device ID
    $deviceQuery = "SELECT id FROM iot_devices WHERE device_id = 'AQUA_001' LIMIT 1";
    $deviceStmt = $db->prepare($deviceQuery);
    $deviceStmt->execute();
    $device = $deviceStmt->fetch(PDO::FETCH_ASSOC);
    $deviceId = $device ? $device['id'] : 1;
    
    $data = [];
    $importedCount = 0;
    $errorCount = 0;
    $errors = [];
    
    if ($fileExtension === 'csv') {
        $data = parseCSV($filename);
    } elseif (in_array($fileExtension, ['xlsx', 'xls'])) {
        $data = parseExcel($filename);
    } else {
        echo json_encode(['success' => false, 'message' => 'Unsupported file format. Please use CSV, XLS, or XLSX files.']);
        exit;
    }
    
    if (empty($data)) {
        echo json_encode(['success' => false, 'message' => 'No valid data found in file']);
        exit;
    }
    
    // Prepare insert statement
    $insertQuery = "INSERT INTO sensor_readings (device_id, user_id, ph_level, tds_value, ec_value, turbidity, temperature, reading_timestamp) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $insertStmt = $db->prepare($insertQuery);
    
    // Begin transaction
    $db->beginTransaction();
    
    foreach ($data as $rowIndex => $row) {
        try {
            // Validate required fields
            $ph_level = validateFloat($row['ph_level'] ?? '', 'pH Level', 0, 14);
            $tds_value = validateFloat($row['tds_value'] ?? '', 'TDS Value', 0);
            $ec_value = validateFloat($row['ec_value'] ?? '', 'EC Value', 0);
            $turbidity = validateFloat($row['turbidity'] ?? '', 'Turbidity', 0);
            $temperature = validateFloat($row['temperature'] ?? '', 'Temperature');
            $reading_timestamp = validateTimestamp($row['reading_timestamp'] ?? '');
            
            // Insert record
            $result = $insertStmt->execute([
                $deviceId, 
                $userId, 
                $ph_level, 
                $tds_value, 
                $ec_value, 
                $turbidity, 
                $temperature, 
                $reading_timestamp
            ]);
            
            if ($result) {
                $importedCount++;
            } else {
                $errorCount++;
                $errors[] = "Row " . ($rowIndex + 2) . ": Failed to insert record";
            }
            
        } catch (Exception $e) {
            $errorCount++;
            $errors[] = "Row " . ($rowIndex + 2) . ": " . $e->getMessage();
        }
    }
    
    // Commit transaction
    $db->commit();
    
    $message = "Import completed. $importedCount records imported";
    if ($errorCount > 0) {
        $message .= ", $errorCount errors occurred";
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'imported' => $importedCount,
        'errors' => $errorCount,
        'error_details' => array_slice($errors, 0, 10) // Limit error details to first 10
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Import failed: ' . $e->getMessage()]);
}

function parseCSV($filename) {
    $data = [];
    $headers = [];
    
    if (($handle = fopen($filename, "r")) !== FALSE) {
        $rowIndex = 0;
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if ($rowIndex === 0) {
                // Process headers - normalize and map common variations
                $headers = array_map('normalizeHeader', $row);
            } else {
                if (count($row) === count($headers)) {
                    $data[] = array_combine($headers, $row);
                }
            }
            $rowIndex++;
        }
        fclose($handle);
    }
    
    return $data;
}

function parseExcel($filename) {
    // For Excel parsing, you would typically use a library like PhpSpreadsheet
    // For now, we'll provide a basic implementation that requires the user to convert to CSV
    // In a production environment, you should install PhpSpreadsheet via Composer
    
    // Basic Excel to CSV conversion using a simple approach
    // This is a simplified version - in production, use PhpSpreadsheet
    throw new Exception('Excel import requires PhpSpreadsheet library. Please convert your Excel file to CSV format for now.');
}

function normalizeHeader($header) {
    $header = strtolower(trim($header));
    
    // Map common header variations to our standard field names
    $headerMap = [
        'ph' => 'ph_level',
        'ph_level' => 'ph_level',
        'ph level' => 'ph_level',
        'ph value' => 'ph_level',
        'tds' => 'tds_value',
        'tds_value' => 'tds_value',
        'tds value' => 'tds_value',
        'tds (ppm)' => 'tds_value',
        'tds ppm' => 'tds_value',
        'ec' => 'ec_value',
        'ec_value' => 'ec_value',
        'ec value' => 'ec_value',
        'ec (μs/cm)' => 'ec_value',
        'ec μs/cm' => 'ec_value',
        'electrical conductivity' => 'ec_value',
        'turbidity' => 'turbidity',
        'turbidity (ntu)' => 'turbidity',
        'turbidity ntu' => 'turbidity',
        'temperature' => 'temperature',
        'temp' => 'temperature',
        'temperature (°c)' => 'temperature',
        'temperature °c' => 'temperature',
        'timestamp' => 'reading_timestamp',
        'reading_timestamp' => 'reading_timestamp',
        'reading timestamp' => 'reading_timestamp',
        'date' => 'reading_timestamp',
        'datetime' => 'reading_timestamp',
        'date time' => 'reading_timestamp',
        'time' => 'reading_timestamp'
    ];
    
    return $headerMap[$header] ?? $header;
}

function validateFloat($value, $fieldName, $min = null, $max = null) {
    $value = trim($value);
    
    if ($value === '' || $value === null) {
        throw new Exception("$fieldName is required");
    }
    
    if (!is_numeric($value)) {
        throw new Exception("$fieldName must be a valid number");
    }
    
    $floatValue = floatval($value);
    
    if ($min !== null && $floatValue < $min) {
        throw new Exception("$fieldName must be at least $min");
    }
    
    if ($max !== null && $floatValue > $max) {
        throw new Exception("$fieldName must not exceed $max");
    }
    
    return $floatValue;
}

function validateTimestamp($value) {
    $value = trim($value);
    
    if ($value === '' || $value === null) {
        // If no timestamp provided, use current time
        return date('Y-m-d H:i:s');
    }
    
    // Try to parse various timestamp formats
    $timestamp = false;
    
    // Common formats to try
    $formats = [
        'Y-m-d H:i:s',
        'Y/m/d H:i:s',
        'Y-m-d H:i',
        'Y/m/d H:i',
        'd/m/Y H:i:s',
        'd/m/Y H:i',
        'm/d/Y H:i:s',
        'm/d/Y H:i',
        'Y-m-d',
        'Y/m/d',
        'd/m/Y',
        'm/d/Y'
    ];
    
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date !== false) {
            $timestamp = $date->format('Y-m-d H:i:s');
            break;
        }
    }
    
    // Try strtotime as fallback
    if ($timestamp === false) {
        $time = strtotime($value);
        if ($time !== false) {
            $timestamp = date('Y-m-d H:i:s', $time);
        }
    }
    
    if ($timestamp === false) {
        throw new Exception("Invalid timestamp format: $value");
    }
    
    return $timestamp;
}
?>