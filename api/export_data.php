<?php
session_start();
require_once '../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Authentication required');
}

$range = $_GET['range'] ?? 'all';
$format = $_GET['format'] ?? 'csv';
$userId = $_SESSION['user_id']; // This is the key missing piece

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Build the WHERE clause for time range
    $whereClause = "WHERE sr.user_id = ?"; // Filter by user ID first
    $params = [$userId];
    
    switch ($range) {
        case '24h':
            $whereClause .= " AND sr.reading_timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            break;
        case '7d':
            $whereClause .= " AND sr.reading_timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case '30d':
            $whereClause .= " AND sr.reading_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case 'all':
        default:
            // No additional time filter - just user filter
            break;
    }
    
    // Updated query to remove device and location data - just get sensor readings
    $query = "SELECT 
                sr.reading_timestamp,
                sr.ph_level,
                sr.tds_value,
                sr.ec_value,
                sr.turbidity,
                sr.temperature
             FROM sensor_readings sr 
             $whereClause
             ORDER BY sr.reading_timestamp DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log the number of records found
    error_log("Export: Found " . count($data) . " records for user ID: " . $userId);
    
    if (empty($data)) {
        // If no data found, return an error response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'No data available to export for the selected time range.'
        ]);
        exit;
    }
    
    if ($format === 'csv') {
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="aqualitics_data_' . date('Y-m-d_H-i-s') . '.csv"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for proper UTF-8 encoding in Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Updated CSV headers - removed Device Name and Location
        fputcsv($output, [
            'Timestamp',
            'pH Level',
            'TDS (ppm)',
            'EC (μS/cm)',
            'Turbidity (NTU)',
            'Temperature (°C)'
        ]);
        
        // Updated CSV data - removed device_name and location
        foreach ($data as $row) {
            fputcsv($output, [
                $row['reading_timestamp'],
                number_format($row['ph_level'], 2),
                number_format($row['tds_value'], 2),
                number_format($row['ec_value'], 2),
                number_format($row['turbidity'], 2),
                number_format($row['temperature'], 2)
            ]);
        }
        
        fclose($output);
        
    } elseif ($format === 'json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="aqualitics_data_' . date('Y-m-d_H-i-s') . '.json"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        
        echo json_encode([
            'exported_at' => date('Y-m-d H:i:s'),
            'total_records' => count($data),
            'time_range' => $range,
            'data' => $data
        ], JSON_PRETTY_PRINT);
    }
    
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    
    // Return JSON error response instead of plain text
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Export failed: ' . $e->getMessage()
    ]);
}
?>