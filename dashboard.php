<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/auth_check.php';
checkAuth('client');

// Set page title and additional CSS
$page_title = 'Dashboard';
$additional_css = ['dashboard.css'];
$additional_js = ['dashboard.js'];

// Include header
include 'includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Water Quality Dashboard</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</span>
            <span id="lastUpdate">Last updated: Loading...</span>
        </div>
        <div class="location-info">
            <span>📍 San Fernando, La Union, Philippines</span>
        </div>
    </div>

    <!-- Device Pairing Panel -->
    <div class="device-pairing-panel">
        <div class="pairing-header">
            <h3>🔌 IoT Device Connection</h3>
            <div id="pairingStatus" class="pairing-status status-disconnected">
                <span class="status-indicator"></span>
                <span class="status-text">Not Connected</span>
            </div>
        </div>
        <div class="pairing-controls">
            <select id="deviceSelect" class="device-select">
                <option value="">Select a device...</option>
            </select>
            <button id="connectBtn" class="btn-connect" onclick="connectToDevice()">
                🔗 Connect
            </button>
            <button id="disconnectBtn" class="btn-disconnect" onclick="disconnectFromDevice()" style="display: none;">
                ❌ Disconnect
            </button>
        </div>
        <div id="pairingInfo" class="pairing-info" style="display: block;">
            <p class="info-notice">⚠️ No device connected. Select a device and click Connect to start receiving data.</p>
        </div>
    </div>

    <!-- Real-time Sensor Cards -->
    <div class="sensor-grid">
        <div class="sensor-card ph-card">
            <div class="sensor-header">
                <h3>pH Level</h3>
                <span class="sensor-icon">🧪</span>
            </div>
            <div class="sensor-value" id="phValue">--</div>
            <div class="sensor-status" id="phStatus">Not Connected</div>
        </div>

        <div class="sensor-card tds-card">
            <div class="sensor-header">
                <h3>TDS (ppm)</h3>
                <span class="sensor-icon">💧</span>
            </div>
            <div class="sensor-value" id="tdsValue">--</div>
            <div class="sensor-status" id="tdsStatus">Not Connected</div>
        </div>

        <div class="sensor-card ec-card">
            <div class="sensor-header">
                <h3>EC (μS/cm)</h3>
                <span class="sensor-icon">⚡</span>
            </div>
            <div class="sensor-value" id="ecValue">--</div>
            <div class="sensor-status" id="ecStatus">Not Connected</div>
        </div>

        <div class="sensor-card turbidity-card">
            <div class="sensor-header">
                <h3>Turbidity (NTU)</h3>
                <span class="sensor-icon">🌊</span>
            </div>
            <div class="sensor-value" id="turbidityValue">--</div>
            <div class="sensor-status" id="turbidityStatus">Not Connected</div>
        </div>
    </div>

    <!-- Data Management -->
    <div class="data-management">
        <div class="management-header">
            <h3>Data Management</h3>
            <div class="management-buttons">
                <button class="btn-import" onclick="importData()">📥 Import CSV</button>
                <button class="btn-export" onclick="exportData()">📤 Export CSV</button>
                <button class="btn-add" onclick="openAddModal()">➕ Add Reading</button>
                <button class="btn-delete-all" onclick="deleteAllData()">🗑️ Delete All Data</button>
            </div>
        </div>
    </div>

    <!-- Data Table Section -->
    <div class="data-table-section">
        <div class="table-header">
            <h3>Sensor Readings History</h3>
            <div class="table-controls">
                <input type="text" id="searchInput" placeholder="Search readings..." class="search-input">
                <select id="recordsPerPage" class="records-select">
                    <option value="10">10 per page</option>
                    <option value="25">25 per page</option>
                    <option value="50">50 per page</option>
                    <option value="100">100 per page</option>
                </select>
            </div>
        </div>
        
        <div class="table-container">
            <table id="dataTable" class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Timestamp</th>
                        <th>pH Level</th>
                        <th>TDS (ppm)</th>
                        <th>EC (μS/cm)</th>
                        <th>Turbidity (NTU)</th>
                        <th>Temperature (°C)</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr>
                        <td colspan="7" class="empty-state">
                            <div>
                                <h4>No data found</h4>
                                <p>Connect to a device to start collecting data.</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="pagination">
            <button id="prevPage" class="pagination-btn" onclick="changePage(-1)">Previous</button>
            <span id="pageInfo">Page 1 of 1</span>
            <button id="nextPage" class="pagination-btn" onclick="changePage(1)">Next</button>
        </div>
    </div>

    <!-- Analytics Charts -->
    <div class="analytics-section">
        <h3>Analytics</h3>
        <div class="chart-container">
            <canvas id="sensorChart"></canvas>
        </div>
        
        <div class="chart-controls">
            <select id="timeRange">
                <option value="all">Over Time (All Data)</option>
                <option value="24h">Last 24 Hours</option>
                <option value="7d">Last 7 Days</option>
                <option value="30d">Last 30 Days</option>
            </select>
        </div>
    </div>
</div>

<!-- Hidden file input for CSV import -->
<input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display: none;">

<!-- Add/Edit Modal -->
<div id="dataModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add New Reading</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form id="dataForm">
            <input type="hidden" id="recordId" name="id">
            
            <div class="form-group">
                <label for="phLevel">pH Level:</label>
                <input type="number" id="phLevel" name="ph_level" step="0.01" min="0" max="14" required>
            </div>
            
            <div class="form-group">
                <label for="tdsValue">TDS (ppm):</label>
                <input type="number" id="tdsValue" name="tds_value" step="0.01" min="0" required>
            </div>
            
            <div class="form-group">
                <label for="ecValue">EC (μS/cm):</label>
                <input type="number" id="ecValue" name="ec_value" step="0.01" min="0" required>
            </div>
            
            <div class="form-group">
                <label for="turbidityValue">Turbidity (NTU):</label>
                <input type="number" id="turbidityValue" name="turbidity" step="0.01" min="0" required>
            </div>
            
            <div class="form-group">
                <label for="temperatureValue">Temperature (°C):</label>
                <input type="number" id="temperatureValue" name="temperature" step="0.01" required>
            </div>
            
            <div class="form-group">
                <label for="readingTimestamp">Timestamp:</label>
                <input type="datetime-local" id="readingTimestamp" name="reading_timestamp" required>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-save">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete All Data Modal -->
<div id="deleteAllModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>⚠️ Delete All Data</h3>
            <span class="close" onclick="closeDeleteAllModal()">&times;</span>
        </div>
        <div class="warning-content">
            <div class="warning-icon">🚨</div>
            <h4>This action is irreversible!</h4>
            <p>You are about to delete <strong>ALL</strong> your sensor readings data. This includes:</p>
            <ul>
                <li>All historical sensor readings</li>
                <li>All imported data</li>
                <li>All manually added records</li>
            </ul>
            <p><strong>This action cannot be undone!</strong> Please make sure you have backed up your data if needed.</p>
            <div class="confirmation-input">
                <label>
                    <input type="checkbox" id="deleteAllConfirm"> 
                    I understand that this will permanently delete all my data
                </label>
            </div>
        </div>
        <div class="form-actions">
            <button type="button" class="btn-cancel" onclick="closeDeleteAllModal()">Cancel</button>
            <button type="button" class="btn-delete-confirm" onclick="confirmDeleteAll()" disabled>Delete All Data</button>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>