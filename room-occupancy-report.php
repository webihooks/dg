<?php
// room-occupancy-report.php
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
$check_table_sql = "SHOW TABLES LIKE 'rooms_$user_id'";
$table_result = $conn->query($check_table_sql);
if ($table_result->num_rows == 0) {
    header("Location: create_room_tables.php");
    exit();
}

// Date range filter
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month
$room_type_filter = $_GET['room_type'] ?? 'all';

// Initialize variables
$occupancy_data = [];
$revenue_data = [];
$room_type_stats = [];
$daily_occupancy = [];

// Main occupancy report query
$occupancy_sql = "SELECT 
                    rt.name as room_type,
                    COUNT(r.id) as total_rooms,
                    SUM(CASE WHEN r.status = 'occupied' THEN 1 ELSE 0 END) as occupied_rooms,
                    SUM(CASE WHEN r.status = 'available' THEN 1 ELSE 0 END) as available_rooms,
                    SUM(CASE WHEN r.status IN ('maintenance', 'cleaning') THEN 1 ELSE 0 END) as out_of_service_rooms,
                    ROUND((SUM(CASE WHEN r.status = 'occupied' THEN 1 ELSE 0 END) / COUNT(r.id)) * 100, 2) as occupancy_rate,
                    AVG(r.rate_per_night) as avg_rate
                  FROM rooms_$user_id r
                  LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id
                  WHERE rt.is_active = 1";

if ($room_type_filter !== 'all') {
    $occupancy_sql .= " AND rt.id = ?";
}

$occupancy_sql .= " GROUP BY rt.id, rt.name ORDER BY occupancy_rate DESC";

$stmt = $conn->prepare($occupancy_sql);
if ($room_type_filter !== 'all') {
    $stmt->bind_param("i", $room_type_filter);
}
$stmt->execute();
$occupancy_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Revenue and booking statistics
$revenue_sql = "SELECT 
                  rt.name as room_type,
                  COUNT(b.id) as total_bookings,
                  SUM(b.total_amount) as total_revenue,
                  AVG(b.total_amount) as avg_booking_value,
                  SUM(b.total_nights) as total_nights_sold,
                  ROUND(SUM(b.total_amount) / COUNT(b.id), 2) as rev_per_booking
                FROM bookings_$user_id b
                LEFT JOIN rooms_$user_id r ON b.room_id = r.id
                LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id
                WHERE b.status IN ('checked_in', 'checked_out')
                AND b.check_in_date BETWEEN ? AND ?";

if ($room_type_filter !== 'all') {
    $revenue_sql .= " AND rt.id = ?";
}

$revenue_sql .= " GROUP BY rt.id, rt.name ORDER BY total_revenue DESC";

$stmt = $conn->prepare($revenue_sql);
if ($room_type_filter !== 'all') {
    $stmt->bind_param("ssi", $start_date, $end_date, $room_type_filter);
} else {
    $stmt->bind_param("ss", $start_date, $end_date);
}
$stmt->execute();
$revenue_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Daily occupancy trend for the selected period
$daily_sql = "SELECT 
                DATE(b.check_in_date) as occupancy_date,
                COUNT(DISTINCT b.room_id) as occupied_rooms,
                (SELECT COUNT(*) FROM rooms_$user_id WHERE status != 'maintenance') as total_rooms,
                ROUND((COUNT(DISTINCT b.room_id) / (SELECT COUNT(*) FROM rooms_$user_id WHERE status != 'maintenance')) * 100, 2) as occupancy_rate,
                SUM(b.total_amount) as daily_revenue
              FROM bookings_$user_id b
              WHERE b.status IN ('checked_in', 'checked_out')
              AND b.check_in_date BETWEEN ? AND ?
              GROUP BY DATE(b.check_in_date)
              ORDER BY occupancy_date";

