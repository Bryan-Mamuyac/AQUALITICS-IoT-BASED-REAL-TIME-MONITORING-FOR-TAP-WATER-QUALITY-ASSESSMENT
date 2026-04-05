// Global variables
let clientsData = [];
let systemChart = null;
let clientDataChart = null;
let selectedClientId = 'all';
let dataCurrentPage = 1;
let dataRecordsPerPage = 10;
let dataTotalRecords = 0;
let dataTotalPages = 0;
let dataSearchTerm = '';

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Admin Dashboard Initializing...');
    initializeEventListeners();
    loadDashboardStats();
    loadClients();
    initializeCharts();
    loadClientData();
    loadRecentActivity();
    startAutoRefresh();
});

// Initialize all event listeners
function initializeEventListeners() {
    const clientSelector = document.getElementById('clientSelector');
    const dataRecordsPerPage = document.getElementById('dataRecordsPerPage');
    const dataSearchInput = document.getElementById('dataSearchInput');
    const dataTimeRange = document.getElementById('dataTimeRange');
    
    if (clientSelector) {
        clientSelector.addEventListener('change', handleClientChange);
    }
    
    if (dataRecordsPerPage) {
        dataRecordsPerPage.addEventListener('change', handleRecordsPerPageChange);
    }
    
    if (dataSearchInput) {
        dataSearchInput.addEventListener('input', handleSearchChange);
    }
    
    if (dataTimeRange) {
        dataTimeRange.addEventListener('change', updateClientDataChart);
    }
    
    // Close modal when clicking outside
    window.addEventListener('click', function(e) {
        const modal = document.getElementById('clientModal');
        if (e.target === modal) {
            closeClientModal();
        }
    });
    
    console.log('Event listeners initialized');
}

