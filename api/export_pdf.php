<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get user information
    $userQuery = "SELECT first_name, last_name, email, created_at FROM users WHERE id = ?";
    $userStmt = $db->prepare($userQuery);
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        die('User not found');
    }
    
    // Get device count
    $deviceQuery = "SELECT COUNT(DISTINCT ud.device_id) as device_count FROM user_devices ud WHERE ud.user_id = ?";
    $deviceStmt = $db->prepare($deviceQuery);
    $deviceStmt->execute([$userId]);
    $deviceCount = $deviceStmt->fetchColumn();
    
    // Get total readings count
    $readingsCountQuery = "SELECT COUNT(*) as total_readings FROM sensor_readings WHERE user_id = ?";
    $readingsCountStmt = $db->prepare($readingsCountQuery);
    $readingsCountStmt->execute([$userId]);
    $totalReadings = $readingsCountStmt->fetchColumn();
    
    // Get sensor data statistics
    $statsQuery = "SELECT 
                    AVG(ph_level) as avg_ph,
                    MIN(ph_level) as min_ph,
                    MAX(ph_level) as max_ph,
                    AVG(tds_value) as avg_tds,
                    MIN(tds_value) as min_tds,
                    MAX(tds_value) as max_tds,
                    AVG(ec_value) as avg_ec,
                    MIN(ec_value) as min_ec,
                    MAX(ec_value) as max_ec,
                    AVG(turbidity) as avg_turbidity,
                    MIN(turbidity) as min_turbidity,
                    MAX(turbidity) as max_turbidity,
                    AVG(temperature) as avg_temp,
                    MIN(temperature) as min_temp,
                    MAX(temperature) as max_temp,
                    MIN(reading_timestamp) as first_reading,
                    MAX(reading_timestamp) as last_reading
                   FROM sensor_readings 
                   WHERE user_id = ?";
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->execute([$userId]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent readings (last 10)
    $recentQuery = "SELECT ph_level, tds_value, ec_value, turbidity, temperature, reading_timestamp 
                    FROM sensor_readings 
                    WHERE user_id = ? 
                    ORDER BY reading_timestamp DESC 
                    LIMIT 10";
    $recentStmt = $db->prepare($recentQuery);
    $recentStmt->execute([$userId]);
    $recentReadings = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get monthly trend data for chart
    $monthlyQuery = "SELECT 
                        DATE_FORMAT(reading_timestamp, '%Y-%m') as month,
                        AVG(ph_level) as avg_ph,
                        AVG(tds_value) as avg_tds,
                        AVG(turbidity) as avg_turbidity,
                        COUNT(*) as reading_count
                     FROM sensor_readings 
                     WHERE user_id = ? AND reading_timestamp >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                     GROUP BY DATE_FORMAT(reading_timestamp, '%Y-%m')
                     ORDER BY month ASC";
    $monthlyStmt = $db->prepare($monthlyQuery);
    $monthlyStmt->execute([$userId]);
    $monthlyData = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate days active
    $daysActive = max(1, floor((time() - strtotime($user['created_at'])) / (60 * 60 * 24)));
    
    // Generate PDF
    generateProfessionalPDF($user, $deviceCount, $totalReadings, $stats, $recentReadings, $monthlyData, $daysActive);
    
} catch (Exception $e) {
    die('Error generating PDF: ' . $e->getMessage());
}

function generateProfessionalPDF($user, $deviceCount, $totalReadings, $stats, $recentReadings, $monthlyData, $daysActive) {
    // Set proper headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Aqualitics-Water-Quality-Report-' . date('Y-m-d') . '.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Generate professional PDF
    $pdf = createProfessionalPDF($user, $deviceCount, $totalReadings, $stats, $recentReadings, $monthlyData, $daysActive);
    
    echo $pdf;
    exit;
}

