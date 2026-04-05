<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/auth_check.php';
checkAuth('admin');

// Set page title and additional CSS
$page_title = 'Admin Dashboard';
$additional_css = ['admin.css'];
$additional_js = ['admin.js'];

// Include header
include 'includes/header.php';
?>

<div class="admin-container">
    <!-- Dashboard Header -->
    <div class="admin-header">
        <h1>Admin Dashboard</h1>
        <div class="stats-summary">
            <div class="stat-item">
                <span class="stat-number" id="totalClients">0</span>
                <span class="stat-label">Total Clients</span>
            </div>
            <div class="stat-item">
                <span class="stat-number" id="totalReadings">0</span>
                <span class="stat-label">Total Readings</span>
            </div>
        </div>
    </div>

    <!-- Client Data Overview Section -->
    <div class="admin-section">
        <div class="section-header">
            <h3>Client Data Overview</h3>
            <div class="data-controls">
                <select id="clientSelector" class="client-selector">
                    <option value="all">All Clients</option>
                </select>
                <button class="btn-export" onclick="exportClientData()">📊 Export Data</button>
                <button class="btn-refresh" onclick="loadClientData()">🔄 Refresh</button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="client-data-stats">
            <div class="data-stat-item">
                <span class="data-stat-number" id="selectedClientReadings">0</span>
                <span class="data-stat-label">Total Readings</span>
            </div>
            <div class="data-stat-item">
                <span class="data-stat-number" id="avgPH">0.00</span>
                <span class="data-stat-label">Avg pH</span>
            </div>
            <div class="data-stat-item">
                <span class="data-stat-number" id="avgTDS">0.00</span>
                <span class="data-stat-label">Avg TDS (ppm)</span>
            </div>
            <div class="data-stat-item">
                <span class="data-stat-number" id="avgEC">0.00</span>
                <span class="data-stat-label">Avg EC (μS/cm)</span>
            </div>
            <div class="data-stat-item">
                <span class="data-stat-number" id="avgTurbidity">0.00</span>
                <span class="data-stat-label">Avg Turbidity (NTU)</span>
            </div>
        </div>

        <!-- Data Table -->
        <div class="data-table-section">
            <div class="table-header">
                <h4>Sensor Readings Data</h4>
                <div class="table-controls">
                    <input type="text" id="dataSearchInput" placeholder="Search readings..." class="search-input">
                    <select id="dataRecordsPerPage" class="records-select">
                        <option value="10">10 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client</th>
                            <th>Timestamp</th>
                            <th>pH Level</th>
                            <th>TDS (ppm)</th>
                            <th>EC (μS/cm)</th>
                            <th>Turbidity (NTU)</th>
                            <th>Temperature (°C)</th>
                        </tr>
                    </thead>
                    <tbody id="dataTableBody">
                        <tr>
                            <td colspan="8" class="loading-cell">Loading data...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="pagination">
                <button id="dataPrevPage" class="pagination-btn" onclick="changeDataPage(-1)">Previous</button>
                <span id="dataPageInfo">Page 1 of 1</span>
                <button id="dataNextPage" class="pagination-btn" onclick="changeDataPage(1)">Next</button>
            </div>
        </div>
    </div>

    <!-- Data Analytics Section -->
    <div class="admin-section">
        <div class="section-header">
            <h3>Data Analytics</h3>
            <div class="analytics-controls">
                <select id="dataTimeRange">
                    <option value="all">All Time</option>
                    <option value="24h">Last 24 Hours</option>
                    <option value="7d">Last 7 Days</option>
                    <option value="30d">Last 30 Days</option>
                </select>
            </div>
        </div>
        
        <div class="analytics-grid">
            <div class="chart-container">
                <canvas id="clientDataChart"></canvas>
            </div>
            <div class="data-insights">
                <h4>Data Quality Insights</h4>
                <div id="dataInsights">
                    <div class="insight-item">
                        <span class="insight-label">pH Status:</span>
                        <span class="insight-value" id="phQualityStatus">Analyzing...</span>
                    </div>
                    <div class="insight-item">
                        <span class="insight-label">TDS Status:</span>
                        <span class="insight-value" id="tdsQualityStatus">Analyzing...</span>
                    </div>
                    <div class="insight-item">
                        <span class="insight-label">EC Status:</span>
                        <span class="insight-value" id="ecQualityStatus">Analyzing...</span>
                    </div>
                    <div class="insight-item">
                        <span class="insight-label">Turbidity Status:</span>
                        <span class="insight-value" id="turbidityQualityStatus">Analyzing...</span>
                    </div>
                    <div class="insight-item">
                        <span class="insight-label">Data Frequency:</span>
                        <span class="insight-value" id="dataFrequency">Calculating...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Client Management Section - MODIFIED: Gender changed to Location -->
    <div class="admin-section">
        <div class="section-header">
            <h3>Client Management</h3>
            <button class="btn-refresh" onclick="loadClients()">🔄 Refresh</button>
        </div>
        <div class="table-responsive">
            <table class="clients-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Total Readings</th>
                    </tr>
                </thead>
                <tbody id="clientsTableBody">
                    <tr>
                        <td colspan="7" class="loading-cell">Loading clients...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- System Analytics Section -->
    <div class="admin-section">
        <h3 style="margin-bottom: 2rem; background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">System Analytics</h3>
        <div class="analytics-grid">
            <div class="chart-container">
                <canvas id="systemChart"></canvas>
            </div>
            <div class="recent-activity">
                <h4>Recent Activity</h4>
                <div id="activityLog">
                    <div class="loading-text">Loading activity...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Client Details Modal -->
<div id="clientModal" class="modal">
    <div class="modal-content client-modal">
        <span class="close" onclick="closeClientModal()">&times;</span>
        <div id="modalContent">
            <!-- Dynamic content will be loaded here -->
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>