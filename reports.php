<?php
// reports.php
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

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get date filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'occupancy';

// Initialize report data
$report_data = [];
$summary_data = [];
$chart_data = [];

try {
    // Overall Summary Data
    $summary_sql = "SELECT 
        COUNT(*) as total_rooms,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_rooms,
        SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied_rooms,
        SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_rooms,
        SUM(CASE WHEN status = 'cleaning' THEN 1 ELSE 0 END) as cleaning_rooms
    FROM rooms_87";
    
    $stmt = $conn->prepare($summary_sql);
    $stmt->execute();
    $summary_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Report-specific data
    switch($report_type) {
        case 'occupancy':
            $report_data = getOccupancyReport($conn, $start_date, $end_date);
            break;
        case 'revenue':
            $report_data = getRevenueReport($conn, $start_date, $end_date);
            break;
        case 'bookings':
            $report_data = getBookingsReport($conn, $start_date, $end_date);
            break;
        case 'guests':
            $report_data = getGuestsReport($conn, $start_date, $end_date);
            break;
        case 'maintenance':
            $report_data = getMaintenanceReport($conn, $start_date, $end_date);
            break;
        case 'loyalty':
            $report_data = getLoyaltyReport($conn, $start_date, $end_date);
            break;
        default:
            $report_data = getOccupancyReport($conn, $start_date, $end_date);
    }

    // Chart data for visualization
    $chart_data = getChartData($conn, $start_date, $end_date, $report_type);

} catch (Exception $e) {
    $error_message = "Error generating report: " . $e->getMessage();
}

$conn->close();

// Report generation functions
function getOccupancyReport($conn, $start_date, $end_date) {
    $sql = "SELECT 
        DATE(check_in_date) as date,
        COUNT(*) as total_bookings,
        SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as checked_in,
        SUM(CASE WHEN status = 'checked_out' THEN 1 ELSE 0 END) as checked_out,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        AVG(DATEDIFF(check_out_date, check_in_date)) as avg_stay_duration,
        (COUNT(*) / (SELECT COUNT(*) FROM rooms_87)) * 100 as occupancy_rate
    FROM bookings_87 
    WHERE DATE(check_in_date) BETWEEN ? AND ?
    GROUP BY DATE(check_in_date)
    ORDER BY date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $data;
}

function getRevenueReport($conn, $start_date, $end_date) {
    $sql = "SELECT 
        DATE(b.check_in_date) as date,
        COUNT(*) as total_bookings,
        SUM(b.total_amount) as total_revenue,
        SUM(COALESCE(p.amount, 0)) as collected_amount,
        AVG(b.total_amount) as avg_booking_value,
        SUM(b.total_amount - COALESCE(b.discount_amount, 0)) as net_revenue,
        SUM(COALESCE(ec.total_amount, 0)) as extra_charges,
        SUM(COALESCE(b.discount_amount, 0)) as discounts
    FROM bookings_87 b
    LEFT JOIN payments_87 p ON b.id = p.booking_id AND p.payment_status = 'completed'
    LEFT JOIN extra_charges_87 ec ON b.id = ec.booking_id
    WHERE DATE(b.check_in_date) BETWEEN ? AND ?
    GROUP BY DATE(b.check_in_date)
    ORDER BY date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $data;
}

function getBookingsReport($conn, $start_date, $end_date) {
    $sql = "SELECT 
        b.id,
        b.booking_reference,
        b.guest_name,
        b.room_number,
        b.check_in_date,
        b.check_out_date,
        b.total_amount,
        b.status as booking_status,
        r.room_type_id,
        b.adults + b.children as guest_count,
        p.payment_method,
        p.payment_status
    FROM bookings_87 b
    LEFT JOIN rooms_87 r ON b.room_id = r.id
    LEFT JOIN payments_87 p ON b.id = p.booking_id
    WHERE DATE(b.check_in_date) BETWEEN ? AND ?
    ORDER BY b.check_in_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $data;
}