function createProfessionalPDF($user, $deviceCount, $totalReadings, $stats, $recentReadings, $monthlyData, $daysActive) {
    $exportDate = date('F j, Y \a\t g:i A');
    $memberSince = date('F j, Y', strtotime($user['created_at']));
    
    // Build the complete PDF content
    $content = buildProfessionalPDFContent($user, $deviceCount, $totalReadings, $stats, $recentReadings, $monthlyData, $daysActive, $exportDate, $memberSince);
    
    // Calculate content length
    $contentLength = strlen($content);
    
    // Build complete PDF structure
    $pdf = "%PDF-1.7\n";
    
    // Document catalog
    $pdf .= "1 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n/PageLayout /SinglePage\n/PageMode /UseNone\n>>\nendobj\n";
    
    // Pages object
    $pdf .= "2 0 obj\n<<\n/Type /Pages\n/Kids [3 0 R]\n/Count 1\n>>\nendobj\n";
    
    // Page object
    $pdf .= "3 0 obj\n<<\n/Type /Page\n/Parent 2 0 R\n/MediaBox [0 0 595 842]\n/Contents 4 0 R\n/Resources <<\n/Font <<\n/F1 5 0 R\n/F2 6 0 R\n/F3 7 0 R\n>>\n>>\n>>\nendobj\n";
    
    // Content stream
    $pdf .= "4 0 obj\n<<\n/Length $contentLength\n>>\nstream\n$content\nendstream\nendobj\n";
    
    // Font objects
    $pdf .= "5 0 obj\n<<\n/Type /Font\n/Subtype /Type1\n/BaseFont /Helvetica\n>>\nendobj\n";
    $pdf .= "6 0 obj\n<<\n/Type /Font\n/Subtype /Type1\n/BaseFont /Helvetica-Bold\n>>\nendobj\n";
    $pdf .= "7 0 obj\n<<\n/Type /Font\n/Subtype /Type1\n/BaseFont /Helvetica-Oblique\n>>\nendobj\n";
    
    // Cross-reference table
    $xref_pos = strlen($pdf);
    $pdf .= "xref\n0 8\n";
    $pdf .= "0000000000 65535 f \n";
    $pdf .= sprintf("%010d", 9) . " 00000 n \n";
    $pdf .= sprintf("%010d", 88) . " 00000 n \n";
    $pdf .= sprintf("%010d", 145) . " 00000 n \n";
    $pdf .= sprintf("%010d", 280) . " 00000 n \n";
    $pdf .= sprintf("%010d", 340 + $contentLength) . " 00000 n \n";
    $pdf .= sprintf("%010d", 398 + $contentLength) . " 00000 n \n";
    $pdf .= sprintf("%010d", 461 + $contentLength) . " 00000 n \n";
    
    // Trailer
    $pdf .= "trailer\n<<\n/Size 8\n/Root 1 0 R\n>>\nstartxref\n$xref_pos\n%%EOF";
    
    return $pdf;
}

