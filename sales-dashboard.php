<?php
// Start the session
session_start();

// Include the enhanced session manager
require_once 'android_session_manager.php';
$sessionManager = new AndroidSessionManager();

// Let the session manager handle Android session persistence
$sessionManager->validateAndroidSession();

// Include the database connection file
require 'db_connection.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';
$current_profile_url = '';

// Get user role
$role_sql = "SELECT role FROM users WHERE id = ?";
$role_stmt = $conn->prepare($role_sql);
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_stmt->bind_result($role);
$role_stmt->fetch();
$role_stmt->close();

// Check if user is a sales person
if ($role !== 'sales_person') {
    header("Location: dashboard.php");
    exit();
}

// Fetch sales data for charts
$sales_data = [];
$leads_data = [];
$conversion_data = [];

// Get today's sales
$today_sales_sql = "SELECT COUNT(*) as count, COALESCE(SUM(package_price), 0) as total 
                   FROM sales_track 
                   WHERE user_id = ? AND DATE(record_date) = CURDATE() AND status = 'completed'";
$today_stmt = $conn->prepare($today_sales_sql);
$today_stmt->bind_param("i", $user_id);
$today_stmt->execute();
$today_result = $today_stmt->get_result();
$today_data = $today_result->fetch_assoc();
$today_sales_count = $today_data['count'];
$today_sales_total = $today_data['total'];
$today_stmt->close();

// Get monthly sales data for chart
$monthly_sales_sql = "SELECT 
                        DATE_FORMAT(record_date, '%Y-%m') as month,
                        COUNT(*) as count,
                        COALESCE(SUM(package_price), 0) as total
                      FROM sales_track 
                      WHERE user_id = ? AND status = 'completed'
                      GROUP BY DATE_FORMAT(record_date, '%Y-%m')
                      ORDER BY month DESC
                      LIMIT 6";
$monthly_stmt = $conn->prepare($monthly_sales_sql);
$monthly_stmt->bind_param("i", $user_id);
$monthly_stmt->execute();
$monthly_result = $monthly_stmt->get_result();
$monthly_sales = [];
while ($row = $monthly_result->fetch_assoc()) {
    $monthly_sales[] = $row;
}
$monthly_stmt->close();

// Get leads data by status
$leads_sql = "SELECT status, COUNT(*) as count 
              FROM sales_track 
              WHERE user_id = ?
              GROUP BY status";
$leads_stmt = $conn->prepare($leads_sql);
$leads_stmt->bind_param("i", $user_id);
$leads_stmt->execute();
$leads_result = $leads_stmt->get_result();
$leads_by_status = [];
while ($row = $leads_result->fetch_assoc()) {
    $leads_by_status[$row['status']] = $row['count'];
}
$leads_stmt->close();

// Get conversion rate data
$conversion_sql = "SELECT 
                    COUNT(*) as total_leads,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as converted_leads
                   FROM sales_track 
                   WHERE user_id = ?";
$conversion_stmt = $conn->prepare($conversion_sql);
$conversion_stmt->bind_param("i", $user_id);
$conversion_stmt->execute();
$conversion_result = $conversion_stmt->get_result();
$conversion_data = $conversion_result->fetch_assoc();
$conversion_stmt->close();

// Calculate conversion rate
$total_leads = $conversion_data['total_leads'] ?: 1; // Avoid division by zero
$converted_leads = $conversion_data['converted_leads'] ?: 0;
$conversion_rate = ($converted_leads / $total_leads) * 100;

// Get top performing locations
$locations_sql = "SELECT city, COUNT(*) as count 
                  FROM sales_track 
                  WHERE user_id = ? AND city IS NOT NULL
                  GROUP BY city 
                  ORDER BY count DESC 
                  LIMIT 5";
