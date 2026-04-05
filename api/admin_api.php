<?php
session_start();
require_once '../config/database.php';
header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    switch ($action) {
        case 'stats':
            $stats = [];
            
            // Total clients
            $clientQuery = "SELECT COUNT(*) as count FROM users WHERE role = 'client'";
            $clientStmt = $db->prepare($clientQuery);
            $clientStmt->execute();
            $stats['total_clients'] = $clientStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Total readings
            $totalReadingQuery = "SELECT COUNT(*) as count FROM sensor_readings";
            $totalReadingStmt = $db->prepare($totalReadingQuery);
            $totalReadingStmt->execute();
            $stats['total_readings'] = $totalReadingStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;
            
        case 'clients':
            $query = "SELECT u.*, 
                     (SELECT COUNT(*) FROM sensor_readings sr WHERE sr.user_id = u.id) as total_readings
                     FROM users u 
                     WHERE u.role = 'client' 
                     ORDER BY u.created_at DESC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'clients' => $clients]);
            break;
            
        case 'clients_with_data':
            $query = "SELECT u.*, 
                     (SELECT COUNT(*) FROM sensor_readings sr WHERE sr.user_id = u.id) as total_readings,
                     (SELECT MAX(sr.reading_timestamp) FROM sensor_readings sr WHERE sr.user_id = u.id) as last_reading
                     FROM users u 
                     WHERE u.role = 'client' 
                     ORDER BY u.created_at DESC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'clients' => $clients]);
            break;
            
        case 'client_data':
            $clientId = $_GET['client_id'] ?? 'all';
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = max(1, min(100, intval($_GET['limit'] ?? 10)));
            $search = $_GET['search'] ?? '';
            $offset = ($page - 1) * $limit;
            
            // Build base query
            $baseQuery = "FROM sensor_readings sr 
                         LEFT JOIN users u ON sr.user_id = u.id 
                         WHERE 1=1";
            $params = [];
            
            // Filter by client if specified
            if ($clientId !== 'all') {
                $baseQuery .= " AND sr.user_id = :client_id";
                $params[':client_id'] = $clientId;
            }
            
            // Search functionality
            if (!empty($search)) {
                $baseQuery .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search)";
                $params[':search'] = "%$search%";
            }
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total " . $baseQuery;
            $countStmt = $db->prepare($countQuery);
            $countStmt->execute($params);
            $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get data with pagination
            $dataQuery = "SELECT sr.*, 
                         CONCAT(u.first_name, ' ', u.last_name) as client_name,
                         u.email as client_email
                         " . $baseQuery . " 
                         ORDER BY sr.reading_timestamp DESC 
                         LIMIT :limit OFFSET :offset";
            
            $dataStmt = $db->prepare($dataQuery);
            foreach ($params as $key => $value) {
                $dataStmt->bindValue($key, $value);
            }
            $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $dataStmt->execute();
            $data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate statistics
            $statsQuery = "SELECT 
                          COUNT(*) as total_readings,
                          AVG(ph_level) as avg_ph,
                          AVG(tds_value) as avg_tds,
                          AVG(ec_value) as avg_ec,
                          AVG(turbidity) as avg_turbidity,
                          MIN(reading_timestamp) as first_reading,
                          MAX(reading_timestamp) as last_reading,
                          DATEDIFF(MAX(reading_timestamp), MIN(reading_timestamp)) + 1 as date_range_days
                          " . $baseQuery;
            $statsStmt = $db->prepare($statsQuery);
            $statsStmt->execute($params);
            $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current' => $page,
                    'total' => $totalRecords,
                    'pages' => ceil($totalRecords / $limit),
                    'limit' => $limit
                ],
                'stats' => $stats
            ]);
            break;
            
        case 'client_chart_data':
            $clientId = $_GET['client_id'] ?? 'all';
            $range = $_GET['range'] ?? 'all';
            
            // Build date filter
            $dateFilter = "";
            $params = [];
            
            switch ($range) {
                case '24h':
                    $dateFilter = "AND sr.reading_timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
                    break;
                case '7d':
                    $dateFilter = "AND sr.reading_timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case '30d':
                    $dateFilter = "AND sr.reading_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
            }
            
            $query = "SELECT sr.* FROM sensor_readings sr WHERE 1=1 $dateFilter";
            
            if ($clientId !== 'all') {
                $query .= " AND sr.user_id = :client_id";
                $params[':client_id'] = $clientId;
            }
            
            $query .= " ORDER BY sr.reading_timestamp ASC";
            if ($range === 'all') {
                $query .= " LIMIT 100"; // Limit to last 100 readings for performance
            }
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'export_client_data':
            $clientId = $_GET['client_id'] ?? 'all';
            $range = $_GET['range'] ?? 'all';
            
            // Build date filter
            $dateFilter = "";
            $params = [];
            
            switch ($range) {
                case '24h':
                    $dateFilter = "AND sr.reading_timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
                    break;
                case '7d':
                    $dateFilter = "AND sr.reading_timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case '30d':
                    $dateFilter = "AND sr.reading_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
            }
            
            $query = "SELECT sr.id, sr.reading_timestamp, sr.ph_level, sr.tds_value, 
                     sr.ec_value, sr.turbidity, sr.temperature,
                     CONCAT(u.first_name, ' ', u.last_name) as client_name,
                     u.email as client_email
                     FROM sensor_readings sr 
                     LEFT JOIN users u ON sr.user_id = u.id 
                     WHERE 1=1 $dateFilter";
            
            if ($clientId !== 'all') {
                $query .= " AND sr.user_id = :client_id";
                $params[':client_id'] = $clientId;
            }
            
            $query .= " ORDER BY sr.reading_timestamp DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($data)) {
                echo json_encode(['success' => false, 'message' => 'No data found for export']);
                break;
            }
            
            // Set CSV headers
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="aqualitics_data_export.csv"');
            
            // Create CSV content
            $output = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($output, [
                'Reading ID', 'Timestamp', 'Client Name', 'Client Email', 
                'pH Level', 'TDS (ppm)', 'EC (μS/cm)', 'Turbidity (NTU)', 'Temperature (°C)'
            ]);
            
            // CSV data
            foreach ($data as $row) {
                fputcsv($output, [
                    $row['id'],
                    $row['reading_timestamp'],
                    $row['client_name'],
                    $row['client_email'],
                    $row['ph_level'],
                    $row['tds_value'],
                    $row['ec_value'],
                    $row['turbidity'],
                    $row['temperature']
                ]);
            }
            
            fclose($output);
            exit;
            
        case 'client_details':
            $clientId = $_GET['id'] ?? 0;
            
            // Get client info
            $clientQuery = "SELECT * FROM users WHERE id = :id AND role = 'client'";
            $clientStmt = $db->prepare($clientQuery);
            $clientStmt->bindValue(':id', $clientId, PDO::PARAM_INT);
            $clientStmt->execute();
            $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$client) {
                echo json_encode(['success' => false, 'message' => 'Client not found']);
                break;
            }
            
            // Get recent readings
            $readingsQuery = "SELECT * FROM sensor_readings 
                             WHERE user_id = :user_id 
                             ORDER BY reading_timestamp DESC 
                             LIMIT 10";
            $readingsStmt = $db->prepare($readingsQuery);
            $readingsStmt->bindValue(':user_id', $clientId, PDO::PARAM_INT);
            $readingsStmt->execute();
            $readings = $readingsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true, 
                'client' => $client, 
                'readings' => $readings
            ]);
            break;
            
        case 'delete_client':
            $input = json_decode(file_get_contents('php://input'), true);
            $clientId = $input['client_id'] ?? 0;
            
            if (!$clientId) {
                echo json_encode(['success' => false, 'message' => 'Client ID required']);
                break;
            }
            
            // Start transaction
            $db->beginTransaction();
            
            try {
                // Delete sensor readings first (foreign key constraint)
                $deleteReadingsQuery = "DELETE FROM sensor_readings WHERE user_id = :user_id";
                $deleteReadingsStmt = $db->prepare($deleteReadingsQuery);
                $deleteReadingsStmt->bindValue(':user_id', $clientId, PDO::PARAM_INT);
                $deleteReadingsStmt->execute();
                
                // Delete client
                $deleteClientQuery = "DELETE FROM users WHERE id = :id AND role = 'client'";
                $deleteClientStmt = $db->prepare($deleteClientQuery);
                $deleteClientStmt->bindValue(':id', $clientId, PDO::PARAM_INT);
                $deleteClientStmt->execute();
                
                if ($deleteClientStmt->rowCount() > 0) {
                    $db->commit();
                    echo json_encode(['success' => true, 'message' => 'Client deleted successfully']);
                } else {
                    $db->rollback();
                    echo json_encode(['success' => false, 'message' => 'Client not found or not deleted']);
                }
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;
            
        case 'chart_data':
            // Get data for the last 7 days
            $chartQuery = "SELECT 
                          DATE(reading_timestamp) as date,
                          COUNT(*) as readings_count
                          FROM sensor_readings 
                          WHERE reading_timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                          GROUP BY DATE(reading_timestamp)
                          ORDER BY date ASC";
            $chartStmt = $db->prepare($chartQuery);
            $chartStmt->execute();
            $readingsData = $chartStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get new users data
            $usersQuery = "SELECT 
                          DATE(created_at) as date,
                          COUNT(*) as users_count
                          FROM users 
                          WHERE role = 'client' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                          GROUP BY DATE(created_at)
                          ORDER BY date ASC";
            $usersStmt = $db->prepare($usersQuery);
            $usersStmt->execute();
            $usersData = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Create arrays for the last 7 days
            $labels = [];
            $readings = [];
            $users = [];
            
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $labels[] = date('M d', strtotime($date));
                
                // Find readings for this date
                $dayReadings = 0;
                foreach ($readingsData as $reading) {
                    if ($reading['date'] === $date) {
                        $dayReadings = $reading['readings_count'];
                        break;
                    }
                }
                $readings[] = $dayReadings;
                
                // Find users for this date
                $dayUsers = 0;
                foreach ($usersData as $user) {
                    if ($user['date'] === $date) {
                        $dayUsers = $user['users_count'];
                        break;
                    }
                }
                $users[] = $dayUsers;
            }
            
            echo json_encode([
                'success' => true,
                'chart_data' => [
                    'labels' => $labels,
                    'readings' => $readings,
                    'users' => $users
                ]
            ]);
            break;
            
        case 'recent_activity':
            $activities = [];
            
            // Recent readings
            $recentReadingsQuery = "SELECT sr.reading_timestamp, 
                                   CONCAT(u.first_name, ' ', u.last_name) as client_name
                                   FROM sensor_readings sr
                                   LEFT JOIN users u ON sr.user_id = u.id
                                   ORDER BY sr.reading_timestamp DESC
                                   LIMIT 5";
            $recentReadingsStmt = $db->prepare($recentReadingsQuery);
            $recentReadingsStmt->execute();
            $recentReadings = $recentReadingsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($recentReadings as $reading) {
                $activities[] = [
                    'description' => "New reading from " . ($reading['client_name'] ?? 'Unknown'),
                    'timestamp' => $reading['reading_timestamp']
                ];
            }
            
            // Recent user registrations
            $recentUsersQuery = "SELECT created_at, 
                                CONCAT(first_name, ' ', last_name) as client_name
                                FROM users 
                                WHERE role = 'client'
                                ORDER BY created_at DESC
                                LIMIT 3";
            $recentUsersStmt = $db->prepare($recentUsersQuery);
            $recentUsersStmt->execute();
            $recentUsers = $recentUsersStmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($recentUsers as $user) {
                $activities[] = [
                    'description' => "New client registered: " . $user['client_name'],
                    'timestamp' => $user['created_at']
                ];
            }
            
            // Sort activities by timestamp
            usort($activities, function($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });
            
            // Limit to 10 activities
            $activities = array_slice($activities, 0, 10);
            
            echo json_encode(['success' => true, 'activities' => $activities]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>