function getGuestsReport($conn, $start_date, $end_date) {
    $sql = "SELECT 
        g.id as guest_id,
        g.name as full_name,
        g.email,
        g.phone,
        g.id_proof_type,
        g.id_proof_number,
        g.country,
        g.city,
        COUNT(b.id) as total_stays,
        SUM(COALESCE(b.total_amount, 0)) as total_spent,
        AVG(COALESCE(b.total_amount, 0)) as avg_spend_per_stay,
        MAX(b.check_in_date) as last_visit,
        COALESCE(lp.total_points, 0) as points_balance,
        COALESCE(lp.tier_name, 'Standard') as tier_level
    FROM guests_87 g
    LEFT JOIN bookings_87 b ON g.id = b.guest_id
    LEFT JOIN loyalty_program_87 lp ON g.id = lp.guest_id
    WHERE DATE(b.check_in_date) BETWEEN ? AND ?
    GROUP BY g.id
    ORDER BY total_spent DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $data;
}

function getMaintenanceReport($conn, $start_date, $end_date) {
    $sql = "SELECT 
        m.id as maintenance_id,
        m.room_number,
        m.maintenance_type,
        m.description,
        m.priority,
        m.status,
        m.reported_date,
        m.completion_date,
        m.estimated_cost,
        m.actual_cost,
        DATEDIFF(COALESCE(m.completion_date, CURDATE()), m.reported_date) as days_to_complete,
        m.assigned_to as staff_name
    FROM room_maintenance_87 m
    WHERE DATE(m.reported_date) BETWEEN ? AND ?
    ORDER BY m.reported_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $data;
}

function getLoyaltyReport($conn, $start_date, $end_date) {
    $sql = "SELECT 
        g.name as full_name,
        g.email,
        g.phone,
        COALESCE(lp.current_points, 0) as points_balance,
        COALESCE(lp.tier_name, 'Standard') as tier_level,
        COALESCE(lp.points_earned, 0) as total_points_earned,
        COALESCE(lp.points_redeemed, 0) as total_points_redeemed,
        lp.last_activity,
        COUNT(b.id) as total_bookings,
        SUM(COALESCE(b.total_amount, 0)) as total_spent
    FROM guests_87 g
    LEFT JOIN loyalty_program_87 lp ON g.id = lp.guest_id
    LEFT JOIN bookings_87 b ON g.id = b.guest_id
    WHERE DATE(b.check_in_date) BETWEEN ? AND ?
    GROUP BY g.id
    ORDER BY lp.current_points DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $data;
}