$locations_stmt = $conn->prepare($locations_sql);
$locations_stmt->bind_param("i", $user_id);
$locations_stmt->execute();
$locations_result = $locations_stmt->get_result();
$top_locations = [];
while ($row = $locations_result->fetch_assoc()) {
    $top_locations[] = $row;
}
$locations_stmt->close();

// Get recent sales
$recent_sales_sql = "SELECT restaurant_name, package_price, record_date, status 
                     FROM sales_track 
                     WHERE user_id = ? 
                     ORDER BY record_date DESC 
                     LIMIT 5";
$recent_stmt = $conn->prepare($recent_sales_sql);
$recent_stmt->bind_param("i", $user_id);
$recent_stmt->execute();
$recent_result = $recent_stmt->get_result();
$recent_sales = [];
while ($row = $recent_result->fetch_assoc()) {
    $recent_sales[] = $row;
}
$recent_stmt->close();

// Fetch user name
$sql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name);
$stmt->fetch();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Sales Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/jquery.validate.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .session-status-android {
            position: fixed;
            bottom: 10px;
            left: 10px;
            background: #28a745;
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            z-index: 10000;
            font-weight: bold;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .session-status-android.web {
            background: #17a2b8;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .progress {
            height: 10px;
        }
        
        .recent-sales-table {
            font-size: 0.9rem;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-in-process {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-not-interested {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>

<body>

    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php 
        // Include the appropriate menu based on user role
        if ($role === 'admin') {
            include 'admin_menu.php';
        } elseif ($role === 'sales_person') {
            include 'sales_menu.php';
        }
        ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Sales Dashboard</h4>
                                <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($user_name); ?>! Here's your sales performance overview.</p>
                            </div>
                        </div>
                        
                        <!-- Sales Quick Stats -->
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card stat-card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <h5>Today's Sales</h5>
                                        <h3>₹<?php echo number_format($today_sales_total); ?></h3>
                                        <p class="mb-0"><?php echo $today_sales_count; ?> deals</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h5>Total Leads</h5>
                                        <h3><?php echo $total_leads; ?></h3>
                                        <p class="mb-0">All time</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h5>Conversion Rate</h5>
                                        <h3><?php echo number_format($conversion_rate, 1); ?>%</h3>
                                        <p class="mb-0"><?php echo $converted_leads; ?> converted</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card bg-warning text-dark">
                                    <div class="card-body text-center">
                                        <h5>Active Leads</h5>
                                        <h3><?php echo isset($leads_by_status['in process']) ? $leads_by_status['in process'] : 0; ?></h3>
                                        <p class="mb-0">In process</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Charts Row -->
                        <div class="row mt-4">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Monthly Sales Performance</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="salesChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Leads by Status</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="leadsChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Additional Charts Row -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Top Performing Locations</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="locationsChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Monthly Conversion Rate</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="conversionChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Sales Table -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Recent Sales Activity</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover recent-sales-table">
                                                <thead>
                                                    <tr>
                                                        <th>Restaurant</th>
                                                        <th>Package Value</th>
                                                        <th>Date</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (count($recent_sales) > 0): ?>
                                                        <?php foreach ($recent_sales as $sale): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($sale['restaurant_name']); ?></td>
                                                                <td>₹<?php echo number_format($sale['package_price'], 2); ?></td>
                                                                <td><?php echo date('M j, Y', strtotime($sale['record_date'])); ?></td>
                                                                <td>
                                                                    <span class="status-badge status-<?php echo str_replace(' ', '-', strtolower($sale['status'])); ?>">
                                                                        <?php echo ucfirst($sale['status']); ?>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="4" class="text-center">No recent sales activity</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'footer.php'; ?>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
    $(document).ready(function() {
        // Monthly Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'bar',
            data: {
                labels: [<?php 
                    $labels = [];
                    foreach(array_reverse($monthly_sales) as $sale) {
                        $labels[] = "'" . date('M Y', strtotime($sale['month'] . '-01')) . "'";
                    }
                    echo implode(', ', $labels);
                ?>],
                datasets: [{
                    label: 'Sales Amount (₹)',
                    data: [<?php 
                        $data = [];
                        foreach(array_reverse($monthly_sales) as $sale) {
                            $data[] = $sale['total'];
                        }
                        echo implode(', ', $data);
                    ?>],
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }, {
                    label: 'Number of Deals',
                    data: [<?php 
                        $countData = [];
                        foreach(array_reverse($monthly_sales) as $sale) {
                            $countData[] = $sale['count'];
                        }
                        echo implode(', ', $countData);
                    ?>],
                    backgroundColor: 'rgba(255, 99, 132, 0.7)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1,
                    type: 'line',
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Sales Amount (₹)'
                        }
                    },
                    y1: {
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Deals'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
        
        // Leads by Status Chart
        const leadsCtx = document.getElementById('leadsChart').getContext('2d');
        const leadsChart = new Chart(leadsCtx, {
            type: 'doughnut',
            data: {
                labels: ['In Process', 'Completed', 'Not Interested'],
                datasets: [{
                    data: [
                        <?php echo isset($leads_by_status['in process']) ? $leads_by_status['in process'] : 0; ?>,
                        <?php echo isset($leads_by_status['completed']) ? $leads_by_status['completed'] : 0; ?>,
                        <?php echo isset($leads_by_status['not interested']) ? $leads_by_status['not interested'] : 0; ?>
                    ],
                    backgroundColor: [
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(255, 99, 132, 0.7)'
                    ],
                    borderColor: [
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(255, 99, 132, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Top Locations Chart
        const locationsCtx = document.getElementById('locationsChart').getContext('2d');
        const locationsChart = new Chart(locationsCtx, {
            type: 'bar',
            data: {
                labels: [<?php 
                    $locationLabels = [];
                    $locationData = [];
                    foreach($top_locations as $location) {
                        $locationLabels[] = "'" . $location['city'] . "'";
                        $locationData[] = $location['count'];
                    }
                    echo implode(', ', $locationLabels);
                ?>],
                datasets: [{
                    label: 'Number of Leads',
                    data: [<?php echo implode(', ', $locationData); ?>],
                    backgroundColor: 'rgba(153, 102, 255, 0.7)',
                    borderColor: 'rgba(153, 102, 255, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Conversion Rate Chart (Sample data - in a real app, you'd calculate this from your data)
        const conversionCtx = document.getElementById('conversionChart').getContext('2d');
        const conversionChart = new Chart(conversionCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Conversion Rate (%)',
                    data: [15, 22, 18, 25, 30, 28],
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Conversion Rate (%)'
                        }
                    }
                }
            }
        });
        
        // Session status indicator management
        function updateSessionStatus() {
            const indicator = $('#sessionStatusIndicator');
            if (indicator.length) {
                // Check session status every 30 seconds
                setInterval(() => {
                    $.get('session_health_check.php', function(data) {
                        if (data.status === 'success') {
                            console.log('Sales session active');
                        }
                    }).fail(() => {
                        indicator.removeClass('android web').addClass('warning');
                        indicator.text('⚠️ Connection Issue');
                    });
                }, 30000);
            }
        }

        // Android-specific session maintenance
        function androidSessionMaintenance() {
            // For Android apps, send periodic keep-alive requests
            if (navigator.userAgent.includes('WebToNative') || <?php echo $sessionManager->isAndroidApp() ? 'true' : 'false'; ?>) {
                setInterval(() => {
                    $.ajax({
                        url: 'heartbeat.php',
                        method: 'GET',
                        xhrFields: {
                            withCredentials: true
                        },
                        success: function(data) {
                            console.log('Sales Android session maintained');
                        }
                    });
                }, 300000); // Every 5 minutes for Android apps
            }
        }

        // Initialize session monitoring
        updateSessionStatus();
        androidSessionMaintenance();
    });
    </script>

</body>
</html>