function buildProfessionalPDFContent($user, $deviceCount, $totalReadings, $stats, $recentReadings, $monthlyData, $daysActive, $exportDate, $memberSince) {
    $content = "q\n"; // Save graphics state
    
    // Set line width for borders
    $content .= "0.5 w\n";
    
    // HEADER SECTION - Enhanced Blue Header
    $content .= "0.2 0.4 0.8 rg\n"; // Professional blue
    $content .= "0 780 595 62 re f\n"; // Header rectangle
    
    // White border line
    $content .= "1 1 1 RG\n";
    $content .= "0 780 595 0 re S\n";
    $content .= "0 842 595 0 re S\n";
    
    // Company logo area (white rectangle)
    $content .= "1 1 1 rg\n";
    $content .= "15 792 50 38 re f\n";
    $content .= "0.8 0.8 0.8 RG\n";
    $content .= "15 792 50 38 re S\n";
    
    // Logo text - properly aligned
    $content .= "0.2 0.4 0.8 rg\n";
    $content .= "BT\n/F2 16 Tf\n22 815 Td\n(AQUA) Tj\nET\n";
    $content .= "BT\n/F2 10 Tf\n20 798 Td\n(LITICS) Tj\nET\n";
    
    // Main header text - white and properly spaced
    $content .= "1 1 1 rg\n";
    $content .= "BT\n/F2 24 Tf\n80 815 Td\n(AQUALITICS) Tj\nET\n";
    $content .= "BT\n/F1 14 Tf\n80 798 Td\n(Water Quality Analysis Report) Tj\nET\n";
    
    // Date in header - right aligned and properly positioned
    $content .= "BT\n/F1 9 Tf\n400 815 Td\n(Generated: $exportDate) Tj\nET\n";
    $content .= "BT\n/F3 8 Tf\n420 802 Td\n(Professional Analysis Report) Tj\nET\n";
    
    // Reset to black text
    $content .= "0.2 0.2 0.2 rg\n";
    
    // USER PROFILE SECTION - Better spacing
    $content .= "BT\n/F2 18 Tf\n30 755 Td\n(USER PROFILE) Tj\nET\n";
    
    // Profile container with better alignment
    $content .= "0.96 0.97 0.99 rg\n"; // Very light blue
    $content .= "30 685 535 65 re f\n";
    $content .= "0.8 0.85 0.95 RG\n"; // Light blue border
    $content .= "30 685 535 65 re S\n";
    
    // User information with improved spacing and alignment
    $content .= "0.2 0.2 0.2 rg\n";
    $content .= "BT\n/F2 12 Tf\n45 730 Td\n(Profile Information) Tj\nET\n";
    
    // First row - Name and Email properly spaced
    $content .= "BT\n/F2 10 Tf\n45 715 Td\n(Name:) Tj\nET\n";
    $content .= "BT\n/F1 10 Tf\n85 715 Td\n({$user['first_name']} {$user['last_name']}) Tj\nET\n";
    
    $content .= "BT\n/F2 10 Tf\n300 715 Td\n(Email:) Tj\nET\n";
    $content .= "BT\n/F1 10 Tf\n340 715 Td\n({$user['email']}) Tj\nET\n";
    
    // Second row - Member Since and Days Active properly aligned
    $content .= "BT\n/F2 10 Tf\n45 700 Td\n(Member Since:) Tj\nET\n";
    $content .= "BT\n/F1 10 Tf\n125 700 Td\n($memberSince) Tj\nET\n";
    $content .= "BT\n/F2 10 Tf\n300 700 Td\n(Days Active:) Tj\nET\n";
    $content .= "BT\n/F1 10 Tf\n365 700 Td\n($daysActive days) Tj\nET\n";
    
    // ACCOUNT STATISTICS SECTION - Better spacing
    $content .= "BT\n/F2 18 Tf\n30 650 Td\n(ACCOUNT STATISTICS) Tj\nET\n";
    
    // Enhanced statistics cards with proper alignment and equal spacing
    $cardWidth = 150;
    $cardHeight = 50;
    $cardY = 575;
    $totalWidth = 535;
    $spacing = ($totalWidth - (3 * $cardWidth)) / 4; // Equal spacing between cards
    
    $statCards = [
        ['label' => 'Assigned\\nDevices', 'value' => $deviceCount, 'x' => 30 + $spacing, 'color' => '0.2 0.4 0.8'],
        ['label' => 'Total\\nReadings', 'value' => number_format($totalReadings), 'x' => 30 + $spacing + $cardWidth + $spacing, 'color' => '0.1 0.6 0.3'],
        ['label' => 'Days\\nActive', 'value' => $daysActive, 'x' => 30 + $spacing + (2 * $cardWidth) + (2 * $spacing), 'color' => '0.9 0.5 0.1']
    ];
    
    foreach ($statCards as $card) {
        // Shadow effect
        $content .= "0.8 0.8 0.8 rg\n";
        $content .= ($card['x'] + 2) . " " . ($cardY - 2) . " $cardWidth $cardHeight re f\n";
        
        // Main card
        $content .= $card['color'] . " rg\n";
        $content .= "{$card['x']} $cardY $cardWidth $cardHeight re f\n";
        
        // Card border
        $content .= "1 1 1 RG\n";
        $content .= "{$card['x']} $cardY $cardWidth $cardHeight re S\n";
        
        // Value (large text) - centered
        $content .= "1 1 1 rg\n";
        $valueX = $card['x'] + ($cardWidth / 2) - (strlen((string)$card['value']) * 6);
        $content .= "BT\n/F2 20 Tf\n$valueX " . ($cardY + 28) . " Td\n({$card['value']}) Tj\nET\n";
        
        // Label (smaller text) - centered
        $labelX = $card['x'] + ($cardWidth / 2) - 25;
        $content .= "BT\n/F1 9 Tf\n$labelX " . ($cardY + 8) . " Td\n({$card['label']}) Tj\nET\n";
    }
    
    // Reset color
    $content .= "0.2 0.2 0.2 rg\n";
    
    if ($stats && $totalReadings > 0) {
        // WATER QUALITY ANALYSIS SECTION - Better positioning
        $content .= "BT\n/F2 18 Tf\n30 530 Td\n(WATER QUALITY ANALYSIS) Tj\nET\n";
        
        $dataPeriod = date('M j, Y', strtotime($stats['first_reading'])) . ' to ' . date('M j, Y', strtotime($stats['last_reading']));
        $content .= "BT\n/F3 9 Tf\n30 515 Td\n(Analysis Period: $dataPeriod) Tj\nET\n";
        
        // Enhanced parameter analysis table with better alignment
        $tableY = 485;
        $rowHeight = 15;
        
        $content .= "0.95 0.95 0.95 rg\n"; // Header background
        $content .= "30 $tableY 535 $rowHeight re f\n";
        $content .= "0.7 0.7 0.7 RG\n";
        $content .= "30 $tableY 535 $rowHeight re S\n";
        
        // Table headers - properly aligned columns
        $content .= "0.3 0.3 0.3 rg\n";
        $content .= "BT\n/F2 9 Tf\n35 " . ($tableY + 4) . " Td\n(Parameter) Tj\nET\n";
        $content .= "BT\n/F2 9 Tf\n140 " . ($tableY + 4) . " Td\n(Average) Tj\nET\n";
        $content .= "BT\n/F2 9 Tf\n200 " . ($tableY + 4) . " Td\n(Min) Tj\nET\n";
        $content .= "BT\n/F2 9 Tf\n240 " . ($tableY + 4) . " Td\n(Max) Tj\nET\n";
        $content .= "BT\n/F2 9 Tf\n280 " . ($tableY + 4) . " Td\n(Unit) Tj\nET\n";
        $content .= "BT\n/F2 9 Tf\n330 " . ($tableY + 4) . " Td\n(Status) Tj\nET\n";
        $content .= "BT\n/F2 9 Tf\n420 " . ($tableY + 4) . " Td\n(Quality Score) Tj\nET\n";
        
        $parameters = [
            ['name' => 'pH Level', 'avg' => $stats['avg_ph'], 'min' => $stats['min_ph'], 'max' => $stats['max_ph'], 'unit' => ''],
            ['name' => 'TDS', 'avg' => $stats['avg_tds'], 'min' => $stats['min_tds'], 'max' => $stats['max_tds'], 'unit' => 'ppm'],
            ['name' => 'EC', 'avg' => $stats['avg_ec'], 'min' => $stats['min_ec'], 'max' => $stats['max_ec'], 'unit' => 'uS/cm'],
            ['name' => 'Turbidity', 'avg' => $stats['avg_turbidity'], 'min' => $stats['min_turbidity'], 'max' => $stats['max_turbidity'], 'unit' => 'NTU'],
            ['name' => 'Temperature', 'avg' => $stats['avg_temp'], 'min' => $stats['min_temp'], 'max' => $stats['max_temp'], 'unit' => 'C']
        ];
        
        $currentY = $tableY - $rowHeight;
        foreach ($parameters as $index => $param) {
            // Alternating row colors
            if ($index % 2 == 0) {
                $content .= "0.98 0.98 0.98 rg\n";
                $content .= "30 $currentY 535 $rowHeight re f\n";
            }
            
            // Row borders
            $content .= "0.9 0.9 0.9 RG\n";
            $content .= "30 $currentY 535 $rowHeight re S\n";
            
            $avg = round($param['avg'], 2);
            $min = round($param['min'], 2);
            $max = round($param['max'], 2);
            $status = getParameterStatus($param['name'], $avg);
            $score = getParameterScore($param['name'], $avg);
            
            // Data with proper column alignment
            $content .= "0.2 0.2 0.2 rg\n";
            $content .= "BT\n/F2 8 Tf\n35 " . ($currentY + 4) . " Td\n({$param['name']}) Tj\nET\n";
            $content .= "BT\n/F1 8 Tf\n150 " . ($currentY + 4) . " Td\n($avg) Tj\nET\n";
            $content .= "BT\n/F1 8 Tf\n205 " . ($currentY + 4) . " Td\n($min) Tj\nET\n";
            $content .= "BT\n/F1 8 Tf\n245 " . ($currentY + 4) . " Td\n($max) Tj\nET\n";
            $content .= "BT\n/F1 8 Tf\n285 " . ($currentY + 4) . " Td\n({$param['unit']}) Tj\nET\n";
            
            // Status with color
            $statusColor = getStatusColorRGB($status);
            $content .= "$statusColor rg\n";
            $content .= "BT\n/F2 8 Tf\n335 " . ($currentY + 4) . " Td\n($status) Tj\nET\n";
            
            // Score with color - right aligned
            $scoreColor = getScoreColorRGB($score);
            $content .= "$scoreColor rg\n";
            $content .= "BT\n/F2 8 Tf\n450 " . ($currentY + 4) . " Td\n($score/100) Tj\nET\n";
            
            $currentY -= $rowHeight;
        }
        
        // OVERALL QUALITY ASSESSMENT - Better positioning
        $overallScore = calculateOverallScore($stats);
        $assessmentY = $currentY - 20;
        
        $content .= "0.2 0.2 0.2 rg\n";
        $content .= "BT\n/F2 16 Tf\n30 $assessmentY Td\n(OVERALL QUALITY ASSESSMENT) Tj\nET\n";
        
        // Quality score display - better centered
        $scoreBoxY = $assessmentY - 45;
        $scoreColor = getScoreColorRGB($overallScore);
        $content .= "$scoreColor rg\n";
        $content .= "30 $scoreBoxY 180 35 re f\n";
        
        $content .= "1 1 1 rg\n";
        $content .= "BT\n/F2 28 Tf\n50 " . ($scoreBoxY + 18) . " Td\n($overallScore) Tj\nET\n";
        $content .= "BT\n/F1 10 Tf\n95 " . ($scoreBoxY + 18) . " Td\n(/100) Tj\nET\n";
        $content .= "BT\n/F2 12 Tf\n55 " . ($scoreBoxY + 5) . " Td\n(" . getQualityGrade($overallScore) . ") Tj\nET\n";
        
        // Quality interpretation - properly aligned
        $content .= "0.2 0.2 0.2 rg\n";
        $content .= "BT\n/F2 10 Tf\n230 " . ($scoreBoxY + 25) . " Td\n(Quality Interpretation:) Tj\nET\n";
        $interpretationText = getQualityInterpretation($overallScore);
        $content .= "BT\n/F1 9 Tf\n230 " . ($scoreBoxY + 10) . " Td\n($interpretationText) Tj\nET\n";
        
        // RECENT READINGS TABLE - Better formatting
        if (!empty($recentReadings)) {
            $readingsY = $scoreBoxY - 30;
            $content .= "BT\n/F2 14 Tf\n30 $readingsY Td\n(RECENT READINGS \\(Last 10\\)) Tj\nET\n";
            
            // Table header with proper column widths
            $headerY = $readingsY - 20;
            $content .= "0.9 0.9 0.9 rg\n";
            $content .= "30 $headerY 535 12 re f\n";
            $content .= "0.7 0.7 0.7 RG\n";
            $content .= "30 $headerY 535 12 re S\n";
            
            $content .= "0.3 0.3 0.3 rg\n";
            $content .= "BT\n/F2 8 Tf\n35 " . ($headerY + 3) . " Td\n(Date & Time) Tj\nET\n";
            $content .= "BT\n/F2 8 Tf\n150 " . ($headerY + 3) . " Td\n(pH) Tj\nET\n";
            $content .= "BT\n/F2 8 Tf\n200 " . ($headerY + 3) . " Td\n(TDS) Tj\nET\n";
            $content .= "BT\n/F2 8 Tf\n250 " . ($headerY + 3) . " Td\n(EC) Tj\nET\n";
            $content .= "BT\n/F2 8 Tf\n320 " . ($headerY + 3) . " Td\n(Turbidity) Tj\nET\n";
            $content .= "BT\n/F2 8 Tf\n420 " . ($headerY + 3) . " Td\n(Temp) Tj\nET\n";
            
            $rowY = $headerY - 12;
            foreach (array_slice($recentReadings, 0, 8) as $index => $reading) {
                if ($rowY < 60) break; // Prevent overflow
                
                // Alternating row colors
                if ($index % 2 == 0) {
                    $content .= "0.98 0.98 0.98 rg\n";
                    $content .= "30 $rowY 535 10 re f\n";
                }
                
                $content .= "0.9 0.9 0.9 RG\n";
                $content .= "30 $rowY 535 10 re S\n";
                
                $date = date('M j, g:i A', strtotime($reading['reading_timestamp']));
                
                $content .= "0.2 0.2 0.2 rg\n";
                $content .= "BT\n/F1 7 Tf\n35 " . ($rowY + 2) . " Td\n($date) Tj\nET\n";
                $content .= "BT\n/F1 7 Tf\n155 " . ($rowY + 2) . " Td\n(" . round($reading['ph_level'], 2) . ") Tj\nET\n";
                $content .= "BT\n/F1 7 Tf\n205 " . ($rowY + 2) . " Td\n(" . round($reading['tds_value']) . ") Tj\nET\n";
                $content .= "BT\n/F1 7 Tf\n255 " . ($rowY + 2) . " Td\n(" . round($reading['ec_value']) . ") Tj\nET\n";
                $content .= "BT\n/F1 7 Tf\n330 " . ($rowY + 2) . " Td\n(" . round($reading['turbidity'], 2) . ") Tj\nET\n";
                $content .= "BT\n/F1 7 Tf\n425 " . ($rowY + 2) . " Td\n(" . round($reading['temperature'], 1) . ") Tj\nET\n";
                
                $rowY -= 10;
            }
        }
        
        // RECOMMENDATIONS SECTION - Better spacing if room allows
        $recommendations = getDetailedRecommendations($stats);
        if ($rowY > 100) {
            $content .= "0.2 0.2 0.2 rg\n";
            $content .= "BT\n/F2 14 Tf\n30 " . ($rowY - 15) . " Td\n(KEY RECOMMENDATIONS) Tj\nET\n";
            
            $recY = $rowY - 30;
            foreach (array_slice($recommendations, 0, 3) as $index => $rec) {
                if ($recY < 45) break;
                
                $icon = getRecommendationIcon($rec['type']);
                $content .= "BT\n/F2 9 Tf\n35 $recY Td\n($icon {$rec['title']}) Tj\nET\n";
                
                // Word wrap for description - better formatting
                $description = wordwrap($rec['description'], 90, "\\n", true);
                $lines = explode("\\n", $description);
                $lineY = $recY - 10;
                
                foreach (array_slice($lines, 0, 2) as $line) { // Limit to 2 lines
                    if ($lineY < 25) break;
                    $content .= "BT\n/F1 8 Tf\n50 $lineY Td\n($line) Tj\nET\n";
                    $lineY -= 8;
                }
                
                $recY = $lineY - 8;
            }
        }
        
    } else {
        $content .= "BT\n/F1 12 Tf\n30 450 Td\n(No sensor data available for comprehensive analysis.) Tj\nET\n";
        $content .= "BT\n/F1 10 Tf\n30 435 Td\n(Please ensure your devices are connected and collecting data.) Tj\nET\n";
    }
    
    // FOOTER - Fixed positioning at bottom
    $content .= "0.9 0.9 0.9 rg\n";
    $content .= "0 0 595 35 re f\n";
    
    $content .= "0.5 0.5 0.5 rg\n";
    $content .= "BT\n/F3 8 Tf\n30 22 Td\n(This report was generated by Aqualitics Water Quality Monitoring System) Tj\nET\n";
    $content .= "BT\n/F3 8 Tf\n30 12 Td\n(For support: support@aqualitics.com | Web: www.aqualitics.com) Tj\nET\n";
    
    $content .= "BT\n/F1 7 Tf\n450 22 Td\n(Report ID: AQL-" . date('Ymd-His') . ") Tj\nET\n";
    $content .= "BT\n/F1 7 Tf\n480 12 Td\n(Page 1 of 1) Tj\nET\n";
    
    $content .= "Q\n"; // Restore graphics state
    
    return $content;
}