// Load dashboard statistics
async function loadDashboardStats() {
    try {
        const response = await fetch('api/admin_api.php?action=stats');
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('totalClients').textContent = data.stats.total_clients || 0;
            document.getElementById('totalReadings').textContent = data.stats.total_readings || 0;
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

// Load all clients
async function loadClients() {
    try {
        const response = await fetch('api/admin_api.php?action=clients_with_data');
        const data = await response.json();
        
        if (data.success) {
            clientsData = data.clients;
            renderClientsTable(data.clients);
            populateClientSelector(data.clients);
        } else {
            showMessage('Error loading clients: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error loading clients:', error);
        showMessage('Error loading clients', 'error');
    }
}

// Render clients table - MODIFIED: Changed gender to location
function renderClientsTable(clients) {
    const tbody = document.getElementById('clientsTableBody');
    if (!tbody) return;
    
    if (!clients || clients.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="empty-cell">No clients found</td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = clients.map(client => `
        <tr>
            <td>${client.id}</td>
            <td>${escapeHtml(client.first_name)} ${escapeHtml(client.last_name)}</td>
            <td>${escapeHtml(client.email)}</td>
            <td>${escapeHtml(client.location)}</td>
            <td>
                <span class="status-badge ${client.is_verified ? 'status-verified' : 'status-pending'}">
                    ${client.is_verified ? 'Verified' : 'Pending'}
                </span>
            </td>
            <td>${formatDate(client.created_at)}</td>
            <td><span class="reading-count">${client.total_readings || 0}</span></td>
        </tr>
    `).join('');
}

// Populate client selector dropdown
function populateClientSelector(clients) {
    const selector = document.getElementById('clientSelector');
    if (!selector) return;
    
    const currentValue = selector.value;
    
    // Clear and add "All Clients" option
    selector.innerHTML = '<option value="all">All Clients</option>';
    
    // Add each client
    clients.forEach(client => {
        const option = document.createElement('option');
        option.value = client.id;
        option.textContent = `${client.first_name} ${client.last_name} (${client.total_readings || 0} readings)`;
        selector.appendChild(option);
    });
    
    // Restore previous selection if it exists
    if (currentValue && [...selector.options].some(opt => opt.value === currentValue)) {
        selector.value = currentValue;
    }
}

// Handle client selection change
function handleClientChange(e) {
    selectedClientId = e.target.value;
    dataCurrentPage = 1;
    loadClientData();
    updateClientDataChart();
}

// Handle records per page change
function handleRecordsPerPageChange(e) {
    dataRecordsPerPage = parseInt(e.target.value) || 10;
    dataCurrentPage = 1;
    loadClientData();
}

// Handle search input change
function handleSearchChange(e) {
    dataSearchTerm = e.target.value;
    dataCurrentPage = 1;
    // Debounce search
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(() => {
        loadClientData();
    }, 500);
}

// Load client data
async function loadClientData() {
    try {
        const url = `api/admin_api.php?action=client_data&client_id=${selectedClientId}&page=${dataCurrentPage}&limit=${dataRecordsPerPage}&search=${encodeURIComponent(dataSearchTerm)}`;
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            renderClientDataTable(data.data);
            updatePagination(data.pagination);
            updateDataStats(data.stats);
            updateDataInsights(data.stats);
        } else {
            showMessage('Error loading data: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error loading client data:', error);
        showMessage('Error loading client data', 'error');
    }
}

// Render client data table
function renderClientDataTable(data) {
    const tbody = document.getElementById('dataTableBody');
    if (!tbody) return;
    
    if (!data || data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="empty-cell">No sensor readings found</td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = data.map(row => `
        <tr>
            <td>${row.id}</td>
            <td>${escapeHtml(row.client_name || 'Unknown')}</td>
            <td>${formatDateTime(row.reading_timestamp)}</td>
            <td><span class="sensor-value ph-value">${formatNumber(row.ph_level)}</span></td>
            <td><span class="sensor-value tds-value">${formatNumber(row.tds_value)}</span></td>
            <td><span class="sensor-value ec-value">${formatNumber(row.ec_value)}</span></td>
            <td><span class="sensor-value turbidity-value">${formatNumber(row.turbidity)}</span></td>
            <td><span class="sensor-value temp-value">${formatNumber(row.temperature)}</span></td>
        </tr>
    `).join('');
}

// Update data statistics
function updateDataStats(stats) {
    if (!stats) return;
    
    document.getElementById('selectedClientReadings').textContent = stats.total_readings || 0;
    document.getElementById('avgPH').textContent = formatNumber(stats.avg_ph || 0);
    document.getElementById('avgTDS').textContent = formatNumber(stats.avg_tds || 0);
    document.getElementById('avgEC').textContent = formatNumber(stats.avg_ec || 0);
    document.getElementById('avgTurbidity').textContent = formatNumber(stats.avg_turbidity || 0);
}

// Update data insights
function updateDataInsights(stats) {
    if (!stats) return;
    
    // pH Quality
    const avgPH = parseFloat(stats.avg_ph || 0);
    let phStatus = 'Unknown';
    if (avgPH >= 6.5 && avgPH <= 8.5) {
        phStatus = '<span class="status-good">Good</span>';
    } else if (avgPH >= 6.0 && avgPH <= 9.0) {
        phStatus = '<span class="status-warning">Warning</span>';
    } else if (avgPH > 0) {
        phStatus = '<span class="status-danger">Poor</span>';
    }
    document.getElementById('phQualityStatus').innerHTML = phStatus;
    
    // TDS Quality
    const avgTDS = parseFloat(stats.avg_tds || 0);
    let tdsStatus = 'Unknown';
    if (avgTDS <= 300) {
        tdsStatus = '<span class="status-good">Good</span>';
    } else if (avgTDS <= 500) {
        tdsStatus = '<span class="status-warning">Warning</span>';
    } else if (avgTDS > 0) {
        tdsStatus = '<span class="status-danger">Poor</span>';
    }
    document.getElementById('tdsQualityStatus').innerHTML = tdsStatus;
    
    // EC Quality
    const avgEC = parseFloat(stats.avg_ec || 0);
    let ecStatus = 'Unknown';
    if (avgEC <= 600) {
        ecStatus = '<span class="status-good">Good</span>';
    } else if (avgEC <= 1000) {
        ecStatus = '<span class="status-warning">Warning</span>';
    } else if (avgEC > 0) {
        ecStatus = '<span class="status-danger">Poor</span>';
    }
    document.getElementById('ecQualityStatus').innerHTML = ecStatus;
    
    // Turbidity Quality
    const avgTurbidity = parseFloat(stats.avg_turbidity || 0);
    let turbidityStatus = 'Unknown';
    if (avgTurbidity <= 1) {
        turbidityStatus = '<span class="status-good">Good</span>';
    } else if (avgTurbidity <= 4) {
        turbidityStatus = '<span class="status-warning">Warning</span>';
    } else if (avgTurbidity > 0) {
        turbidityStatus = '<span class="status-danger">Poor</span>';
    }
    document.getElementById('turbidityQualityStatus').innerHTML = turbidityStatus;
    
    // Data Frequency
    const totalReadings = parseInt(stats.total_readings || 0);
    const dateRangeDays = parseInt(stats.date_range_days || 0);
    let frequency = 'No data';
    if (totalReadings > 0 && dateRangeDays > 0) {
        const readingsPerDay = totalReadings / dateRangeDays;
        frequency = `${readingsPerDay.toFixed(1)} readings/day`;
    }
    document.getElementById('dataFrequency').textContent = frequency;
}

// Update pagination
function updatePagination(pagination) {
    if (!pagination) return;
    
    dataTotalRecords = pagination.total;
    dataTotalPages = pagination.pages;
    dataCurrentPage = pagination.current;
    
    const pageInfo = document.getElementById('dataPageInfo');
    const prevBtn = document.getElementById('dataPrevPage');
    const nextBtn = document.getElementById('dataNextPage');
    
    if (pageInfo) {
        pageInfo.textContent = `Page ${dataCurrentPage} of ${dataTotalPages}`;
    }
    
    if (prevBtn) {
        prevBtn.disabled = dataCurrentPage <= 1;
    }
    
    if (nextBtn) {
        nextBtn.disabled = dataCurrentPage >= dataTotalPages;
    }
}

// Change page
function changeDataPage(direction) {
    const newPage = dataCurrentPage + direction;
    if (newPage >= 1 && newPage <= dataTotalPages) {
        dataCurrentPage = newPage;
        loadClientData();
    }
}

// Export client data
async function exportClientData() {
    try {
        showMessage('Preparing export...', 'info');
        
        const timeRange = document.getElementById('dataTimeRange').value;
        const url = `api/admin_api.php?action=export_client_data&client_id=${selectedClientId}&range=${timeRange}`;
        
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error('Export failed');
        }
        
        const contentType = response.headers.get('content-type');
        
        if (contentType && contentType.includes('application/json')) {
            const errorData = await response.json();
            showMessage(errorData.message || 'Export failed', 'error');
            return;
        }
        
        const blob = await response.blob();
        
        if (blob.size === 0) {
            showMessage('No data available to export', 'warning');
            return;
        }
        
        // Create download link
        const url2 = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url2;
        const clientName = selectedClientId === 'all' ? 'all_clients' : `client_${selectedClientId}`;
        const date = new Date().toISOString().split('T')[0];
        link.download = `aqualitics_${clientName}_${timeRange}_${date}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url2);
        
        showMessage('Data exported successfully!', 'success');
        
    } catch (error) {
        console.error('Export error:', error);
        showMessage('Export failed. Please try again.', 'error');
    }
}

// View client details - MODIFIED: Show location instead of gender
async function viewClient(clientId) {
    try {
        const response = await fetch(`api/admin_api.php?action=client_details&id=${clientId}`);
        const data = await response.json();
        
        if (data.success) {
            showClientModal(data.client, data.readings);
        } else {
            showMessage('Error loading client details', 'error');
        }
    } catch (error) {
        console.error('Error loading client details:', error);
        showMessage('Error loading client details', 'error');
    }
}

// Show client modal - MODIFIED: Display location instead of gender
function showClientModal(client, readings) {
    const modal = document.getElementById('clientModal');
    const modalContent = document.getElementById('modalContent');
    
    if (!modal || !modalContent) return;
    
    modalContent.innerHTML = `
        <h3>Client Details</h3>
        <div class="client-info">
            <p><strong>ID:</strong> ${client.id}</p>
            <p><strong>Name:</strong> ${escapeHtml(client.first_name)} ${escapeHtml(client.last_name)}</p>
            <p><strong>Email:</strong> ${escapeHtml(client.email)}</p>
            <p><strong>Location:</strong> ${escapeHtml(client.location)}</p>
            <p><strong>Status:</strong> ${client.is_verified ? 'Verified' : 'Pending'}</p>
            <p><strong>Joined:</strong> ${formatDate(client.created_at)}</p>
        </div>
        <h4>Recent Readings (Last 10)</h4>
        <div class="readings-table">
            ${readings && readings.length > 0 ? `
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>pH</th>
                            <th>TDS</th>
                            <th>EC</th>
                            <th>Turbidity</th>
                            <th>Temp</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${readings.map(reading => `
                            <tr>
                                <td>${formatDateTime(reading.reading_timestamp)}</td>
                                <td>${formatNumber(reading.ph_level)}</td>
                                <td>${formatNumber(reading.tds_value)}</td>
                                <td>${formatNumber(reading.ec_value)}</td>
                                <td>${formatNumber(reading.turbidity)}</td>
                                <td>${formatNumber(reading.temperature)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            ` : '<p>No recent readings</p>'}
        </div>
    `;
    
    modal.style.display = 'block';
}

// Close client modal
function closeClientModal() {
    const modal = document.getElementById('clientModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Select client data
function selectClientData(clientId) {
    const clientSelector = document.getElementById('clientSelector');
    if (clientSelector) {
        clientSelector.value = clientId;
        selectedClientId = clientId;
        dataCurrentPage = 1;
        
        // Scroll to data section
        const dataSection = document.querySelector('.admin-section');
        if (dataSection) {
            dataSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        loadClientData();
        updateClientDataChart();
        
        const client = clientsData.find(c => c.id == clientId);
        if (client) {
            showMessage(`Viewing data for ${client.first_name} ${client.last_name}`, 'success');
        }
    }
}

// Initialize charts
function initializeCharts() {
    initializeClientDataChart();
    initializeSystemChart();
}

// Initialize client data chart
function initializeClientDataChart() {
    const ctx = document.getElementById('clientDataChart');
    if (!ctx) return;
    
    clientDataChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'pH Level',
                    data: [],
                    borderColor: '#fbbf24',
                    backgroundColor: 'rgba(251, 191, 36, 0.1)',
                    tension: 0.4,
                    pointRadius: 3,
                    borderWidth: 2
                },
                {
                    label: 'TDS (ppm)',
                    data: [],
                    borderColor: '#60a5fa',
                    backgroundColor: 'rgba(96, 165, 250, 0.1)',
                    tension: 0.4,
                    pointRadius: 3,
                    borderWidth: 2
                },
                {
                    label: 'EC (μS/cm)',
                    data: [],
                    borderColor: '#6ee7b7',
                    backgroundColor: 'rgba(110, 231, 183, 0.1)',
                    tension: 0.4,
                    pointRadius: 3,
                    borderWidth: 2
                },
                {
                    label: 'Turbidity (NTU)',
                    data: [],
                    borderColor: '#c084fc',
                    backgroundColor: 'rgba(192, 132, 252, 0.1)',
                    tension: 0.4,
                    pointRadius: 3,
                    borderWidth: 2
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
                        padding: 15,
                        color: '#cbd5e1',
                        font: {
                            weight: '600'
                        }
                    }
                },
                title: {
                    display: true,
                    text: 'Water Quality Data Over Time',
                    color: '#60a5fa',
                    font: {
                        size: 14,
                        weight: '600'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(148, 163, 184, 0.1)'
                    },
                    ticks: {
                        color: '#cbd5e1'
                    },
                    title: {
                        display: true,
                        text: 'Values',
                        color: '#cbd5e1'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(148, 163, 184, 0.1)'
                    },
                    ticks: {
                        color: '#cbd5e1'
                    },
                    title: {
                        display: true,
                        text: 'Time',
                        color: '#cbd5e1'
                    }
                }
            }
        }
    });
    
    updateClientDataChart();
}

// Update client data chart
async function updateClientDataChart() {
    if (!clientDataChart) return;
    
    const timeRange = document.getElementById('dataTimeRange')?.value || 'all';
    
    try {
        const response = await fetch(`api/admin_api.php?action=client_chart_data&client_id=${selectedClientId}&range=${timeRange}`);
        const data = await response.json();
        
        if (data.success && data.data && data.data.length > 0) {
            const labels = data.data.map(item => {
                const date = new Date(item.reading_timestamp);
                if (timeRange === '24h') {
                    return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                } else if (timeRange === '7d') {
                    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit' });
                } else if (timeRange === '30d') {
                    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                } else {
                    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit' });
                }
            });
            
            clientDataChart.data.labels = labels;
            clientDataChart.data.datasets[0].data = data.data.map(item => parseFloat(item.ph_level) || 0);
            clientDataChart.data.datasets[1].data = data.data.map(item => parseFloat(item.tds_value) || 0);
            clientDataChart.data.datasets[2].data = data.data.map(item => parseFloat(item.ec_value) || 0);
            clientDataChart.data.datasets[3].data = data.data.map(item => parseFloat(item.turbidity) || 0);
            
            clientDataChart.update();
        } else {
            clientDataChart.data.labels = ['No Data'];
            clientDataChart.data.datasets.forEach(dataset => {
                dataset.data = [0];
            });
            clientDataChart.update();
        }
    } catch (error) {
        console.error('Error updating chart:', error);
    }
}

// Initialize system chart
function initializeSystemChart() {
    const ctx = document.getElementById('systemChart');
    if (!ctx) return;
    
    systemChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'Daily Readings',
                    data: [],
                    borderColor: '#60a5fa',
                    backgroundColor: 'rgba(96, 165, 250, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2
                },
                {
                    label: 'New Users',
                    data: [],
                    borderColor: '#6ee7b7',
                    backgroundColor: 'rgba(110, 231, 183, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        color: '#cbd5e1',
                        font: {
                            weight: '600'
                        }
                    }
                },
                title: {
                    display: true,
                    text: 'System Activity (Last 7 Days)',
                    color: '#60a5fa',
                    font: {
                        size: 14,
                        weight: '600'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(148, 163, 184, 0.1)'
                    },
                    ticks: {
                        color: '#cbd5e1'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(148, 163, 184, 0.1)'
                    },
                    ticks: {
                        color: '#cbd5e1'
                    }
                }
            }
        }
    });
    
    loadSystemChartData();
}