function getChartData($conn, $start_date, $end_date, $report_type) {
    $chart_data = [];
    
    switch($report_type) {
        case 'occupancy':
            $sql = "SELECT 
                DATE(check_in_date) as date,
                COUNT(*) as bookings,
                SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as occupied
            FROM bookings_87 
            WHERE DATE(check_in_date) BETWEEN ? AND ?
            GROUP BY DATE(check_in_date)
            ORDER BY date";
            break;
        case 'revenue':
            $sql = "SELECT 
                DATE(check_in_date) as date,
                SUM(total_amount) as revenue,
                COUNT(*) as bookings
            FROM bookings_87 
            WHERE DATE(check_in_date) BETWEEN ? AND ?
            GROUP BY DATE(check_in_date)
            ORDER BY date";
            break;
        default:
            return $chart_data;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $chart_data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $chart_data;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Room Management Reports</title>
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
        .report-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .report-filter {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        .badge-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-available { background: #28a745; color: white; }
        .badge-occupied { background: #dc3545; color: white; }
        .badge-maintenance { background: #ffc107; color: #000; }
        .badge-cleaning { background: #17a2b8; color: white; }
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
                            <h4 class="page-title">Room Management Reports</h4>
                            <div class="page-title-right">
                                <button class="btn btn-success" onclick="exportToExcel()">
                                    <i class="fas fa-file-excel"></i> Export to Excel
                                </button>
                                <button class="btn btn-danger" onclick="exportToPDF()">
                                    <i class="fas fa-file-pdf"></i> Export to PDF
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Error/Success Messages -->
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>

                <!-- Summary Statistics -->
                <div class="row">
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $summary_data['total_rooms'] ?? 0; ?></div>
                            <div class="stat-label">Total Rooms</div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                            <div class="stat-number"><?php echo $summary_data['available_rooms'] ?? 0; ?></div>
                            <div class="stat-label">Available Rooms</div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card" style="background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);">
                            <div class="stat-number"><?php echo $summary_data['occupied_rooms'] ?? 0; ?></div>
                            <div class="stat-label">Occupied Rooms</div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                            <div class="stat-number"><?php echo $summary_data['maintenance_rooms'] ?? 0; ?></div>
                            <div class="stat-label">Under Maintenance</div>
                        </div>
                    </div>
                </div>

                <!-- Report Filters -->
                <div class="row">
                    <div class="col-12">
                        <div class="report-filter">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Report Type</label>
                                    <select name="report_type" class="form-select" onchange="this.form.submit()">
                                        <option value="occupancy" <?php echo $report_type == 'occupancy' ? 'selected' : ''; ?>>Occupancy Report</option>
                                        <option value="revenue" <?php echo $report_type == 'revenue' ? 'selected' : ''; ?>>Revenue Report</option>
                                        <option value="bookings" <?php echo $report_type == 'bookings' ? 'selected' : ''; ?>>Bookings Report</option>
                                        <option value="guests" <?php echo $report_type == 'guests' ? 'selected' : ''; ?>>Guests Report</option>
                                        <option value="maintenance" <?php echo $report_type == 'maintenance' ? 'selected' : ''; ?>>Maintenance Report</option>
                                        <option value="loyalty" <?php echo $report_type == 'loyalty' ? 'selected' : ''; ?>>Loyalty Report</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">Generate Report</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Chart Section -->
                <div class="row">
                    <div class="col-12">
                        <div class="chart-container">
                            <h5><?php echo ucfirst($report_type); ?> Trends</h5>
                            <canvas id="reportChart" height="100"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Report Data Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card report-card">
                            <div class="card-header">
                                <h4 class="card-title">
                                    <?php echo ucfirst($report_type); ?> Report 
                                    (<?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?>)
                                </h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <?php echo generateReportTable($report_type, $report_data); ?>
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
    // Initialize Chart
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('reportChart').getContext('2d');
        const reportType = '<?php echo $report_type; ?>';
        
        <?php if (!empty($chart_data)): ?>
            const chartData = <?php echo json_encode($chart_data); ?>;
            
            let labels = chartData.map(item => item.date);
            let dataset1 = chartData.map(item => {
                if (reportType === 'occupancy') return item.occupied || 0;
                if (reportType === 'revenue') return item.revenue || 0;
                return 0;
            });
            let dataset2 = chartData.map(item => {
                if (reportType === 'occupancy') return item.bookings || 0;
                if (reportType === 'revenue') return item.bookings || 0;
                return 0;
            });

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: reportType === 'occupancy' ? 'Occupied Rooms' : 'Revenue (₹)',
                            data: dataset1,
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            borderWidth: 2,
                            fill: true
                        },
                        {
                            label: 'Bookings',
                            data: dataset2,
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            borderWidth: 2,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: reportType === 'occupancy' ? 'Occupancy Trend' : 'Revenue Trend'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        <?php else: ?>
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['No Data'],
                    datasets: []
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'No data available for the selected period'
                        }
                    }
                }
            });
        <?php endif; ?>
    });

    // Export functions
    function exportToExcel() {
        const table = document.getElementById('reportTable');
        if (!table) {
            alert('No data to export');
            return;
        }
        const html = table.outerHTML;
        const url = 'data:application/vnd.ms-excel;charset=utf-8,' + encodeURIComponent(html);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'room_report_<?php echo $report_type; ?>_<?php echo date('Y-m-d'); ?>.xls';
        link.click();
    }

    function exportToPDF() {
        alert('PDF export functionality would be implemented here with a PDF library');
        // In practice, you would use a library like jsPDF or make a server-side call
    }

    // Android Session Protection
    function setupReportsSessionProtection() {
        if (typeof WTN === 'undefined') return;
        
        console.log('📊 Reports: Setting up Android session protection');
        
        setTimeout(() => {
            if (WTN.forceUpdateCookies) {
                WTN.forceUpdateCookies();
            }
        }, 1000);
        
        setInterval(() => {
            if (WTN.forceUpdateCookies) {
                WTN.forceUpdateCookies();
            }
        }, 45000);
    }

    document.addEventListener('DOMContentLoaded', function() {
        setupReportsSessionProtection();
    });
    </script>
</body>
</html>