// Helper functions for enhanced PDF generation
function getParameterStatus($paramName, $value) {
    switch ($paramName) {
        case 'pH Level':
            if ($value < 6.5) return "Low";
            if ($value > 8.5) return "High";
            return "Good";
        case 'TDS':
            if ($value < 150) return "Excellent";
            if ($value < 300) return "Good";
            if ($value < 600) return "Fair";
            if ($value < 900) return "Poor";
            return "Bad";
        case 'Turbidity':
            if ($value < 1) return "Clear";
            if ($value < 4) return "Good";
            return "High";
        case 'EC':
            if ($value < 300) return "Good";
            if ($value < 600) return "Fair";
            return "High";
        case 'Temperature':
            if ($value < 15) return "Cold";
            if ($value > 30) return "Warm";
            return "Normal";
        default:
            return "Unknown";
    }
}

function getParameterScore($paramName, $value) {
    switch ($paramName) {
        case 'pH Level':
            if ($value >= 6.5 && $value <= 8.5) return 100;
            if ($value >= 6.0 && $value <= 9.0) return 75;
            if ($value >= 5.5 && $value <= 9.5) return 50;
            return 25;
        case 'TDS':
            if ($value < 150) return 100;
            if ($value < 300) return 85;
            if ($value < 600) return 70;
            if ($value < 900) return 50;
            return 25;
        case 'Turbidity':
            if ($value < 1) return 100;
            if ($value < 4) return 80;
            if ($value < 10) return 60;
            return 30;
        case 'EC':
            if ($value < 300) return 100;
            if ($value < 600) return 75;
            if ($value < 1000) return 50;
            return 25;
        case 'Temperature':
            if ($value >= 15 && $value <= 30) return 100;
            if ($value >= 10 && $value <= 35) return 80;
            return 60;
        default:
            return 50;
    }
}