// Load system chart data
async function loadSystemChartData() {
    if (!systemChart) return;
    
    try {
        const response = await fetch('api/admin_api.php?action=chart_data');
        const data = await response.json();
        
        if (data.success) {
            systemChart.data.labels = data.chart_data.labels;
            systemChart.data.datasets[0].data = data.chart_data.readings;
            systemChart.data.datasets[1].data = data.chart_data.users;
            systemChart.update();
        }
    } catch (error) {
        console.error('Error loading system chart:', error);
    }
}

// Load recent activity
async function loadRecentActivity() {
    try {
        const response = await fetch('api/admin_api.php?action=recent_activity');
        const data = await response.json();
        
        const activityLog = document.getElementById('activityLog');
        if (!activityLog) return;
        
        if (data.success && data.activities && data.activities.length > 0) {
            activityLog.innerHTML = data.activities.map(activity => `
                <div class="activity-item">
                    <div class="activity-description">${escapeHtml(activity.description)}</div>
                    <div class="activity-time">${formatDateTime(activity.timestamp)}</div>
                </div>
            `).join('');
        } else {
            activityLog.innerHTML = '<div class="no-activity">No recent activity</div>';
        }
    } catch (error) {
        console.error('Error loading activity:', error);
    }
}

// Start auto refresh
function startAutoRefresh() {
    // Refresh stats every 2 minutes
    setInterval(loadDashboardStats, 120000);
    
    // Refresh activity every 5 minutes
    setInterval(loadRecentActivity, 300000);
    
    // Refresh system chart every 10 minutes
    setInterval(loadSystemChartData, 600000);
}

// Show message
function showMessage(message, type = 'info') {
    let messageDiv = document.getElementById('adminMessage');
    
    if (!messageDiv) {
        messageDiv = document.createElement('div');
        messageDiv.id = 'adminMessage';
        messageDiv.className = 'admin-message';
        const container = document.querySelector('.admin-container');
        if (container) {
            container.insertBefore(messageDiv, container.firstChild);
        }
    }
    
    messageDiv.textContent = message;
    messageDiv.className = `admin-message message-${type}`;
    messageDiv.style.display = 'block';
    
    setTimeout(() => {
        messageDiv.style.display = 'none';
    }, 5000);
}

// Utility Functions
function formatNumber(num) {
    const number = parseFloat(num);
    return isNaN(number) ? '0.00' : number.toFixed(2);
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatDateTime(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}