$stmt = $conn->prepare($daily_sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$daily_occupancy = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get room types for filter dropdown
$room_types_sql = "SELECT id, name FROM room_types_$user_id WHERE is_active = 1 ORDER BY name";
$room_types_result = $conn->query($room_types_sql);
$room_types = $room_types_result->fetch_all(MYSQLI_ASSOC);

// Overall statistics
$overall_stats_sql = "SELECT 
                        COUNT(*) as total_rooms,
                        SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as current_occupied,
                        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as current_available,
                        ROUND((SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as current_occupancy_rate
                      FROM rooms_$user_id";
$overall_stats = $conn->query($overall_stats_sql)->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Room Occupancy Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .card-summary {
            transition: all 0.3s ease;
        }
        .card-summary:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .occupancy-high { background: linear-gradient(135deg, #28a745, #20c997) !important; }
        .occupancy-medium { background: linear-gradient(135deg, #ffc107, #fd7e14) !important; }
        .occupancy-low { background: linear-gradient(135deg, #dc3545, #e83e8c) !important; }
        .revenue-card { background: linear-gradient(135deg, #007bff, #6f42c1) !important; }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0;
        }
        .stats-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .occupancy-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 12px;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0,123,255,0.1);
        }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        .filter-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .export-btn {
            margin-left: 10px;
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
                            <h4 class="page-title">Room Occupancy Report</h4>
                            <div class="page-title-right">
                                <button class="btn btn-success export-btn" onclick="exportToExcel()">
                                    <i class="fas fa-file-excel"></i> Export Excel
                                </button>
                                <button class="btn btn-danger export-btn" onclick="exportToPDF()">
                                    <i class="fas fa-file-pdf"></i> Export PDF
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="row">
                    <div class="col-12">
                        <div class="filter-section">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Room Type</label>
                                    <select class="form-control" name="room_type">
                                        <option value="all">All Room Types</option>
                                        <?php foreach ($room_types as $type): ?>
                                            <option value="<?php echo $type['id']; ?>" 
                                                <?php echo ($room_type_filter == $type['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($type['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                                        <a href="room-occupancy-report.php" class="btn btn-secondary">Reset</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Overall Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card card-summary occupancy-high text-white">
                            <div class="card-body text-center">
                                <h3 class="stats-number"><?php echo $overall_stats['current_occupancy_rate'] ?? '0'; ?>%</h3>
                                <p class="stats-label">Current Occupancy Rate</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-summary revenue-card text-white">
                            <div class="card-body text-center">
                                <h3 class="stats-number"><?php echo $overall_stats['current_occupied'] ?? '0'; ?></h3>
                                <p class="stats-label">Currently Occupied Rooms</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-summary bg-success text-white">
                            <div class="card-body text-center">
                                <h3 class="stats-number"><?php echo $overall_stats['current_available'] ?? '0'; ?></h3>
                                <p class="stats-label">Available Rooms</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-summary bg-info text-white">
                            <div class="card-body text-center">
                                <h3 class="stats-number"><?php echo $overall_stats['total_rooms'] ?? '0'; ?></h3>
                                <p class="stats-label">Total Rooms</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Daily Occupancy Trend</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="occupancyTrendChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Occupancy Distribution</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="occupancyPieChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Occupancy by Room Type -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Occupancy by Room Type</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-centered mb-0" id="occupancyTable">
                                        <thead>
                                            <tr>
                                                <th>Room Type</th>
                                                <th>Total Rooms</th>
                                                <th>Occupied</th>
                                                <th>Available</th>
                                                <th>Out of Service</th>
                                                <th>Occupancy Rate</th>
                                                <th>Avg. Rate</th>
                                                <th>Performance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($occupancy_data as $row): 
                                                $occupancy_rate = $row['occupancy_rate'] ?? 0;
                                                $performance_class = $occupancy_rate >= 70 ? 'badge bg-success' : 
                                                                   ($occupancy_rate >= 40 ? 'badge bg-warning' : 'badge bg-danger');
                                                $performance_text = $occupancy_rate >= 70 ? 'High' : 
                                                                  ($occupancy_rate >= 40 ? 'Medium' : 'Low');
                                            ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($row['room_type']); ?></strong></td>
                                                    <td><?php echo $row['total_rooms']; ?></td>
                                                    <td>
                                                        <span class="badge bg-danger"><?php echo $row['occupied_rooms']; ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-success"><?php echo $row['available_rooms']; ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-warning"><?php echo $row['out_of_service_rooms']; ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="progress" style="height: 20px;">
                                                            <div class="progress-bar 
                                                                <?php echo $occupancy_rate >= 70 ? 'bg-success' : 
                                                                      ($occupancy_rate >= 40 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                                role="progressbar" 
                                                                style="width: <?php echo $occupancy_rate; ?>%;" 
                                                                aria-valuenow="<?php echo $occupancy_rate; ?>" 
                                                                aria-valuemin="0" 
                                                                aria-valuemax="100">
                                                                <?php echo $occupancy_rate; ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>₹<?php echo number_format($row['avg_rate'] ?? 0, 2); ?></td>
                                                    <td>
                                                        <span class="<?php echo $performance_class; ?>">
                                                            <?php echo $performance_text; ?>
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

                <!-- Revenue Statistics -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Revenue Statistics (<?php echo date('M j', strtotime($start_date)); ?> - <?php echo date('M j', strtotime($end_date)); ?>)</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-centered mb-0">
                                        <thead>
                                            <tr>
                                                <th>Room Type</th>
                                                <th>Total Bookings</th>
                                                <th>Total Nights</th>
                                                <th>Total Revenue</th>
                                                <th>Avg. Booking Value</th>
                                                <th>Revenue per Booking</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($revenue_data as $revenue): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($revenue['room_type']); ?></strong></td>
                                                    <td><?php echo $revenue['total_bookings']; ?></td>
                                                    <td><?php echo $revenue['total_nights_sold']; ?></td>
                                                    <td class="text-success">
                                                        <strong>₹<?php echo number_format($revenue['total_revenue'] ?? 0, 2); ?></strong>
                                                    </td>
                                                    <td>₹<?php echo number_format($revenue['avg_booking_value'] ?? 0, 2); ?></td>
                                                    <td>₹<?php echo number_format($revenue['rev_per_booking'] ?? 0, 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($revenue_data)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted">No revenue data for the selected period</td>
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

            <?php include 'footer.php'; ?>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Occupancy Trend Chart
            const trendCtx = document.getElementById('occupancyTrendChart').getContext('2d');
            const trendChart = new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($daily_occupancy, 'occupancy_date')); ?>,
                    datasets: [{
                        label: 'Occupancy Rate (%)',
                        data: <?php echo json_encode(array_column($daily_occupancy, 'occupancy_rate')); ?>,
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'Daily Revenue (₹)',
                        data: <?php echo json_encode(array_column($daily_occupancy, 'daily_revenue')); ?>,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y1'
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
                                text: 'Occupancy Rate (%)'
                            }
                        },
                        y1: {
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Revenue (₹)'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });

            // Occupancy Pie Chart
            const pieCtx = document.getElementById('occupancyPieChart').getContext('2d');
            const pieChart = new Chart(pieCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Occupied', 'Available', 'Out of Service'],
                    datasets: [{
                        data: [
                            <?php echo $overall_stats['current_occupied'] ?? 0; ?>,
                            <?php echo $overall_stats['current_available'] ?? 0; ?>,
                            <?php echo ($overall_stats['total_rooms'] ?? 0) - ($overall_stats['current_occupied'] ?? 0) - ($overall_stats['current_available'] ?? 0); ?>
                        ],
                        backgroundColor: [
                            '#dc3545',
                            '#28a745',
                            '#ffc107'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
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
        });

        // Export functions
        function exportToExcel() {
            // Simple table export (you can enhance this with a proper Excel library)
            const table = document.getElementById('occupancyTable');
            let csv = [];
            for (let i = 0; i < table.rows.length; i++) {
                let row = [], cols = table.rows[i].querySelectorAll('td, th');
                for (let j = 0; j < cols.length; j++) {
                    let text = cols[j].innerText.replace(/,/g, '');
                    row.push(text);
                }
                csv.push(row.join(','));
            }
            let csvString = csv.join('\n');
            let link = document.createElement('a');
            link.href = 'data:text/csv;charset=utf-8,' + encodeURI(csvString);
            link.download = 'occupancy_report_<?php echo date('Y-m-d'); ?>.csv';
            link.click();
        }

        function exportToPDF() {
            alert('PDF export feature would be implemented with a PDF library like jsPDF');
            // You can implement PDF export using jsPDF + html2canvas
        }
    </script>
</body>
</html>