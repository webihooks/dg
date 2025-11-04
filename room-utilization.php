<?php
// room-utilization.php
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
$success_message = '';
$error_message = '';

// Check if room tables exist
$check_table_sql = "SHOW TABLES LIKE 'rooms_$user_id'";
$table_result = $conn->query($check_table_sql);
if ($table_result->num_rows == 0) {
    header("Location: create_room_tables.php");
    exit();
}

// Get filter parameters
$filter_month = $_GET['month'] ?? date('Y-m');
$filter_room_type = $_GET['room_type'] ?? 'all';
$filter_year = $_GET['year'] ?? date('Y');

// Validate month format
if (!preg_match('/^\d{4}-\d{2}$/', $filter_month)) {
    $filter_month = date('Y-m');
}

// Get room utilization data
$utilization_data = [];
$monthly_stats = [];
$room_type_stats = [];

try {
    // Monthly utilization rate
    $monthly_sql = "
        SELECT 
            DATE_FORMAT(b.check_in_date, '%Y-%m') as month,
            COUNT(DISTINCT b.room_id) as occupied_rooms,
            (SELECT COUNT(*) FROM rooms_$user_id WHERE status != 'maintenance') as total_rooms,
            ROUND((COUNT(DISTINCT b.room_id) / (SELECT COUNT(*) FROM rooms_$user_id WHERE status != 'maintenance')) * 100, 2) as utilization_rate,
            SUM(b.total_amount) as monthly_revenue,
            COUNT(DISTINCT b.id) as total_bookings
        FROM bookings_$user_id b
        WHERE b.status IN ('checked_in', 'checked_out')
        AND DATE_FORMAT(b.check_in_date, '%Y-%m') = ?
        GROUP BY DATE_FORMAT(b.check_in_date, '%Y-%m')
    ";
    
    $stmt = $conn->prepare($monthly_sql);
    $stmt->bind_param("s", $filter_month);
    $stmt->execute();
    $monthly_stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Daily utilization for the selected month
    $daily_sql = "
        SELECT 
            DATE(b.check_in_date) as date,
            COUNT(DISTINCT b.room_id) as occupied_rooms,
            (SELECT COUNT(*) FROM rooms_$user_id WHERE status != 'maintenance') as total_rooms,
            ROUND((COUNT(DISTINCT b.room_id) / (SELECT COUNT(*) FROM rooms_$user_id WHERE status != 'maintenance')) * 100, 2) as utilization_rate,
            SUM(b.total_amount) as daily_revenue
        FROM bookings_$user_id b
        WHERE b.status IN ('checked_in', 'checked_out')
        AND DATE_FORMAT(b.check_in_date, '%Y-%m') = ?
        GROUP BY DATE(b.check_in_date)
        ORDER BY date
    ";
    
    $stmt = $conn->prepare($daily_sql);
    $stmt->bind_param("s", $filter_month);
    $stmt->execute();
    $daily_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Room type utilization
    $room_type_sql = "
        SELECT 
            rt.name as room_type,
            COUNT(DISTINCT r.id) as total_rooms,
            COUNT(DISTINCT CASE WHEN b.status IN ('checked_in', 'checked_out') AND DATE_FORMAT(b.check_in_date, '%Y-%m') = ? THEN b.room_id END) as occupied_rooms,
            ROUND(
                (COUNT(DISTINCT CASE WHEN b.status IN ('checked_in', 'checked_out') AND DATE_FORMAT(b.check_in_date, '%Y-%m') = ? THEN b.room_id END) / COUNT(DISTINCT r.id)) * 100, 
                2
            ) as utilization_rate,
            COALESCE(SUM(CASE WHEN DATE_FORMAT(b.check_in_date, '%Y-%m') = ? THEN b.total_amount END), 0) as revenue
        FROM room_types_$user_id rt
        LEFT JOIN rooms_$user_id r ON rt.id = r.room_type_id
        LEFT JOIN bookings_$user_id b ON r.id = b.room_id AND b.status IN ('checked_in', 'checked_out')
        GROUP BY rt.id, rt.name
        ORDER BY utilization_rate DESC
    ";
    
    $stmt = $conn->prepare($room_type_sql);
    $stmt->bind_param("sss", $filter_month, $filter_month, $filter_month);
    $stmt->execute();
    $room_type_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Top performing rooms
    $top_rooms_sql = "
        SELECT 
            r.room_number,
            rt.name as room_type,
            COUNT(b.id) as total_bookings,
            SUM(b.total_amount) as total_revenue,
            ROUND(
                (COUNT(DISTINCT CASE WHEN b.status IN ('checked_in', 'checked_out') AND DATE_FORMAT(b.check_in_date, '%Y-%m') = ? THEN b.id END) / 
                (SELECT DATEDIFF(LAST_DAY(?), DATE_FORMAT(?, '%Y-%m-01')) + 1)) * 100, 
                2
            ) as monthly_utilization
        FROM rooms_$user_id r
        LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id
        LEFT JOIN bookings_$user_id b ON r.id = b.room_id AND b.status IN ('checked_in', 'checked_out')
        WHERE r.status != 'maintenance'
        GROUP BY r.id, r.room_number, rt.name
        ORDER BY monthly_utilization DESC, total_revenue DESC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($top_rooms_sql);
    $stmt->bind_param("sss", $filter_month, $filter_month, $filter_month);
    $stmt->execute();
    $top_rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Revenue trends (last 6 months)
    $revenue_trend_sql = "
        SELECT 
            DATE_FORMAT(check_in_date, '%Y-%m') as month,
            SUM(total_amount) as revenue,
            COUNT(DISTINCT room_id) as occupied_rooms,
            (SELECT COUNT(*) FROM rooms_$user_id WHERE status != 'maintenance') as total_rooms,
            ROUND((COUNT(DISTINCT room_id) / (SELECT COUNT(*) FROM rooms_$user_id WHERE status != 'maintenance')) * 100, 2) as utilization_rate
        FROM bookings_$user_id 
        WHERE status IN ('checked_in', 'checked_out')
        AND check_in_date >= DATE_SUB(?, INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(check_in_date, '%Y-%m')
        ORDER BY month DESC
    ";
    
    $current_month = $filter_month . '-01';
    $stmt = $conn->prepare($revenue_trend_sql);
    $stmt->bind_param("s", $current_month);
    $stmt->execute();
    $revenue_trends = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (Exception $e) {
    $error_message = "Error loading utilization data: " . $e->getMessage();
}

$conn->close();

// Get available months for filter
$available_months = [];
for ($i = 0; $i < 12; $i++) {
    $month = date('Y-m', strtotime("-$i months"));
    $available_months[] = $month;
}

// Get room types for filter
$room_types = [];
foreach ($room_type_stats as $stat) {
    $room_types[] = $stat['room_type'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Room Utilization Analytics</title>
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
        .stat-card {
            transition: all 0.3s ease;
            border-left: 4px solid #007bff;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .utilization-high { border-left-color: #28a745; }
        .utilization-medium { border-left-color: #ffc107; }
        .utilization-low { border-left-color: #dc3545; }
        .revenue-card { border-left-color: #6f42c1; }
        
        .progress {
            height: 8px;
            margin-top: 5px;
        }
        .utilization-rate {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .room-type-card {
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .top-room-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        @media (max-width: 768px) {
            .stat-card .card-body {
                padding: 15px;
            }
            .utilization-rate {
                font-size: 1.2rem;
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
                <!-- Header -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <h4 class="page-title">🏨 Room Utilization Analytics</h4>
                            <p class="text-muted">Comprehensive analysis of room occupancy and revenue performance</p>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="row">
                    <div class="col-12">
                        <div class="filter-section">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Select Month</label>
                                    <select name="month" class="form-select">
                                        <?php foreach ($available_months as $month): ?>
                                            <option value="<?php echo $month; ?>" <?php echo $month == $filter_month ? 'selected' : ''; ?>>
                                                <?php echo date('F Y', strtotime($month . '-01')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Room Type</label>
                                    <select name="room_type" class="form-select">
                                        <option value="all" <?php echo $filter_room_type == 'all' ? 'selected' : ''; ?>>All Room Types</option>
                                        <?php foreach ($room_types as $type): ?>
                                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $filter_room_type == $type ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($type); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Year</label>
                                    <select name="year" class="form-select">
                                        <?php for ($year = date('Y'); $year >= 2020; $year--): ?>
                                            <option value="<?php echo $year; ?>" <?php echo $filter_year == $year ? 'selected' : ''; ?>>
                                                <?php echo $year; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                                        <a href="room-utilization.php" class="btn btn-secondary">Reset</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Error Message -->
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <!-- Key Metrics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card utilization-<?php echo ($monthly_stats['utilization_rate'] ?? 0) > 70 ? 'high' : (($monthly_stats['utilization_rate'] ?? 0) > 40 ? 'medium' : 'low'); ?>">
                            <div class="card-body">
                                <h5 class="card-title">Utilization Rate</h5>
                                <div class="utilization-rate text-<?php echo ($monthly_stats['utilization_rate'] ?? 0) > 70 ? 'success' : (($monthly_stats['utilization_rate'] ?? 0) > 40 ? 'warning' : 'danger'); ?>">
                                    <?php echo $monthly_stats['utilization_rate'] ?? '0.00'; ?>%
                                </div>
                                <p class="card-text">
                                    <?php echo $monthly_stats['occupied_rooms'] ?? 0; ?> of <?php echo $monthly_stats['total_rooms'] ?? 0; ?> rooms occupied
                                </p>
                                <div class="progress">
                                    <div class="progress-bar bg-<?php echo ($monthly_stats['utilization_rate'] ?? 0) > 70 ? 'success' : (($monthly_stats['utilization_rate'] ?? 0) > 40 ? 'warning' : 'danger'); ?>" 
                                         style="width: <?php echo min(($monthly_stats['utilization_rate'] ?? 0), 100); ?>%">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card revenue-card">
                            <div class="card-body">
                                <h5 class="card-title">Monthly Revenue</h5>
                                <div class="utilization-rate text-primary">
                                    ₹<?php echo number_format($monthly_stats['monthly_revenue'] ?? 0); ?>
                                </div>
                                <p class="card-text">Total revenue for <?php echo date('F Y', strtotime($filter_month . '-01')); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">Total Bookings</h5>
                                <div class="utilization-rate text-info">
                                    <?php echo $monthly_stats['total_bookings'] ?? 0; ?>
                                </div>
                                <p class="card-text">Completed bookings this month</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">Avg. Room Revenue</h5>
                                <div class="utilization-rate text-success">
                                    ₹<?php 
                                    $avg_revenue = ($monthly_stats['monthly_revenue'] ?? 0) / max(($monthly_stats['occupied_rooms'] ?? 1), 1);
                                    echo number_format($avg_revenue, 2);
                                    ?>
                                </div>
                                <p class="card-text">Per occupied room</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <!-- Utilization Trend Chart -->
                    <div class="col-md-8">
                        <div class="chart-container">
                            <h5>Monthly Utilization & Revenue Trend</h5>
                            <canvas id="utilizationTrendChart" height="250"></canvas>
                        </div>
                    </div>
                    
                    <!-- Room Type Distribution -->
                    <div class="col-md-4">
                        <div class="chart-container">
                            <h5>Room Type Utilization</h5>
                            <canvas id="roomTypeChart" height="250"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Room Type Performance -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Room Type Performance - <?php echo date('F Y', strtotime($filter_month . '-01')); ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Room Type</th>
                                                <th>Total Rooms</th>
                                                <th>Occupied</th>
                                                <th>Utilization Rate</th>
                                                <th>Revenue</th>
                                                <th>Avg. Rate/Night</th>
                                                <th>Performance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($room_type_stats as $stat): ?>
                                                <?php
                                                $utilization_rate = $stat['utilization_rate'] ?? 0;
                                                $performance_class = $utilization_rate > 70 ? 'text-success' : ($utilization_rate > 40 ? 'text-warning' : 'text-danger');
                                                $avg_rate = $stat['revenue'] / max($stat['occupied_rooms'] * 30, 1); // Simplified calculation
                                                ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($stat['room_type']); ?></strong></td>
                                                    <td><?php echo $stat['total_rooms']; ?></td>
                                                    <td><?php echo $stat['occupied_rooms']; ?></td>
                                                    <td>
                                                        <span class="<?php echo $performance_class; ?> fw-bold">
                                                            <?php echo $utilization_rate; ?>%
                                                        </span>
                                                        <div class="progress" style="height: 5px;">
                                                            <div class="progress-bar <?php echo $utilization_rate > 70 ? 'bg-success' : ($utilization_rate > 40 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                                 style="width: <?php echo min($utilization_rate, 100); ?>%">
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>₹<?php echo number_format($stat['revenue']); ?></td>
                                                    <td>₹<?php echo number_format($avg_rate, 2); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $utilization_rate > 70 ? 'bg-success' : ($utilization_rate > 40 ? 'bg-warning' : 'bg-danger'); ?>">
                                                            <?php echo $utilization_rate > 70 ? 'Excellent' : ($utilization_rate > 40 ? 'Good' : 'Needs Attention'); ?>
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

                <!-- Top Performing Rooms -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">🏆 Top Performing Rooms</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($top_rooms)): ?>
                                    <?php foreach ($top_rooms as $index => $room): ?>
                                        <div class="top-room-badge">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong>#<?php echo $index + 1; ?> <?php echo htmlspecialchars($room['room_number']); ?></strong>
                                                    <small class="d-block"><?php echo htmlspecialchars($room['room_type']); ?></small>
                                                </div>
                                                <div class="text-end">
                                                    <div class="fw-bold"><?php echo $room['monthly_utilization']; ?>%</div>
                                                    <small>₹<?php echo number_format($room['total_revenue']); ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted">No room data available for the selected period.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Revenue Forecast -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">📈 Revenue Forecast</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $current_revenue = $monthly_stats['monthly_revenue'] ?? 0;
                                $current_utilization = $monthly_stats['utilization_rate'] ?? 0;
                                $optimized_revenue = $current_utilization < 70 ? $current_revenue * (70 / max($current_utilization, 1)) : $current_revenue;
                                $revenue_growth = $optimized_revenue - $current_revenue;
                                ?>
                                <div class="mb-3">
                                    <h6>Current Performance</h6>
                                    <div class="d-flex justify-content-between">
                                        <span>Revenue:</span>
                                        <strong>₹<?php echo number_format($current_revenue); ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Utilization:</span>
                                        <strong><?php echo $current_utilization; ?>%</strong>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <h6>Optimized Potential (70% Utilization)</h6>
                                    <div class="d-flex justify-content-between">
                                        <span>Potential Revenue:</span>
                                        <strong class="text-success">₹<?php echo number_format($optimized_revenue); ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Growth Opportunity:</span>
                                        <strong class="text-success">+₹<?php echo number_format($revenue_growth); ?></strong>
                                    </div>
                                </div>
                                
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo min($current_utilization, 100); ?>%">
                                        Current
                                    </div>
                                    <div class="progress-bar bg-warning" style="width: <?php echo max(0, 70 - $current_utilization); ?>%">
                                        Potential
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
        // Utilization Trend Chart
        const trendCtx = document.getElementById('utilizationTrendChart').getContext('2d');
        const revenueTrends = <?php echo json_encode($revenue_trends); ?>;
        
        const trendLabels = revenueTrends.map(trend => {
            const date = new Date(trend.month + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
        }).reverse();
        
        const utilizationData = revenueTrends.map(trend => trend.utilization_rate).reverse();
        const revenueData = revenueTrends.map(trend => trend.revenue).reverse();

        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: 'Utilization Rate (%)',
                        data: utilizationData,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        yAxisID: 'y',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Revenue (₹)',
                        data: revenueData,
                        borderColor: '#6f42c1',
                        backgroundColor: 'rgba(111, 66, 193, 0.1)',
                        yAxisID: 'y1',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Utilization Rate (%)'
                        },
                        min: 0,
                        max: 100
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Revenue (₹)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        // Room Type Chart
        const roomTypeCtx = document.getElementById('roomTypeChart').getContext('2d');
        const roomTypeStats = <?php echo json_encode($room_type_stats); ?>;
        
        const roomTypeLabels = roomTypeStats.map(stat => stat.room_type);
        const roomTypeUtilization = roomTypeStats.map(stat => stat.utilization_rate);

        new Chart(roomTypeCtx, {
            type: 'doughnut',
            data: {
                labels: roomTypeLabels,
                datasets: [{
                    data: roomTypeUtilization,
                    backgroundColor: [
                        '#28a745',
                        '#007bff',
                        '#6f42c1',
                        '#ffc107',
                        '#dc3545',
                        '#fd7e14',
                        '#20c997',
                        '#e83e8c'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.label}: ${context.raw}% utilization`;
                            }
                        }
                    }
                }
            }
        });

        // Android session protection
        if (typeof WTN !== 'undefined') {
            setInterval(() => {
                if (WTN.forceUpdateCookies) {
                    WTN.forceUpdateCookies();
                }
            }, 45000);
        }
    });
    </script>
</body>
</html>