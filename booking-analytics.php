<?php
// booking-analytics.php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();
date_default_timezone_set('Asia/Kolkata');

require_once 'android_session_manager.php';
$sessionManager = new AndroidSessionManager();
$sessionManager->validateAndroidSession();

require_once 'session_check.php';
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'User';

// Check if room tables exist
$check_table_sql = "SHOW TABLES LIKE 'bookings_$user_id'";
$table_result = $conn->query($check_table_sql);
if ($table_result->num_rows == 0) {
    header("Location: create_room_tables.php");
    exit();
}

// Get filter parameters
$filter_month = $_GET['month'] ?? date('Y-m');
$filter_year = $_GET['year'] ?? date('Y');
$filter_room_type = $_GET['room_type'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';

// Initialize analytics data
$analytics_data = [
    'overview' => [],
    'monthly_trends' => [],
    'room_type_analytics' => [],
    'occupancy_rates' => [],
    'revenue_metrics' => []
];

// 1. OVERVIEW STATISTICS
$overview_sql = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as active_stays,
    SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as upcoming_bookings,
    SUM(CASE WHEN status = 'checked_out' THEN 1 ELSE 0 END) as completed_stays,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
    SUM(total_amount) as total_revenue,
    AVG(total_amount) as avg_booking_value,
    COUNT(DISTINCT guest_phone) as unique_guests,
    SUM(total_nights) as total_nights_sold
FROM bookings_$user_id 
WHERE YEAR(check_in_date) = ?";

$overview_stmt = $conn->prepare($overview_sql);
$overview_stmt->bind_param("s", $filter_year);
$overview_stmt->execute();
$analytics_data['overview'] = $overview_stmt->get_result()->fetch_assoc();
$overview_stmt->close();

// 2. MONTHLY TRENDS (Last 12 months)
$monthly_trends_sql = "SELECT 
    DATE_FORMAT(check_in_date, '%Y-%m') as month,
    COUNT(*) as booking_count,
    SUM(total_amount) as monthly_revenue,
    AVG(total_amount) as avg_booking_value,
    SUM(total_nights) as total_nights,
    COUNT(DISTINCT guest_phone) as unique_guests,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancellations
FROM bookings_$user_id 
WHERE check_in_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
GROUP BY DATE_FORMAT(check_in_date, '%Y-%m')
ORDER BY month DESC";

$monthly_stmt = $conn->prepare($monthly_trends_sql);
$monthly_stmt->execute();
$monthly_result = $monthly_stmt->get_result();
while ($row = $monthly_result->fetch_assoc()) {
    $analytics_data['monthly_trends'][] = $row;
}
$monthly_stmt->close();

// 3. ROOM TYPE ANALYTICS
$room_type_sql = "SELECT 
    rt.name as room_type,
    COUNT(b.id) as booking_count,
    SUM(b.total_amount) as total_revenue,
    AVG(b.total_amount) as avg_revenue,
    SUM(b.total_nights) as total_nights,
    COUNT(DISTINCT b.guest_phone) as unique_guests,
    SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancellations
FROM bookings_$user_id b
LEFT JOIN rooms_$user_id r ON b.room_id = r.id
LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id
WHERE YEAR(b.check_in_date) = ?
GROUP BY rt.name
ORDER BY total_revenue DESC";

$room_type_stmt = $conn->prepare($room_type_sql);
$room_type_stmt->bind_param("s", $filter_year);
$room_type_stmt->execute();
$room_type_result = $room_type_stmt->get_result();
while ($row = $room_type_result->fetch_assoc()) {
    $analytics_data['room_type_analytics'][] = $row;
}
$room_type_stmt->close();

// 4. OCCUPANCY RATES (Monthly)
$occupancy_sql = "SELECT 
    DATE_FORMAT(check_in_date, '%Y-%m') as month,
    COUNT(*) as total_bookings,
    SUM(total_nights) as total_nights,
    (SELECT COUNT(*) FROM rooms_$user_id) as total_rooms,
    (SUM(total_nights) / (DAY(LAST_DAY(check_in_date)) * (SELECT COUNT(*) FROM rooms_$user_id))) * 100 as occupancy_rate
FROM bookings_$user_id 
WHERE status IN ('checked_in', 'checked_out')
    AND check_in_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
GROUP BY DATE_FORMAT(check_in_date, '%Y-%m')
ORDER BY month ASC";

$occupancy_stmt = $conn->prepare($occupancy_sql);
$occupancy_stmt->execute();
$occupancy_result = $occupancy_stmt->get_result();
while ($row = $occupancy_result->fetch_assoc()) {
    $analytics_data['occupancy_rates'][] = $row;
}
$occupancy_stmt->close();

// 5. REVENUE METRICS
$revenue_sql = "SELECT 
    DATE_FORMAT(check_in_date, '%Y-%m') as month,
    SUM(total_amount) as total_revenue,
    SUM(advance_paid) as advance_collected,
    SUM(total_amount - advance_paid) as pending_collection,
    AVG(total_amount) as avg_daily_rate,
    SUM(total_amount) / SUM(total_nights) as revpar
FROM bookings_$user_id 
WHERE status IN ('checked_in', 'checked_out')
    AND check_in_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
GROUP BY DATE_FORMAT(check_in_date, '%Y-%m')
ORDER BY month ASC";

$revenue_stmt = $conn->prepare($revenue_sql);
$revenue_stmt->execute();
$revenue_result = $revenue_stmt->get_result();
while ($row = $revenue_result->fetch_assoc()) {
    $analytics_data['revenue_metrics'][] = $row;
}
$revenue_stmt->close();

// 6. CANCELLATION ANALYSIS
$cancellation_sql = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as total_cancellations,
    SUM(total_amount) as lost_revenue,
    AVG(total_amount) as avg_cancelled_booking_value
FROM bookings_$user_id 
WHERE status = 'cancelled'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
GROUP BY DATE_FORMAT(created_at, '%Y-%m')
ORDER BY month ASC";

$cancellation_stmt = $conn->prepare($cancellation_sql);
$cancellation_stmt->execute();
$cancellation_data = $cancellation_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$cancellation_stmt->close();

// 7. GUEST ANALYSIS
$guest_sql = "SELECT 
    COUNT(DISTINCT guest_phone) as total_guests,
    COUNT(DISTINCT CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN guest_phone END) as new_guests_30d,
    COUNT(DISTINCT CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN guest_phone END) as returning_guests_90d,
    AVG(total_nights) as avg_stay_length
FROM bookings_$user_id 
WHERE status IN ('checked_in', 'checked_out')";

$guest_stmt = $conn->prepare($guest_sql);
$guest_stmt->execute();
$guest_data = $guest_stmt->get_result()->fetch_assoc();
$guest_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Booking Analytics - Room Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/luxon@3.0.4/build/global/luxon.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-luxon@1.3.1/dist/chartjs-adapter-luxon.min.js"></script>
    
    <style>
        .analytics-card {
            transition: all 0.3s ease;
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .analytics-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 500;
        }
        .trend-up { color: #28a745; }
        .trend-down { color: #dc3545; }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        .filter-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .metric-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            color: white;
            margin-bottom: 20px;
        }
        .metric-card.primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .metric-card.success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .metric-card.info { background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%); }
        .metric-card.warning { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: #000; }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .table-analytics {
            font-size: 0.85rem;
        }
        .table-analytics th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .progress-thin {
            height: 6px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'room_management_menu.php'; ?>

        <div class="page-content">
            <div class="container-fluid">
                <!-- Page Header -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <h4 class="page-title">📊 Booking Analytics</h4>
                            <p class="text-muted mb-4">Comprehensive analysis of your booking performance and revenue metrics</p>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="row">
                    <div class="col-12">
                        <div class="filter-section">
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label">Year</label>
                                    <select class="form-select" id="yearFilter" onchange="updateFilters()">
                                        <?php
                                        $current_year = date('Y');
                                        for ($year = $current_year; $year >= $current_year - 5; $year--) {
                                            $selected = $year == $filter_year ? 'selected' : '';
                                            echo "<option value='$year' $selected>$year</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Month</label>
                                    <select class="form-select" id="monthFilter" onchange="updateFilters()">
                                        <option value="all">All Months</option>
                                        <?php
                                        $months = [
                                            '01' => 'January', '02' => 'February', '03' => 'March',
                                            '04' => 'April', '05' => 'May', '06' => 'June',
                                            '07' => 'July', '08' => 'August', '09' => 'September',
                                            '10' => 'October', '11' => 'November', '12' => 'December'
                                        ];
                                        foreach ($months as $num => $name) {
                                            $selected = $filter_month == date('Y') . '-' . $num ? 'selected' : '';
                                            echo "<option value='" . date('Y') . "-$num' $selected>$name</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Export Data</label>
                                    <div>
                                        <button class="btn btn-outline-primary" onclick="exportToCSV()">
                                            <i class="fas fa-download me-1"></i> Export CSV
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Quick Actions</label>
                                    <div>
                                        <a href="room-dashboard.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Key Performance Indicators -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card analytics-card">
                            <div class="card-body text-center">
                                <h2 class="stat-number text-primary">
                                    <?php echo number_format($analytics_data['overview']['total_revenue'] ?? 0); ?>
                                </h2>
                                <p class="stat-label">Total Revenue (₹)</p>
                                <small class="text-success">
                                    <i class="fas fa-trend-up"></i>
                                    <?php echo number_format($analytics_data['overview']['avg_booking_value'] ?? 0); ?> avg booking
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card analytics-card">
                            <div class="card-body text-center">
                                <h2 class="stat-number text-success">
                                    <?php echo number_format($analytics_data['overview']['total_bookings'] ?? 0); ?>
                                </h2>
                                <p class="stat-label">Total Bookings</p>
                                <small class="text-info">
                                    <i class="fas fa-users"></i>
                                    <?php echo number_format($analytics_data['overview']['unique_guests'] ?? 0); ?> unique guests
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card analytics-card">
                            <div class="card-body text-center">
                                <h2 class="stat-number text-info">
                                    <?php echo number_format($analytics_data['overview']['total_nights_sold'] ?? 0); ?>
                                </h2>
                                <p class="stat-label">Total Nights Sold</p>
                                <small class="text-success">
                                    <i class="fas fa-moon"></i>
                                    <?php echo number_format($analytics_data['overview']['total_nights_sold'] / max($analytics_data['overview']['total_bookings'], 1), 1); ?> avg nights
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card analytics-card">
                            <div class="card-body text-center">
                                <h2 class="stat-number text-warning">
                                    <?php echo number_format($guest_data['total_guests'] ?? 0); ?>
                                </h2>
                                <p class="stat-label">Total Guests</p>
                                <small class="text-primary">
                                    <i class="fas fa-user-plus"></i>
                                    <?php echo number_format($guest_data['new_guests_30d'] ?? 0); ?> new (30d)
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 1 -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title">📈 Monthly Revenue & Bookings Trend</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="revenueBookingsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title">🏨 Room Type Performance</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="roomTypeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 2 -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title">📊 Occupancy Rates</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="occupancyChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title">💰 Revenue Metrics</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="revenueMetricsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Analytics Tables -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title">📋 Room Type Analytics</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-analytics">
                                        <thead>
                                            <tr>
                                                <th>Room Type</th>
                                                <th>Bookings</th>
                                                <th>Revenue</th>
                                                <th>Avg Rate</th>
                                                <th>Occupancy</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($analytics_data['room_type_analytics'] as $room_type): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($room_type['room_type'] ?? 'N/A'); ?></strong></td>
                                                    <td><?php echo number_format($room_type['booking_count'] ?? 0); ?></td>
                                                    <td>₹<?php echo number_format($room_type['total_revenue'] ?? 0); ?></td>
                                                    <td>₹<?php echo number_format($room_type['avg_revenue'] ?? 0); ?></td>
                                                    <td>
                                                        <div class="progress progress-thin">
                                                            <?php
                                                            $max_bookings = max(array_column($analytics_data['room_type_analytics'], 'booking_count'));
                                                            $percentage = $max_bookings > 0 ? ($room_type['booking_count'] / $max_bookings) * 100 : 0;
                                                            ?>
                                                            <div class="progress-bar bg-success" style="width: <?php echo $percentage; ?>%"></div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title">📅 Monthly Performance</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-analytics">
                                        <thead>
                                            <tr>
                                                <th>Month</th>
                                                <th>Bookings</th>
                                                <th>Revenue</th>
                                                <th>Avg Value</th>
                                                <th>Growth</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $previous_revenue = 0;
                                            foreach (array_reverse($analytics_data['monthly_trends']) as $index => $monthly): 
                                                $growth = $previous_revenue > 0 ? (($monthly['monthly_revenue'] - $previous_revenue) / $previous_revenue) * 100 : 0;
                                                $previous_revenue = $monthly['monthly_revenue'];
                                            ?>
                                                <tr>
                                                    <td><strong><?php echo date('M Y', strtotime($monthly['month'] . '-01')); ?></strong></td>
                                                    <td><?php echo number_format($monthly['booking_count']); ?></td>
                                                    <td>₹<?php echo number_format($monthly['monthly_revenue']); ?></td>
                                                    <td>₹<?php echo number_format($monthly['avg_booking_value']); ?></td>
                                                    <td>
                                                        <span class="<?php echo $growth >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                            <i class="fas fa-<?php echo $growth >= 0 ? 'trend-up' : 'trend-down'; ?>"></i>
                                                            <?php echo number_format($growth, 1); ?>%
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Guest Analytics -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card analytics-card">
                            <div class="card-header">
                                <h5 class="card-title">👥 Guest Analytics</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 text-center">
                                        <h3 class="text-primary"><?php echo number_format($guest_data['total_guests'] ?? 0); ?></h3>
                                        <p class="text-muted">Total Guests</p>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <h3 class="text-success"><?php echo number_format($guest_data['new_guests_30d'] ?? 0); ?></h3>
                                        <p class="text-muted">New Guests (30 days)</p>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <h3 class="text-info"><?php echo number_format($guest_data['returning_guests_90d'] ?? 0); ?></h3>
                                        <p class="text-muted">Returning Guests (90 days)</p>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <h3 class="text-warning"><?php echo number_format($guest_data['avg_stay_length'] ?? 0, 1); ?></h3>
                                        <p class="text-muted">Average Stay (nights)</p>
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
        // Initialize Charts when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
        });

        function initializeCharts() {
            // Revenue & Bookings Chart
            const revenueCtx = document.getElementById('revenueBookingsChart').getContext('2d');
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_map(function($item) {
                        return date('M Y', strtotime($item['month'] . '-01'));
                    }, array_reverse($analytics_data['monthly_trends']))); ?>,
                    datasets: [
                        {
                            label: 'Revenue (₹)',
                            data: <?php echo json_encode(array_map(function($item) {
                                return $item['monthly_revenue'];
                            }, array_reverse($analytics_data['monthly_trends']))); ?>,
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            yAxisID: 'y',
                            tension: 0.4
                        },
                        {
                            label: 'Bookings',
                            data: <?php echo json_encode(array_map(function($item) {
                                return $item['booking_count'];
                            }, array_reverse($analytics_data['monthly_trends']))); ?>,
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            yAxisID: 'y1',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: { display: true, text: 'Revenue (₹)' }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: { display: true, text: 'Bookings' },
                            grid: { drawOnChartArea: false }
                        }
                    }
                }
            });

            // Room Type Chart
            const roomTypeCtx = document.getElementById('roomTypeChart').getContext('2d');
            new Chart(roomTypeCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_map(function($item) {
                        return $item['room_type'];
                    }, $analytics_data['room_type_analytics'])); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_map(function($item) {
                            return $item['total_revenue'];
                        }, $analytics_data['room_type_analytics'])); ?>,
                        backgroundColor: [
                            '#28a745', '#007bff', '#ffc107', '#dc3545', 
                            '#6f42c1', '#e83e8c', '#fd7e14', '#20c997'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' },
                        title: { display: true, text: 'Revenue by Room Type' }
                    }
                }
            });

            // Occupancy Chart
            const occupancyCtx = document.getElementById('occupancyChart').getContext('2d');
            new Chart(occupancyCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(function($item) {
                        return date('M Y', strtotime($item['month'] . '-01'));
                    }, $analytics_data['occupancy_rates'])); ?>,
                    datasets: [{
                        label: 'Occupancy Rate %',
                        data: <?php echo json_encode(array_map(function($item) {
                            return round($item['occupancy_rate'] ?? 0, 2);
                        }, $analytics_data['occupancy_rates'])); ?>,
                        backgroundColor: 'rgba(23, 162, 184, 0.8)',
                        borderColor: '#17a2b8',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: { display: true, text: 'Occupancy Rate %' }
                        }
                    }
                }
            });

            // Revenue Metrics Chart
            const revenueMetricsCtx = document.getElementById('revenueMetricsChart').getContext('2d');
            new Chart(revenueMetricsCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(function($item) {
                        return date('M Y', strtotime($item['month'] . '-01'));
                    }, $analytics_data['revenue_metrics'])); ?>,
                    datasets: [
                        {
                            label: 'Total Revenue',
                            data: <?php echo json_encode(array_map(function($item) {
                                return $item['total_revenue'];
                            }, $analytics_data['revenue_metrics'])); ?>,
                            backgroundColor: 'rgba(40, 167, 69, 0.8)'
                        },
                        {
                            label: 'Advance Collected',
                            data: <?php echo json_encode(array_map(function($item) {
                                return $item['advance_collected'];
                            }, $analytics_data['revenue_metrics'])); ?>,
                            backgroundColor: 'rgba(255, 193, 7, 0.8)'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true, title: { display: true, text: 'Amount (₹)' } }
                    }
                }
            });
        }

        function updateFilters() {
            const year = document.getElementById('yearFilter').value;
            const month = document.getElementById('monthFilter').value;
            
            let url = `booking-analytics.php?year=${year}`;
            if (month !== 'all') {
                url += `&month=${month}`;
            }
            
            window.location.href = url;
        }

        function exportToCSV() {
            // Simple CSV export implementation
            const year = document.getElementById('yearFilter').value;
            window.open(`export_booking_analytics.php?year=${year}`, '_blank');
        }
    </script>
</body>
</html>