<?php
// Helper function to generate report tables
function generateReportTable($report_type, $data) {
    switch($report_type) {
        case 'occupancy':
            return generateOccupancyTable($data);
        case 'revenue':
            return generateRevenueTable($data);
        case 'bookings':
            return generateBookingsTable($data);
        case 'guests':
            return generateGuestsTable($data);
        case 'maintenance':
            return generateMaintenanceTable($data);
        case 'loyalty':
            return generateLoyaltyTable($data);
        default:
            return '<p>No data available</p>';
    }
}

function generateOccupancyTable($data) {
    if (empty($data)) return '<p>No occupancy data available</p>';
    
    $html = '<table class="table table-hover" id="reportTable">
        <thead>
            <tr>
                <th>Date</th>
                <th>Total Bookings</th>
                <th>Checked In</th>
                <th>Checked Out</th>
                <th>Cancelled</th>
                <th>Avg Stay (Days)</th>
                <th>Occupancy Rate</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach($data as $row) {
        $html .= '<tr>
            <td>' . ($row['date'] ?? '') . '</td>
            <td>' . ($row['total_bookings'] ?? 0) . '</td>
            <td>' . ($row['checked_in'] ?? 0) . '</td>
            <td>' . ($row['checked_out'] ?? 0) . '</td>
            <td>' . ($row['cancelled'] ?? 0) . '</td>
            <td>' . number_format($row['avg_stay_duration'] ?? 0, 1) . '</td>
            <td>' . number_format($row['occupancy_rate'] ?? 0, 1) . '%</td>
        </tr>';
    }
    
    $html .= '</tbody></table>';
    return $html;
}

function generateRevenueTable($data) {
    if (empty($data)) return '<p>No revenue data available</p>';
    
    $html = '<table class="table table-hover" id="reportTable">
        <thead>
            <tr>
                <th>Date</th>
                <th>Total Bookings</th>
                <th>Total Revenue</th>
                <th>Collected Amount</th>
                <th>Avg Booking Value</th>
                <th>Net Revenue</th>
                <th>Extra Charges</th>
                <th>Discounts</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach($data as $row) {
        $html .= '<tr>
            <td>' . ($row['date'] ?? '') . '</td>
            <td>' . ($row['total_bookings'] ?? 0) . '</td>
            <td>₹' . number_format($row['total_revenue'] ?? 0) . '</td>
            <td>₹' . number_format($row['collected_amount'] ?? 0) . '</td>
            <td>₹' . number_format($row['avg_booking_value'] ?? 0) . '</td>
            <td>₹' . number_format($row['net_revenue'] ?? 0) . '</td>
            <td>₹' . number_format($row['extra_charges'] ?? 0) . '</td>
            <td>₹' . number_format($row['discounts'] ?? 0) . '</td>
        </tr>';
    }
    
    $html .= '</tbody></table>';
    return $html;
}

function generateBookingsTable($data) {
    if (empty($data)) return '<p>No bookings data available</p>';
    
    $html = '<table class="table table-hover" id="reportTable">
        <thead>
            <tr>
                <th>Booking ID</th>
                <th>Guest Name</th>
                <th>Room</th>
                <th>Check-in</th>
                <th>Check-out</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Guests</th>
                <th>Payment</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach($data as $row) {
        $status_class = '';
        $status = $row['booking_status'] ?? '';
        switch($status) {
            case 'checked_in': $status_class = 'badge-occupied'; break;
            case 'reserved': $status_class = 'badge-available'; break;
            case 'cancelled': $status_class = 'badge-maintenance'; break;
            default: $status_class = 'badge-cleaning';
        }
        
        $html .= '<tr>
            <td>' . ($row['booking_reference'] ?? '') . '</td>
            <td>' . ($row['guest_name'] ?? '') . '</td>
            <td>' . ($row['room_number'] ?? '') . '</td>
            <td>' . ($row['check_in_date'] ?? '') . '</td>
            <td>' . ($row['check_out_date'] ?? '') . '</td>
            <td>₹' . number_format($row['total_amount'] ?? 0) . '</td>
            <td><span class="badge-status ' . $status_class . '">' . ucfirst($status) . '</span></td>
            <td>' . ($row['guest_count'] ?? 0) . '</td>
            <td>' . ($row['payment_status'] ?? 'Pending') . '</td>
        </tr>';
    }
    
    $html .= '</tbody></table>';
    return $html;
}

