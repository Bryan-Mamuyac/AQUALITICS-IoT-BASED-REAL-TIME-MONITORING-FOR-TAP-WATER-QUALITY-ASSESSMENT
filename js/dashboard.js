// Dashboard specific variables
let sensorChart = null;
let sensorData = [];
let isRealTimeEnabled = true;
let currentPage = 1;
let recordsPerPage = 10;
let totalRecords = 0;
let totalPages = 0;
let searchTerm = '';
let isConnectedToDevice = false;
let currentDeviceId = null;
let pairingCheckInterval = null;
let userHeartbeatInterval = null;

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    console.log('Dashboard initializing...');
    
    initializeDashboard();
    loadDeviceList();
    checkConnectionStatus();
    
    // IMPORTANT: Load data immediately on page load
    loadInitialData();
    loadTableData();
    
    startRealTimeUpdates();
    
    // Wait for Chart.js to load before initializing chart
    waitForChartJS();
    
    // NEW: Auto-disconnect when closing browser/tab
    setupAutoDisconnect();
});

// Load available devices
async function loadDeviceList() {
    try {
        const response = await fetch('api/device_pairing.php?action=list_devices');
        const data = await response.json();
        
        if (data.success) {
            const deviceSelect = document.getElementById('deviceSelect');
            deviceSelect.innerHTML = '<option value="">Select a device...</option>';
            
            data.devices.forEach(device => {
                const option = document.createElement('option');
                option.value = device.id;
                
                // Show online/offline status in dropdown
                const statusIcon = device.is_online ? '🟢' : '🔴';
                const statusText = device.is_online ? 'ONLINE' : 'OFFLINE';
                
                option.textContent = `${statusIcon} ${device.device_name} (${device.device_id}) - ${statusText}`;
                
                // Disable offline devices
                if (!device.is_online) {
                    option.disabled = true;
                    option.style.color = '#999';
                }
                
                deviceSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading devices:', error);
    }
}

// Check connection status
async function checkConnectionStatus() {
    try {
        const response = await fetch('api/device_pairing.php?action=status');
        const data = await response.json();
        
        if (data.success && data.connected) {
            isConnectedToDevice = true;
            currentDeviceId = data.device_id;
            
            // Check if device is actually online
            if (data.is_online === false) {
                // Device went offline!
                console.warn('Device is offline - auto-disconnecting...');
                showMessage('⚠️ Arduino went offline. Disconnecting...', 'warning');
                await disconnectFromDevice();
                return;
            }
            
            updatePairingUI(true, data.device_name, data.minutes_connected);
            
            // Start auto-refresh
            if (!pairingCheckInterval) {
                pairingCheckInterval = setInterval(checkConnectionStatus, 30000);
            }
            
            // Load initial data
            loadInitialData();
        } else {
            isConnectedToDevice = false;
            currentDeviceId = null;
            updatePairingUI(false);
        }
    } catch (error) {
        console.error('Error checking connection status:', error);
    }
}

// Connect to device
async function connectToDevice() {
    const deviceSelect = document.getElementById('deviceSelect');
    const deviceId = deviceSelect.value;
    
    if (!deviceId) {
        showMessage('Please select a device first', 'warning');
        return;
    }
    
    try {
        showMessage('Checking device status...', 'info');
        
        const statusResponse = await fetch(`api/device_pairing.php?action=check_device_online&device_id=${deviceId}`);
        const statusData = await statusResponse.json();
        
        if (!statusData.success || !statusData.is_online) {
            showMessage('⚠️ Arduino device is OFFLINE!', 'error');
            return;
        }
        
        showMessage('Device is online. Connecting...', 'info');
        
        const response = await fetch('api/device_pairing.php?action=connect', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ device_id: deviceId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // ✅ Connection successful
            isConnectedToDevice = true;
            currentDeviceId = deviceId;
            showMessage(data.message, 'success');
            
            const selectedOption = deviceSelect.options[deviceSelect.selectedIndex];
            const deviceName = selectedOption.textContent;
            updatePairingUI(true, deviceName, 0);
            
            if (!pairingCheckInterval) {
                pairingCheckInterval = setInterval(checkConnectionStatus, 30000);
            }
            
            // Start user heartbeat when connected
            if (!userHeartbeatInterval) {
                startUserHeartbeat();
            }
            
            loadInitialData();
            loadTableData();
            
        } else if (data.in_use || data.blocked) {
            // ⚠️ NEW: Device is in use by another user - show detailed error
            showDeviceInUseError(data);
            
        } else {
            // ❌ Other error
            showMessage(data.message, 'error');
        }
    } catch (error) {
        console.error('Connection error:', error);
        showMessage('Failed to connect to device.', 'error');
    }
}

// Show detailed "device in use" error
function showDeviceInUseError(data) {
    const otherUser = data.current_user || 'another user';
    const minutes = data.minutes_connected || 0;
    
    // Calculate time display
    let timeDisplay = 'just now';
    if (minutes >= 60) {
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        timeDisplay = `${hours}h ${mins}m ago`;
    } else if (minutes > 0) {
        timeDisplay = `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
    }
    
    // Show prominent error message
    showMessage(data.message, 'error');
    
    // Also update the pairing info area to show who's using it
    const pairingInfo = document.getElementById('pairingInfo');
    if (pairingInfo) {
        pairingInfo.innerHTML = `
            <div class="device-in-use-notice">
                <div class="notice-icon">🔒</div>
                <div class="notice-content">
                    <h3>Device Currently In Use</h3>
                    <p><strong>Connected User:</strong> ${otherUser}</p>
                    <p><strong>Connected Since:</strong> ${timeDisplay}</p>
                    <p class="notice-help">⏳ Please wait for the other user to disconnect, or try another device.</p>
                    <button class="btn-refresh" onclick="loadDeviceList(); checkConnectionStatus();">
                        🔄 Check Availability
                    </button>
                </div>
            </div>
        `;
        pairingInfo.style.display = 'block';
    }
    
    // Auto-refresh after 10 seconds to check if device becomes available
    setTimeout(() => {
        console.log('Auto-checking device availability...');
        loadDeviceList();
    }, 10000);
}

// Disconnect from device
async function disconnectFromDevice() {
    try {
        showMessage('Disconnecting from device...', 'info');
        
        const response = await fetch('api/device_pairing.php?action=disconnect', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            isConnectedToDevice = false;
            currentDeviceId = null;
            showMessage(data.message, 'success');
            updatePairingUI(false);
            
            if (pairingCheckInterval) {
                clearInterval(pairingCheckInterval);
                pairingCheckInterval = null;
            }
            
            // Stop user heartbeat when disconnected
            if (userHeartbeatInterval) {
                clearInterval(userHeartbeatInterval);
                userHeartbeatInterval = null;
            }
        } else {
            showMessage(data.message, 'error');
        }
    } catch (error) {
        console.error('Disconnection error:', error);
        showMessage('Failed to disconnect from device', 'error');
    }
}

// Update pairing UI
function updatePairingUI(connected, deviceName = '', minutesConnected = 0) {
    const status = document.getElementById('pairingStatus');
    const info = document.getElementById('pairingInfo');
    const connectBtn = document.getElementById('connectBtn');
    const disconnectBtn = document.getElementById('disconnectBtn');
    const deviceSelect = document.getElementById('deviceSelect');
    
    if (connected) {
        status.className = 'pairing-status status-connected';
        status.querySelector('.status-text').textContent = 'Connected';
        
        let duration = 'just now';
        if (minutesConnected >= 60) {
            const hours = Math.floor(minutesConnected / 60);
            const mins = minutesConnected % 60;
            duration = `${hours}h ${mins}m ago`;
        } else if (minutesConnected > 0) {
            duration = `${minutesConnected}m ago`;
        }
        
        info.innerHTML = `
            <p><strong>📡 Connected Device:</strong> ${deviceName}</p>
            <p><strong>⏱️ Connection Started:</strong> ${duration}</p>
            <p class="info-highlight">✅ Device is collecting data and sending it to your account</p>
        `;
        info.style.display = 'block';
        
        connectBtn.style.display = 'none';
        disconnectBtn.style.display = 'inline-flex';
        deviceSelect.disabled = true;
        
    } else {
        status.className = 'pairing-status status-disconnected';
        status.querySelector('.status-text').textContent = 'Not Connected';
        
        info.innerHTML = `
            <p class="info-notice">⚠️ No device connected. Select a device and click Connect to start receiving data.</p>
        `;
        info.style.display = 'block';
        
        connectBtn.style.display = 'inline-flex';
        disconnectBtn.style.display = 'none';
        deviceSelect.disabled = false;
    }
}

// Reset sensor cards
function resetSensorCards() {
    ['ph', 'tds', 'ec', 'turbidity'].forEach(sensor => {
        const valueEl = document.getElementById(`${sensor}Value`);
        const statusEl = document.getElementById(`${sensor}Status`);
        if (valueEl) valueEl.textContent = '--';
        if (statusEl) {
            statusEl.textContent = 'No Data';
            statusEl.className = 'sensor-status';
        }
    });
}

// Wait for Chart.js to be available
function waitForChartJS() {
    let attempts = 0;
    const maxAttempts = 50;
    
    const checkChart = () => {
        attempts++;
        if (typeof Chart !== 'undefined') {
            console.log('Chart.js loaded successfully');
            setTimeout(() => {
                initializeChart();
            }, 200);
        } else if (attempts < maxAttempts) {
            setTimeout(checkChart, 100);
        } else {
            console.error('Chart.js failed to load after 5 seconds');
            showMessage('Chart library failed to load. Please refresh the page.', 'error');
        }
    };
    
    checkChart();
}

// Initialize dashboard
function initializeDashboard() {
    document.getElementById('timeRange').addEventListener('change', updateChart);
    document.getElementById('recordsPerPage').addEventListener('change', onRecordsPerPageChange);
    document.getElementById('searchInput').addEventListener('input', onSearchChange);
    
    const importFile = document.getElementById('importFile');
    if (importFile) {
        importFile.addEventListener('change', handleFileImport);
    }
    
    const dataForm = document.getElementById('dataForm');
    if (dataForm) {
        dataForm.addEventListener('submit', handleFormSubmit);
    }
    
    const deleteAllConfirm = document.getElementById('deleteAllConfirm');
    if (deleteAllConfirm) {
        deleteAllConfirm.addEventListener('change', function() {
            const confirmBtn = document.querySelector('.btn-delete-confirm');
            if (confirmBtn) {
                confirmBtn.disabled = !this.checked;
            }
        });
    }
    
    const timestampInput = document.getElementById('readingTimestamp');
    if (timestampInput) {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        timestampInput.value = now.toISOString().slice(0, 16);
    }
}

// Load initial sensor data
async function loadInitialData() {
    try {
        const response = await fetch('api/sensor_data.php?action=latest');
        const data = await response.json();
        
        if (data.success && data.data) {
            updateSensorCards(data.data);
            updateLastUpdate();
        } else {
            resetSensorCardsToZero();
        }
    } catch (error) {
        console.error('Error loading initial data:', error);
        resetSensorCardsToZero();
    }
}

// Start real-time updates
function startRealTimeUpdates() {
    if (isRealTimeEnabled) {
        // Always load data every 30 seconds (regardless of connection)
        setInterval(() => {
            loadInitialData();
        }, 30000);
        
        // Update chart every 5 minutes
        setInterval(() => {
            updateChart();
        }, 300000);
        
        // AUTO-REFRESH TABLE: Refresh table every 31 seconds
        setInterval(() => {
            console.log('Auto-refreshing table data...');
            loadTableData();
        }, 31000);
    }
}

// Update sensor cards
function updateSensorCards(data) {
    if (!data) return;
    
    updateSensorCard('ph', data.ph_level, {
        good: { min: 6.5, max: 8.5 },
        warning: { min: 6.0, max: 9.0 }
    });
    
    updateSensorCard('tds', data.tds_value, {
        good: { min: 0, max: 300 },
        warning: { min: 0, max: 500 }
    }, 'ppm');
    
    updateSensorCard('ec', data.ec_value, {
        good: { min: 0, max: 600 },
        warning: { min: 0, max: 1000 }
    }, 'μS/cm');
    
    updateSensorCard('turbidity', data.turbidity, {
        good: { min: 0, max: 1 },
        warning: { min: 0, max: 4 }
    }, 'NTU');
}

// Update individual sensor card
function updateSensorCard(sensor, value, thresholds, unit = '') {
    const valueElement = document.getElementById(`${sensor}Value`);
    const statusElement = document.getElementById(`${sensor}Status`);
    
    if (valueElement && statusElement) {
        valueElement.textContent = formatNumber(value) + (unit ? ' ' + unit : '');
        
        let status = 'danger';
        let statusText = 'Poor';
        
        if (value >= thresholds.good.min && value <= thresholds.good.max) {
            status = 'good';
            statusText = 'Good';
        } else if (value >= thresholds.warning.min && value <= thresholds.warning.max) {
            status = 'warning';
            statusText = 'Warning';
        }
        
        statusElement.textContent = statusText;
        statusElement.className = `sensor-status status-${status}`;
    }
}

// Update last update time
function updateLastUpdate() {
    const lastUpdateElement = document.getElementById('lastUpdate');
    if (lastUpdateElement) {
        lastUpdateElement.textContent = `Showing average values - Updated: ${new Date().toLocaleTimeString()}`;
    }
}

// Load table data
async function loadTableData() {
    // Show loading indicator
    const tableBody = document.getElementById('tableBody');
    if (tableBody) {
        const firstRow = tableBody.querySelector('tr');
        if (firstRow) {
            firstRow.style.opacity = '0.5';
        }
    }
    
    try {
        const response = await fetch(`api/table_data.php?page=${currentPage}&limit=${recordsPerPage}&search=${encodeURIComponent(searchTerm)}`);
        const data = await response.json();
        
        if (data.success) {
            populateTable(data.data);
            updatePagination(data.pagination);
            
            // Show brief success indicator
            const pageInfo = document.getElementById('pageInfo');
            if (pageInfo) {
                const originalText = pageInfo.textContent;
                pageInfo.textContent = '✓ Updated';
                pageInfo.style.color = '#10b981';
                setTimeout(() => {
                    pageInfo.textContent = originalText;
                    pageInfo.style.color = '';
                }, 1000);
            }
        } else {
            showMessage('Error loading table data: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error loading table data:', error);
        showMessage('Error loading table data', 'error');
    }
    
    // Remove loading indicator
    if (tableBody) {
        const firstRow = tableBody.querySelector('tr');
        if (firstRow) {
            firstRow.style.opacity = '1';
        }
    }
}

// Populate table with data
function populateTable(data) {
    const tableBody = document.getElementById('tableBody');
    if (!tableBody) return;
    
    if (data.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="empty-state">
                    <div>
                        <h4>No data found</h4>
                        <p>${isConnectedToDevice ? 'Waiting for sensor data...' : 'Connect to a device to start collecting data.'}</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    tableBody.innerHTML = data.map(row => `
        <tr>
            <td>${row.id}</td>
            <td>${formatDateTime(row.reading_timestamp)}</td>
            <td>${formatNumber(row.ph_level)}</td>
            <td>${formatNumber(row.tds_value)}</td>
            <td>${formatNumber(row.ec_value)}</td>
            <td>${formatNumber(row.turbidity)}</td>
            <td>${formatNumber(row.temperature)}</td>
        </tr>
    `).join('');
}

// Update pagination
function updatePagination(pagination) {
    totalRecords = pagination.total;
    totalPages = pagination.pages;
    currentPage = pagination.current;
    
    const pageInfo = document.getElementById('pageInfo');
    const prevBtn = document.getElementById('prevPage');
    const nextBtn = document.getElementById('nextPage');
    
    if (pageInfo) {
        pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
    }
    
    if (prevBtn) {
        prevBtn.disabled = currentPage <= 1;
    }
    
    if (nextBtn) {
        nextBtn.disabled = currentPage >= totalPages;
    }
}

// Change page
function changePage(direction) {
    const newPage = currentPage + direction;
    if (newPage >= 1 && newPage <= totalPages) {
        currentPage = newPage;
        loadTableData();
    }
}

// Records per page change
function onRecordsPerPageChange(e) {
    recordsPerPage = parseInt(e.target.value);
    currentPage = 1;
    loadTableData();
}

// Search change
function onSearchChange(e) {
    searchTerm = e.target.value;
    currentPage = 1;
    loadTableData();
}

// Open add modal
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Reading';
    document.getElementById('dataForm').reset();
    document.getElementById('recordId').value = '';
    
    const timestampInput = document.getElementById('readingTimestamp');
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    timestampInput.value = now.toISOString().slice(0, 16);
    
    document.getElementById('dataModal').style.display = 'block';
}

// Delete all data
function deleteAllData() {
    document.getElementById('deleteAllModal').style.display = 'block';
    const checkbox = document.getElementById('deleteAllConfirm');
    const confirmBtn = document.querySelector('.btn-delete-confirm');
    if (checkbox) checkbox.checked = false;
    if (confirmBtn) confirmBtn.disabled = true;
}

// Confirm delete all
async function confirmDeleteAll() {
    const checkbox = document.getElementById('deleteAllConfirm');
    if (!checkbox || !checkbox.checked) {
        showMessage('Please confirm by checking the checkbox', 'warning');
        return;
    }
    
    try {
        showMessage('Deleting all data...', 'info');
        
        const response = await fetch('api/table_data.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                action: 'delete_all',
                confirmed: true 
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage(data.message, 'success');
            loadTableData();
            loadInitialData();
            updateChart();
            currentPage = 1;
        } else {
            showMessage('Error deleting all data: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Delete all error:', error);
        showMessage('Error deleting all data', 'error');
    }
    
    closeDeleteAllModal();
}

// Handle form submit
async function handleFormSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    const isEdit = data.id !== '';
    const method = isEdit ? 'PUT' : 'POST';
    
    try {
        const response = await fetch('api/table_data.php', {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage(isEdit ? 'Record updated successfully' : 'Record added successfully', 'success');
            closeModal();
            loadTableData();
            loadInitialData();
            updateChart();
        } else {
            showMessage('Error saving record: ' + result.message, 'error');
        }
    } catch (error) {
        console.error('Save error:', error);
        showMessage('Error saving record', 'error');
    }
}

// Close modal
function closeModal() {
    document.getElementById('dataModal').style.display = 'none';
}

// Close delete all modal
function closeDeleteAllModal() {
    document.getElementById('deleteAllModal').style.display = 'none';
    const checkbox = document.getElementById('deleteAllConfirm');
    const confirmBtn = document.querySelector('.btn-delete-confirm');
    if (checkbox) checkbox.checked = false;
    if (confirmBtn) confirmBtn.disabled = true;
}

// Close modal when clicking outside
window.onclick = function(event) {
    const dataModal = document.getElementById('dataModal');
    const deleteAllModal = document.getElementById('deleteAllModal');
    
    if (event.target === dataModal) {
        closeModal();
    }
    if (event.target === deleteAllModal) {
        closeDeleteAllModal();
    }
}

// Initialize chart
function initializeChart() {
    const ctx = document.getElementById('sensorChart');
    if (!ctx) {
        console.error('Chart canvas element not found');
        return;
    }
    
    if (sensorChart) {
        sensorChart.destroy();
        sensorChart = null;
    }
    
    try {
        sensorChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'pH Level',
                        data: [],
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        tension: 0.4,
                        fill: false,
                        pointBackgroundColor: '#f59e0b',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    },
                    {
                        label: 'TDS (ppm)',
                        data: [],
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: false,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    },
                    {
                        label: 'EC (μS/cm)',
                        data: [],
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: false,
                        pointBackgroundColor: '#10b981',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    },
                    {
                        label: 'Turbidity (NTU)',
                        data: [],
                        borderColor: '#8b5cf6',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        tension: 0.4,
                        fill: false,
                        pointBackgroundColor: '#8b5cf6',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: {
                                size: 12
                            }
                        }
                    },
                    title: {
                        display: true,
                        text: 'Water Quality Sensors Data Over Time',
                        font: {
                            size: 16,
                            weight: 'bold'
                        },
                        padding: 20
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        callbacks: {
                            title: function(context) {
                                return 'Time: ' + context[0].label;
                            },
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += parseFloat(context.parsed.y).toFixed(2);
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Sensor Values',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 10
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Time',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            maxRotation: 45,
                            font: {
                                size: 10
                            }
                        }
                    }
                },
                elements: {
                    point: {
                        radius: 3,
                        hoverRadius: 6
                    },
                    line: {
                        borderWidth: 2
                    }
                }
            }
        });
        
        console.log('Chart initialized successfully');
        setTimeout(() => {
            updateChart();
        }, 500);
        
    } catch (error) {
        console.error('Error initializing chart:', error);
        showMessage('Error initializing chart. Please refresh the page.', 'error');
    }
}

// Update chart
async function updateChart() {
    if (!sensorChart) {
        console.warn('Chart not initialized');
        return;
    }
    
    const timeRange = document.getElementById('timeRange')?.value || 'all';
    
    try {
        const response = await fetch(`api/sensor_data.php?action=chart&range=${timeRange}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.data && Array.isArray(data.data) && data.data.length > 0) {
            const labels = data.data.map(item => {
                const date = new Date(item.reading_timestamp);
                if (timeRange === '24h') {
                    return date.toLocaleTimeString('en-US', { 
                        hour: '2-digit', 
                        minute: '2-digit' 
                    });
                } else if (timeRange === '7d') {
                    return date.toLocaleDateString('en-US', { 
                        month: 'short', 
                        day: 'numeric',
                        hour: '2-digit'
                    });
                } else if (timeRange === '30d') {
                    return date.toLocaleDateString('en-US', { 
                        month: 'short', 
                        day: 'numeric' 
                    });
                } else {
                    return date.toLocaleDateString('en-US', { 
                        month: 'short', 
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }
            });
            
            sensorChart.data.labels = labels;
            sensorChart.data.datasets[0].data = data.data.map(item => {
                const val = parseFloat(item.ph_level);
                return isNaN(val) ? 0 : val;
            });
            sensorChart.data.datasets[1].data = data.data.map(item => {
                const val = parseFloat(item.tds_value);
                return isNaN(val) ? 0 : val;
            });
            sensorChart.data.datasets[2].data = data.data.map(item => {
                const val = parseFloat(item.ec_value);
                return isNaN(val) ? 0 : val;
            });
            sensorChart.data.datasets[3].data = data.data.map(item => {
                const val = parseFloat(item.turbidity);
                return isNaN(val) ? 0 : val;
            });
            
            sensorChart.update('active');
            
        } else {
            sensorChart.data.labels = ['No Data'];
            sensorChart.data.datasets.forEach(dataset => {
                dataset.data = [0];
            });
            sensorChart.update();
        }
    } catch (error) {
        console.error('Error updating chart:', error);
    }
}

// Export data
async function exportData() {
    try {
        showMessage('Preparing export...', 'info');
        
        const timeRange = document.getElementById('timeRange').value;
        const response = await fetch(`api/export_data.php?range=${timeRange}&format=csv`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const contentType = response.headers.get('content-type');
        
        if (contentType && contentType.includes('application/json')) {
            const errorData = await response.json();
            showMessage(errorData.message || 'Export failed', 'error');
            return;
        }
        
        const blob = await response.blob();
        
        if (blob.size === 0) {
            showMessage('No data available to export.', 'warning');
            return;
        }
        
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `aqualitics_data_${timeRange}_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
        
        showMessage('Data exported successfully!', 'success');
        
    } catch (error) {
        console.error('Export error:', error);
        showMessage('Export failed. Please try again.', 'error');
    }
}

// Import data
function importData() {
    document.getElementById('importFile').click();
}

// Handle file import
async function handleFileImport(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    const formData = new FormData();
    formData.append('file', file);
    
    try {
        showMessage('Importing data...', 'info');
        
        const response = await fetch('api/import_data.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage(`Import successful! ${data.imported} records imported.`, 'success');
            loadTableData();
            loadInitialData();
            updateChart();
        } else {
            showMessage(data.message, 'error');
        }
    } catch (error) {
        console.error('Import error:', error);
        showMessage('Import failed. Please try again.', 'error');
    }
    
    e.target.value = '';
}

// Utility functions
function formatNumber(num) {
    if (num === null || num === undefined || isNaN(num)) return '0.00';
    return Number(num).toFixed(2);
}

function formatDateTime(dateTimeString) {
    const date = new Date(dateTimeString);
    return date.toLocaleString();
}

// Show message function
function showMessage(message, type) {
    let messageDiv = document.getElementById('dashboardMessage');
    if (!messageDiv) {
        messageDiv = document.createElement('div');
        messageDiv.id = 'dashboardMessage';
        messageDiv.className = 'dashboard-message';
        document.querySelector('.dashboard-container').prepend(messageDiv);
    }
    
    messageDiv.textContent = message;
    messageDiv.className = `dashboard-message message ${type}`;
    messageDiv.style.display = 'block';
    
    setTimeout(() => {
        messageDiv.style.display = 'none';
    }, 5000);
}

function resetSensorCardsToZero() {
    const defaultData = {
        ph_level: 0,
        tds_value: 0,
        ec_value: 0,
        turbidity: 0,
        temperature: 0
    };
    updateSensorCards(defaultData);
    
    // Update status to show "No Data"
    ['ph', 'tds', 'ec', 'turbidity'].forEach(sensor => {
        const statusEl = document.getElementById(`${sensor}Status`);
        if (statusEl) {
            statusEl.textContent = 'No Data';
            statusEl.className = 'sensor-status';
        }
    });
}

// NEW: Setup auto-disconnect when browser/tab closes
function setupAutoDisconnect() {
    // Method 1: beforeunload - fires when page is about to close/refresh
    window.addEventListener('beforeunload', function(e) {
        if (isConnectedToDevice) {
            // ⚠️ REFRESH WARNING: Show confirmation dialog
            const confirmationMessage = '⚠️ You are currently connected to an IoT device. Refreshing or leaving this page will disconnect you. Do you want to continue?';
            e.preventDefault();
            e.returnValue = confirmationMessage; // This triggers the browser's confirmation dialog
            
            // NOTE: The disconnect will only happen if user confirms to leave
            // The code below runs regardless, but if user cancels, the page stays and connection remains
            // We rely on pagehide/unload events for actual disconnection
            
            return confirmationMessage; // For older browsers
        }
    });
    
    // Method 2: pagehide - more reliable on mobile/modern browsers
    // This ONLY fires when page actually unloads (user confirmed to leave)
    window.addEventListener('pagehide', function(e) {
        if (isConnectedToDevice) {
            try {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'api/device_pairing.php?action=disconnect', false);
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.send(JSON.stringify({}));
                console.log('Pagehide disconnect sent');
            } catch (error) {
                console.error('Pagehide disconnect failed:', error);
            }
        }
    });
    
    // Method 3: unload - final attempt
    // This ONLY fires when page actually unloads (user confirmed to leave)
    window.addEventListener('unload', function(e) {
        if (isConnectedToDevice) {
            try {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'api/device_pairing.php?action=disconnect', false);
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.send(JSON.stringify({}));
                console.log('Unload disconnect sent');
            } catch (error) {
                // Silent fail on unload
            }
        }
    });
    
    // Also handle visibility change (tab switching)
    document.addEventListener('visibilitychange', function() {
        if (document.hidden && isConnectedToDevice) {
            console.log('Tab hidden - connection maintained');
        } else if (!document.hidden && isConnectedToDevice) {
            console.log('Tab visible - checking device status...');
            checkConnectionStatus();
        }
    });
    
    console.log('✓ Auto-disconnect on browser close enabled (3 methods + refresh warning active)');
}

// NEW: User heartbeat - tells server "I'm still here"
function startUserHeartbeat() {
    // Send heartbeat every 20 seconds
    userHeartbeatInterval = setInterval(() => {
        if (isConnectedToDevice) {
            sendUserHeartbeat();
        }
    }, 30000);
    
    console.log('✓ User heartbeat system started');
}

// NEW: Send heartbeat to keep session alive
async function sendUserHeartbeat() {
    try {
        const response = await fetch('api/device_pairing.php?action=user_heartbeat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (!data.success) {
            console.warn('Heartbeat failed - may have been disconnected');
            // Check connection status
            checkConnectionStatus();
        }
    } catch (error) {
        console.error('Heartbeat error:', error);
    }
}