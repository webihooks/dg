<?php
// revenue-report.php
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

// Initialize filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month
$room_type = $_GET['room_type'] ?? 'all';
$payment_status = $_GET['payment_status'] ?? 'all';

// Build WHERE clause for filters
$where_conditions = ["b.status IN ('checked_in', 'checked_out')"];
$params = [];
$param_types = "";

if ($start_date && $end_date) {
    $where_conditions[] = "b.check_in_date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $param_types .= "ss";
}

if ($room_type !== 'all') {
    $where_conditions[] = "r.room_type_id = ?";
    $params[] = $room_type;
    $param_types .= "i";
}

if ($payment_status !== 'all') {
    $where_conditions[] = "b.payment_status = ?";
    $params[] = $payment_status;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Get room types for filter dropdown
$room_types_sql = "SELECT id, name FROM room_types_$user_id WHERE is_active = 1 ORDER BY name";
$room_types_result = $conn->query($room_types_sql);
$room_types = [];
while ($row = $room_types_result->fetch_assoc()) {
    $room_types[] = $row;
}

// Revenue Summary
$summary_sql = "SELECT 
    COUNT(*) as total_bookings,
    SUM(b.total_amount) as total_revenue,
    SUM(b.advance_paid) as total_advance,
    SUM(b.tax_amount) as total_tax,
    SUM(b.discount_amount) as total_discount,
    AVG(b.total_amount) as avg_booking_value,
    SUM(b.total_nights) as total_nights,
    COUNT(DISTINCT b.guest_phone) as unique_guests
FROM bookings_$user_id b
LEFT JOIN rooms_$user_id r ON b.room_id = r.id
WHERE $where_clause";

$stmt = $conn->prepare($summary_sql);
if ($params) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$summary_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Daily Revenue Trend
$daily_revenue_sql = "SELECT 
    DATE(b.check_in_date) as date,
    COUNT(*) as bookings_count,
    SUM(b.total_amount) as daily_revenue,
    SUM(b.total_nights) as nights_sold
FROM bookings_$user_id b
LEFT JOIN rooms_$user_id r ON b.room_id = r.id
WHERE $where_clause
GROUP BY DATE(b.check_in_date)
ORDER BY date ASC";

$stmt = $conn->prepare($daily_revenue_sql);
if ($params) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$daily_revenue_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Revenue by Room Type
$room_type_revenue_sql = "SELECT 
    rt.name as room_type,
    COUNT(*) as bookings_count,
    SUM(b.total_amount) as revenue,
    AVG(b.total_amount) as avg_revenue,
    SUM(b.total_nights) as nights_sold
FROM bookings_$user_id b
LEFT JOIN rooms_$user_id r ON b.room_id = r.id
LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id
WHERE $where_clause
GROUP BY rt.id, rt.name
ORDER BY revenue DESC";

$stmt = $conn->prepare($room_type_revenue_sql);
if ($params) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$room_type_revenue_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Payment Status Summary
$payment_summary_sql = "SELECT 
    payment_status,
    COUNT(*) as bookings_count,
    SUM(total_amount) as total_amount,
    SUM(advance_paid) as advance_paid
FROM bookings_$user_id b
LEFT JOIN rooms_$user_id r ON b.room_id = r.id
WHERE $where_clause
GROUP BY payment_status
ORDER BY total_amount DESC";

$stmt = $conn->prepare($payment_summary_sql);
if ($params) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$payment_summary_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Top Performing Rooms
$top_rooms_sql = "SELECT 
    r.room_number,
    rt.name as room_type,
    COUNT(*) as bookings_count,
    SUM(b.total_amount) as revenue,
    SUM(b.total_nights) as nights_occupied,
    AVG(b.total_amount) as avg_revenue
FROM bookings_$user_id b
LEFT JOIN rooms_$user_id r ON b.room_id = r.id
LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id
WHERE $where_clause
GROUP BY r.id, r.room_number, rt.name
ORDER BY revenue DESC
LIMIT 10";

$stmt = $conn->prepare($top_rooms_sql);
if ($params) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$top_rooms_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Monthly Comparison (last 6 months)
$monthly_comparison_sql = "SELECT 
    DATE_FORMAT(check_in_date, '%Y-%m') as month,
    COUNT(*) as bookings_count,
    SUM(total_amount) as revenue,
    SUM(total_nights) as nights_sold,
    AVG(total_amount) as avg_booking_value
FROM bookings_$user_id 
WHERE status IN ('checked_in', 'checked_out')
    AND check_in_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
GROUP BY DATE_FORMAT(check_in_date, '%Y-%m')
ORDER BY month DESC
LIMIT 6";

$monthly_comparison_result = $conn->query($monthly_comparison_sql);
$monthly_comparison_data = $monthly_comparison_result->fetch_all(MYSQLI_ASSOC);
$monthly_comparison_result->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Revenue Report - Room Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/webtonative@1.0.77/webtonative.min.js"></script>
    
    <style>
        .card-summary {
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        .card-summary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .revenue-card { border-left-color: #28a745; }
        .bookings-card { border-left-color: #007bff; }
        .nights-card { border-left-color: #6f42c1; }
        .guests-card { border-left-color: #fd7e14; }
        
        .filter-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 0;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
        }
        
        .revenue-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
        }
        
        @media (max-width: 768px) {
            .stat-number {
                font-size: 1.5rem;
            }
            
            .chart-container {
                height: 250px;
            }
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
                            <h4 class="page-title">Revenue Report</h4>
                            <div class="page-title-right">
                                <button class="btn btn-success" onclick="exportToExcel()">
                                    <i class="fas fa-download me-1"></i> Export Excel
                                </button>
                                <button class="btn btn-primary" onclick="window.print()">
                                    <i class="fas fa-print me-1"></i> Print
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters Section -->
                <div class="row">
                    <div class="col-12">
                        <div class="filter-section">
                            <form method="GET" id="filterForm">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Start Date</label>
                                        <input type="date" class="form-control" name="start_date" 
                                               value="<?php echo $start_date; ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">End Date</label>
                                        <input type="date" class="form-control" name="end_date" 
                                               value="<?php echo $end_date; ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Room Type</label>
                                        <select class="form-select" name="room_type">
                                            <option value="all">All Room Types</option>
                                            <?php foreach ($room_types as $type): ?>
                                                <option value="<?php echo $type['id']; ?>" 
                                                    <?php echo $room_type == $type['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($type['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Payment Status</label>
                                        <select class="form-select" name="payment_status">
                                            <option value="all" <?php echo $payment_status == 'all' ? 'selected' : ''; ?>>All Status</option>
                                            <option value="paid" <?php echo $payment_status == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                            <option value="pending" <?php echo $payment_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="partial" <?php echo $payment_status == 'partial' ? 'selected' : ''; ?>>Partial</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-filter me-1"></i> Apply Filters
                                        </button>
                                        <a href="revenue-report.php" class="btn btn-secondary">
                                            <i class="fas fa-redo me-1"></i> Reset
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row">
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-summary revenue-card">
                            <div class="card-body text-center">
                                <p class="stat-number text-success">₹<?php echo number_format($summary_data['total_revenue'] ?? 0); ?></p>
                                <p class="stat-label">Total Revenue</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-summary bookings-card">
                            <div class="card-body text-center">
                                <p class="stat-number text-primary"><?php echo $summary_data['total_bookings'] ?? 0; ?></p>
                                <p class="stat-label">Total Bookings</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-summary nights-card">
                            <div class="card-body text-center">
                                <p class="stat-number text-primary"><?php echo $summary_data['total_nights'] ?? 0; ?></p>
                                <p class="stat-label">Nights Sold</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-summary guests-card">
                            <div class="card-body text-center">
                                <p class="stat-number text-warning"><?php echo $summary_data['unique_guests'] ?? 0; ?></p>
                                <p class="stat-label">Unique Guests</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <p class="stat-number text-info">₹<?php echo number_format($summary_data['avg_booking_value'] ?? 0); ?></p>
                                <p class="stat-label">Avg. Booking Value</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <p class="stat-number text-success">₹<?php echo number_format($summary_data['total_advance'] ?? 0); ?></p>
                                <p class="stat-label">Advance Collected</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <p class="stat-number text-danger">₹<?php echo number_format($summary_data['total_tax'] ?? 0); ?></p>
                                <p class="stat-label">Tax Collected</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <p class="stat-number text-warning">₹<?php echo number_format($summary_data['total_discount'] ?? 0); ?></p>
                                <p class="stat-label">Discount Given</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row">
                    <!-- Daily Revenue Trend -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Daily Revenue Trend</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="dailyRevenueChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Revenue by Room Type -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Revenue by Room Type</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="roomTypeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Tables Row -->
                <div class="row">
                    <!-- Revenue by Room Type Table -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Revenue by Room Type</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Room Type</th>
                                                <th>Bookings</th>
                                                <th>Nights</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($room_type_revenue_data as $room_type): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($room_type['room_type']); ?></td>
                                                    <td><?php echo $room_type['bookings_count']; ?></td>
                                                    <td><?php echo $room_type['nights_sold']; ?></td>
                                                    <td>
                                                        <span class="badge bg-success revenue-badge">
                                                            ₹<?php echo number_format($room_type['revenue']); ?>
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

                    <!-- Payment Status Summary -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Payment Status Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Status</th>
                                                <th>Bookings</th>
                                                <th>Total Amount</th>
                                                <th>Advance Paid</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payment_summary_data as $payment): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge 
                                                            <?php echo $payment['payment_status'] == 'paid' ? 'bg-success' : 
                                                                  ($payment['payment_status'] == 'pending' ? 'bg-warning' : 'bg-info'); ?>">
                                                            <?php echo ucfirst($payment['payment_status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $payment['bookings_count']; ?></td>
                                                    <td>₹<?php echo number_format($payment['total_amount']); ?></td>
                                                    <td>₹<?php echo number_format($payment['advance_paid']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Performing Rooms -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Top Performing Rooms</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Room</th>
                                                <th>Type</th>
                                                <th>Bookings</th>
                                                <th>Nights</th>
                                                <th>Revenue</th>
                                                <th>Avg. Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top_rooms_data as $room): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($room['room_number']); ?></strong>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($room['room_type']); ?></td>
                                                    <td><?php echo $room['bookings_count']; ?></td>
                                                    <td><?php echo $room['nights_occupied']; ?></td>
                                                    <td>
                                                        <span class="badge bg-success">
                                                            ₹<?php echo number_format($room['revenue']); ?>
                                                        </span>
                                                    </td>
                                                    <td>₹<?php echo number_format($room['avg_revenue']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Comparison -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Last 6 Months Comparison</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="monthlyComparisonChart"></canvas>
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
    // Daily Revenue Chart
    const dailyCtx = document.getElementById('dailyRevenueChart').getContext('2d');
    <?php if (!empty($daily_revenue_data)): ?>
        const dailyLabels = <?php echo json_encode(array_column($daily_revenue_data, 'date')); ?>;
        const dailyRevenue = <?php echo json_encode(array_column($daily_revenue_data, 'daily_revenue')); ?>;
        const dailyBookings = <?php echo json_encode(array_column($daily_revenue_data, 'bookings_count')); ?>;
        
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dailyLabels,
                datasets: [
                    {
                        label: 'Daily Revenue (₹)',
                        data: dailyRevenue,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        yAxisID: 'y',
                        fill: true
                    },
                    {
                        label: 'Bookings Count',
                        data: dailyBookings,
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        type: 'bar',
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue (₹)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Bookings'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    <?php else: ?>
        new Chart(dailyCtx, {
            type: 'line',
            data: { labels: ['No Data'], datasets: [] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: { display: true, text: 'No revenue data available' }
                }
            }
        });
    <?php endif; ?>

    // Room Type Pie Chart
    const roomTypeCtx = document.getElementById('roomTypeChart').getContext('2d');
    <?php if (!empty($room_type_revenue_data)): ?>
        const roomTypeLabels = <?php echo json_encode(array_column($room_type_revenue_data, 'room_type')); ?>;
        const roomTypeRevenue = <?php echo json_encode(array_column($room_type_revenue_data, 'revenue')); ?>;
        
        new Chart(roomTypeCtx, {
            type: 'doughnut',
            data: {
                labels: roomTypeLabels,
                datasets: [{
                    data: roomTypeRevenue,
                    backgroundColor: [
                        '#28a745', '#007bff', '#6f42c1', '#fd7e14', 
                        '#e83e8c', '#20c997', '#ffc107', '#dc3545'
                    ]
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
    <?php else: ?>
        new Chart(roomTypeCtx, {
            type: 'doughnut',
            data: { labels: ['No Data'], datasets: [] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: { display: true, text: 'No room type data' }
                }
            }
        });
    <?php endif; ?>

    // Monthly Comparison Chart
    const monthlyCtx = document.getElementById('monthlyComparisonChart').getContext('2d');
    <?php if (!empty($monthly_comparison_data)): ?>
        const monthlyLabels = <?php echo json_encode(array_map(function($item) {
            $date = DateTime::createFromFormat('Y-m', $item['month']);
            return $date->format('M Y');
        }, $monthly_comparison_data)); ?>;
        const monthlyRevenue = <?php echo json_encode(array_column($monthly_comparison_data, 'revenue')); ?>;
        const monthlyBookings = <?php echo json_encode(array_column($monthly_comparison_data, 'bookings_count')); ?>;
        
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: monthlyLabels.reverse(),
                datasets: [
                    {
                        label: 'Monthly Revenue (₹)',
                        data: monthlyRevenue.reverse(),
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        yAxisID: 'y'
                    },
                    {
                        label: 'Bookings',
                        data: monthlyBookings.reverse(),
                        backgroundColor: 'rgba(0, 123, 255, 0.7)',
                        type: 'line',
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
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
    <?php else: ?>
        new Chart(monthlyCtx, {
            type: 'bar',
            data: { labels: ['No Data'], datasets: [] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: { display: true, text: 'No monthly data available' }
                }
            }
        });
    <?php endif; ?>

    // Export to Excel function
    function exportToExcel() {
        // Create a simple CSV export
        const csvContent = [
            ['Revenue Report', 'Generated on: ' + new Date().toLocaleDateString()],
            [''],
            ['Period:', '<?php echo $start_date . ' to ' . $end_date; ?>'],
            [''],
            ['Summary', ''],
            ['Total Revenue', '₹<?php echo number_format($summary_data['total_revenue'] ?? 0); ?>'],
            ['Total Bookings', '<?php echo $summary_data['total_bookings'] ?? 0; ?>'],
            ['Total Nights', '<?php echo $summary_data['total_nights'] ?? 0; ?>'],
            ['Unique Guests', '<?php echo $summary_data['unique_guests'] ?? 0; ?>'],
            [''],
            ['Revenue by Room Type', '', '', ''],
            ['Room Type', 'Bookings', 'Nights', 'Revenue']
        ];

        // Add room type data
        <?php foreach ($room_type_revenue_data as $room_type): ?>
            csvContent.push([
                '<?php echo $room_type['room_type']; ?>',
                '<?php echo $room_type['bookings_count']; ?>',
                '<?php echo $room_type['nights_sold']; ?>',
                '₹<?php echo number_format($room_type['revenue']); ?>'
            ]);
        <?php endforeach; ?>

        // Convert to CSV
        const csvString = csvContent.map(row => 
            row.map(field => `"${field}"`).join(',')
        ).join('\n');

        // Download
        const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', 'revenue_report_<?php echo date('Y-m-d'); ?>.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // Auto-refresh charts on window resize
    window.addEventListener('resize', function() {
        // Charts will automatically resize due to Chart.js responsiveness
    });
    </script>
</body>
</html>