function generateGuestsTable($data) {
    if (empty($data)) return '<p>No guests data available</p>';
    
    $html = '<table class="table table-hover" id="reportTable">
        <thead>
            <tr>
                <th>Guest Name</th>
                <th>Contact</th>
                <th>ID Type</th>
                <th>Total Stays</th>
                <th>Total Spent</th>
                <th>Avg Spend</th>
                <th>Last Visit</th>
                <th>Loyalty Points</th>
                <th>Tier</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach($data as $row) {
        $html .= '<tr>
            <td>' . ($row['full_name'] ?? '') . '</td>
            <td>' . ($row['email'] ?? '') . '<br>' . ($row['phone'] ?? '') . '</td>
            <td>' . ($row['id_proof_type'] ?? '') . '</td>
            <td>' . ($row['total_stays'] ?? 0) . '</td>
            <td>₹' . number_format($row['total_spent'] ?? 0) . '</td>
            <td>₹' . number_format($row['avg_spend_per_stay'] ?? 0) . '</td>
            <td>' . ($row['last_visit'] ?? 'Never') . '</td>
            <td>' . ($row['points_balance'] ?? 0) . '</td>
            <td>' . ($row['tier_level'] ?? 'Standard') . '</td>
        </tr>';
    }
    
    $html .= '</tbody></table>';
    return $html;
}

function generateMaintenanceTable($data) {
    if (empty($data)) return '<p>No maintenance data available</p>';
    
    $html = '<table class="table table-hover" id="reportTable">
        <thead>
            <tr>
                <th>Room</th>
                <th>Type</th>
                <th>Description</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Requested</th>
                <th>Completed</th>
                <th>Days Taken</th>
                <th>Assigned To</th>
                <th>Cost</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach($data as $row) {
        $html .= '<tr>
            <td>' . ($row['room_number'] ?? '') . '</td>
            <td>' . ($row['maintenance_type'] ?? '') . '</td>
            <td>' . ($row['description'] ?? '') . '</td>
            <td>' . ucfirst($row['priority'] ?? '') . '</td>
            <td><span class="badge-status">' . ucfirst($row['status'] ?? '') . '</span></td>
            <td>' . ($row['reported_date'] ?? '') . '</td>
            <td>' . ($row['completion_date'] ?? 'Ongoing') . '</td>
            <td>' . ($row['days_to_complete'] ?? '-') . '</td>
            <td>' . ($row['staff_name'] ?? 'Unassigned') . '</td>
            <td>₹' . number_format($row['actual_cost'] ?? $row['estimated_cost'] ?? 0) . '</td>
        </tr>';
    }
    
    $html .= '</tbody></table>';
    return $html;
}

function generateLoyaltyTable($data) {
    if (empty($data)) return '<p>No loyalty data available</p>';
    
    $html = '<table class="table table-hover" id="reportTable">
        <thead>
            <tr>
                <th>Guest Name</th>
                <th>Contact</th>
                <th>Points Balance</th>
                <th>Tier Level</th>
                <th>Total Points Earned</th>
                <th>Total Points Redeemed</th>
                <th>Last Activity</th>
                <th>Total Bookings</th>
                <th>Total Spent</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach($data as $row) {
        $html .= '<tr>
            <td>' . ($row['full_name'] ?? '') . '</td>
            <td>' . ($row['email'] ?? '') . '<br>' . ($row['phone'] ?? '') . '</td>
            <td>' . ($row['points_balance'] ?? 0) . '</td>
            <td>' . ($row['tier_level'] ?? 'Standard') . '</td>
            <td>' . ($row['total_points_earned'] ?? 0) . '</td>
            <td>' . ($row['total_points_redeemed'] ?? 0) . '</td>
            <td>' . ($row['last_activity'] ?? 'Never') . '</td>
            <td>' . ($row['total_bookings'] ?? 0) . '</td>
            <td>₹' . number_format($row['total_spent'] ?? 0) . '</td>
        </tr>';
    }
    
    $html .= '</tbody></table>';
    return $html;
}
?>