function getStatusColorRGB($status) {
    switch (strtolower($status)) {
        case 'excellent':
        case 'good':
        case 'clear':
        case 'normal':
            return "0.1 0.6 0.1"; // Green
        case 'fair':
        case 'warm':
        case 'cold':
            return "0.9 0.6 0.1"; // Orange
        case 'poor':
        case 'bad':
        case 'high':
        case 'low':
            return "0.8 0.2 0.2"; // Red
        default:
            return "0.5 0.5 0.5"; // Gray
    }
}

function getScoreColorRGB($score) {
    if ($score >= 85) return "0.1 0.6 0.1"; // Green
    if ($score >= 70) return "0.6 0.8 0.1"; // Yellow-green
    if ($score >= 50) return "0.9 0.6 0.1"; // Orange
    return "0.8 0.2 0.2"; // Red
}

function calculateOverallScore($stats) {
    $phScore = getParameterScore('pH Level', $stats['avg_ph']);
    $tdsScore = getParameterScore('TDS', $stats['avg_tds']);
    $turbidityScore = getParameterScore('Turbidity', $stats['avg_turbidity']);
    $ecScore = getParameterScore('EC', $stats['avg_ec']);
    $tempScore = getParameterScore('Temperature', $stats['avg_temp']);
    
    // Weighted average (pH and TDS are more important)
    $weightedScore = ($phScore * 0.25) + ($tdsScore * 0.25) + ($turbidityScore * 0.2) + ($ecScore * 0.15) + ($tempScore * 0.15);
    
    return round($weightedScore);
}

function getQualityGrade($score) {
    if ($score >= 90) return "EXCELLENT";
    if ($score >= 80) return "GOOD";
    if ($score >= 70) return "FAIR";
    if ($score >= 60) return "POOR";
    return "CRITICAL";
}

function getQualityInterpretation($score) {
    if ($score >= 90) return "Water quality exceeds standards. Safe for all uses.";
    if ($score >= 80) return "Good water quality with minor concerns.";
    if ($score >= 70) return "Acceptable quality but monitoring recommended.";
    if ($score >= 60) return "Poor quality requires immediate attention.";
    return "Critical quality issues - treatment required.";
}

function getDetailedRecommendations($stats) {
    $recommendations = [];
    
    // pH Recommendations
    if ($stats['avg_ph'] < 6.5) {
        $recommendations[] = [
            'type' => 'warning',
            'title' => 'Acidic Water - pH Adjustment Needed',
            'description' => 'Install alkaline treatment system. Acidic water can corrode pipes and affect taste.'
        ];
    } elseif ($stats['avg_ph'] > 8.5) {
        $recommendations[] = [
            'type' => 'warning',
            'title' => 'Alkaline Water - pH Reduction Required',
            'description' => 'Consider water acidification or RO filtration. High pH causes scaling and bitter taste.'
        ];
    } else {
        $recommendations[] = [
            'type' => 'success',
            'title' => 'pH Level Optimal',
            'description' => 'pH levels are within recommended range. Continue regular monitoring.'
        ];
    }
    
    // TDS Recommendations
    if ($stats['avg_tds'] > 500) {
        $recommendations[] = [
            'type' => 'warning',
            'title' => 'High TDS - Filtration Required',
            'description' => 'Install RO system or carbon filter. High TDS affects taste and indicates contamination.'
        ];
    } else {
        $recommendations[] = [
            'type' => 'success',
            'title' => 'TDS Levels Acceptable',
            'description' => 'TDS levels indicate good mineral balance without excessive contamination.'
        ];
    }
    
    // Turbidity Recommendations
    if ($stats['avg_turbidity'] > 4) {
        $recommendations[] = [
            'type' => 'warning',
            'title' => 'High Turbidity - Immediate Filtration',
            'description' => 'Install sediment filters. High turbidity harbors bacteria and affects appearance.'
        ];
    } else {
        $recommendations[] = [
            'type' => 'success',
            'title' => 'Water Clarity Excellent',
            'description' => 'Turbidity levels indicate clear, well-filtered water.'
        ];
    }
    
    // General recommendations
    $recommendations[] = [
        'type' => 'info',
        'title' => 'Regular Monitoring',
        'description' => 'Continue daily monitoring and set up parameter alerts for changes.'
    ];
    
    $recommendations[] = [
        'type' => 'maintenance',
        'title' => 'Professional Testing',
        'description' => 'Schedule quarterly lab testing for bacteria and heavy metals analysis.'
    ];
    
    $recommendations[] = [
        'type' => 'maintenance',
        'title' => 'System Maintenance',
        'description' => 'Clean sensors monthly and calibrate equipment quarterly for accuracy.'
    ];
    
    return $recommendations;
}

function getRecommendationIcon($type) {
    switch ($type) {
        case 'success':
            return "✓";
        case 'warning':
            return "⚠";
        case 'info':
            return "ℹ";
        case 'maintenance':
            return "🔧";
        default:
            return "•";
    }
}
?>