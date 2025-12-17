<?php
// Start the session
session_start();
date_default_timezone_set('Asia/Kolkata'); // for Indian Standard Time

// Include the database connection file
require 'db_connection.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = 'success';

// Fetch user details
$sql = "SELECT name, email, phone, address, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $email, $phone, $address, $role);
$stmt->fetch();
$stmt->close();

// Fetch business information
$business_sql = "SELECT business_name, business_address FROM business_info WHERE user_id = ?";
$business_stmt = $conn->prepare($business_sql);
$business_stmt->bind_param("i", $user_id);
$business_stmt->execute();
$business_stmt->bind_result($business_name, $business_address);
$business_stmt->fetch();
$business_stmt->close();

// If no business info found, set defaults
if (empty($business_name)) {
    $business_name = "Your Restaurant";
    $business_address = "123 Restaurant Street, City";
}

// Date range for reports (default to current month)
// For start date, we want to include everything from 00:00:00 on that day
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] . ' 00:00:00' : date('Y-m-01') . ' 00:00:00';

// For end date, we want to include everything up to 23:59:59 on that day
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] . ' 23:59:59' : date('Y-m-t') . ' 23:59:59';

$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'daily';

// Define status types for the new reports
$status_types = [
    'pending' => 'Pending Orders',
    'confirmed' => 'Confirmed Orders',
    'cancelled' => 'Cancelled Orders',
    'completed' => 'Completed Orders'
];

// Fetch sales data based on report type
$sales_data = [];
$summary_data = [];

if ($report_type === 'daily') {
    // Daily sales report (updated)
$sales_sql = "SELECT 
                DATE(created_at) as sale_date,
                COUNT(*) as total_orders,
                SUM(subtotal) as subtotal,
                SUM(discount_amount) as total_discounts,
                SUM(gst_amount) as total_tax,
                SUM(delivery_charge) as total_delivery,
                SUM(total_amount) as total_sales
              FROM orders 
              WHERE user_id = ? 
              AND status != 'cancelled'
              AND created_at BETWEEN ? AND ?
              GROUP BY DATE(created_at)
              ORDER BY sale_date ASC";

// Order type analysis (updated)
$sales_sql = "SELECT 
                order_type,
                COUNT(*) as total_orders,
                SUM(total_amount) as total_sales,
                SUM(subtotal) as subtotal,
                SUM(discount_amount) as total_discounts,
                SUM(gst_amount) as total_tax,
                SUM(delivery_charge) as total_delivery,
                AVG(total_amount) as avg_order_value
              FROM orders 
              WHERE user_id = ? 
              AND status != 'cancelled'
              AND created_at BETWEEN ? AND ?
              GROUP BY order_type
              ORDER BY total_sales DESC";

// Summary for the period (updated)
$summary_sql = "SELECT 
                  COUNT(*) as total_orders,
                  SUM(total_amount) as total_sales,
                  SUM(subtotal) as subtotal,
                  SUM(discount_amount) as total_discounts,
                  SUM(gst_amount) as total_tax,
                  SUM(delivery_charge) as total_delivery,
                  AVG(total_amount) as avg_order_value
                FROM orders 
                WHERE user_id = ? 
                AND status != 'cancelled'
                AND created_at BETWEEN ? AND ?";
                    
    $stmt = $conn->prepare($summary_sql);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    $summary_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} 
elseif ($report_type === 'order_type') {
    // Order type analysis
    $sales_sql = "SELECT 
                    order_type,
                    COUNT(*) as total_orders,
                    SUM(total_amount) as total_sales,
                    SUM(subtotal) as subtotal,
                    SUM(discount_amount) as total_discounts,
                    SUM(gst_amount) as total_tax,
                    SUM(delivery_charge) as total_delivery,
                    AVG(total_amount) as avg_order_value
                  FROM orders 
                  WHERE user_id = ? 
                  AND created_at BETWEEN ? AND ?
                  GROUP BY order_type
                  ORDER BY total_sales DESC";
                  
    $stmt = $conn->prepare($sales_sql);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $sales_data[] = $row;
    }
    $stmt->close();
    
    // Summary for the period
    $summary_sql = "SELECT 
                      COUNT(*) as total_orders,
                      SUM(total_amount) as total_sales,
                      SUM(subtotal) as subtotal,
                      SUM(discount_amount) as total_discounts,
                      SUM(gst_amount) as total_tax,
                      SUM(delivery_charge) as total_delivery
                    FROM orders 
                    WHERE user_id = ? 
                    AND created_at BETWEEN ? AND ?";
                    
    $stmt = $conn->prepare($summary_sql);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    $summary_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} 
elseif (array_key_exists($report_type, $status_types)) {
    // Status-specific reports (Pending, Confirmed, Cancelled, Completed)
    $status_value = $report_type;
    $status_label = $status_types[$report_type];
    
    $sales_sql = "SELECT 
                    DATE(created_at) as sale_date,
                    COUNT(*) as total_orders,
                    SUM(subtotal) as subtotal,
                    SUM(discount_amount) as total_discounts,
                    SUM(gst_amount) as total_tax,
                    SUM(delivery_charge) as total_delivery,
                    SUM(total_amount) as total_sales
                  FROM orders 
                  WHERE user_id = ? 
                  AND status = ?
                  AND created_at BETWEEN ? AND ?
                  GROUP BY DATE(created_at)
                  ORDER BY sale_date DESC";
                  
    $stmt = $conn->prepare($sales_sql);
    $stmt->bind_param("isss", $user_id, $status_value, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $sales_data[] = $row;
    }
    $stmt->close();
    
    // Summary for the period
    $summary_sql = "SELECT 
                      COUNT(*) as total_orders,
                      SUM(total_amount) as total_sales,
                      SUM(subtotal) as subtotal,
                      SUM(discount_amount) as total_discounts,
                      SUM(gst_amount) as total_tax,
                      SUM(delivery_charge) as total_delivery,
                      AVG(total_amount) as avg_order_value
                    FROM orders 
                    WHERE user_id = ? 
                    AND status = ?
                    AND created_at BETWEEN ? AND ?";
                    
    $stmt = $conn->prepare($summary_sql);
    $stmt->bind_param("isss", $user_id, $status_value, $start_date, $end_date);
    $stmt->execute();
    $summary_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Sales Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">

    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#fb5b29">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="DeeGeeCard">
    <link rel="apple-touch-icon" href="https://deegeecard.com/images/dg_logo.png">
    <meta name="msapplication-TileColor" content="#fb5b29">
    <meta name="msapplication-TileImage" content="https://deegeecard.com/images/dg_logo.png">
    <meta name="application-name" content="DeeGeeCard">
    <meta name="mobile-web-app-capable" content="yes">
    <!-- PWA Meta Tags -->
    
    <link href="assets/css/vendor.min.css" rel="stylesheet" />
    <link href="assets/css/icons.min.css" rel="stylesheet" />
    <link href="assets/css/app.min.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <style>
        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
            display: inline-block;
        }
        .status-pending { background-color: #ffc107; color: #000; }
        .status-confirmed { background-color: #17a2b8; color: #fff; }
        .status-completed { background-color: #28a745; color: #fff; }
        .status-cancelled { background-color: #dc3545; color: #fff; }
        .card-summary {
            transition: all 0.3s ease;
        }
        .card-summary:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php
        if ($role === 'admin') {
            include 'admin_menu.php';
        } else {
            include 'menu.php';
        }
        ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">
                                    <?php 
                                    if (array_key_exists($report_type, $status_types)) {
                                        echo $status_types[$report_type] . " Report";
                                    } else {
                                        echo "Sales Report";
                                    }
                                    ?>
                                    <button id="fullscreenToggle" class="btn btn-primary btn-block fr">Enter Fullscreen</button>
                                </h4>
                            </div>
                            <div class="card-body">
                                <!-- Report Filters -->
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <form method="GET" action="sales_report.php" class="row g-3">
                                            <div class="col-md-3">
                                                <label for="report_type" class="form-label">Report Type</label>
                                                <select class="form-select" id="report_type" name="report_type">
                                                    <option value="daily" <?php echo $report_type === 'daily' ? 'selected' : ''; ?>>Daily Sales</option>
                                                    <option value="order_type" <?php echo $report_type === 'order_type' ? 'selected' : ''; ?>>Order Type Analysis</option>
                                                    <?php foreach ($status_types as $key => $label): ?>
                                                        <option value="<?php echo $key; ?>" <?php echo $report_type === $key ? 'selected' : ''; ?>>
                                                            <?php echo $label; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label for="start_date" class="form-label">Start Date</label>
                                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label for="end_date" class="form-label">End Date</label>
                                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); ?>">
                                            </div>
                                            <div class="col-md-3 d-flex align-items-end">
                                                <button type="submit" class="btn btn-primary">Generate Report</button>
                                                <button type="button" id="exportPdf" class="btn btn-secondary ms-2">
                                                    <i class="bi bi-file-earmark-pdf"></i> Export PDF
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Summary Cards -->
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="card card-summary bg-primary text-white">
                                            <div class="card-body">
                                                <h5 class="card-title">Total Sales</h5>
                                                <h3 class="card-text">₹<?php echo isset($summary_data['total_sales']) ? number_format($summary_data['total_sales'], 2) : '0.00'; ?></h3>
                                                <p class="card-text mb-0"><?php echo isset($summary_data['total_orders']) ? $summary_data['total_orders'] : '0'; ?> orders</p>
                                                <?php if (isset($summary_data['avg_order_value'])): ?>
                                                    <p class="card-text">Avg: ₹<?php echo number_format($summary_data['avg_order_value'], 2); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card card-summary bg-success text-white">
                                            <div class="card-body">
                                                <h5 class="card-title">Subtotal</h5>
                                                <h3 class="card-text">₹<?php echo isset($summary_data['subtotal']) ? number_format($summary_data['subtotal'], 2) : '0.00'; ?></h3>
                                                <p class="card-text mb-0">Before discounts & taxes</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card card-summary bg-info text-white">
                                            <div class="card-body">
                                                <h5 class="card-title">Taxes & Charges</h5>
                                                <h3 class="card-text">₹<?php echo isset($summary_data['total_tax']) ? number_format($summary_data['total_tax'] + ($summary_data['total_delivery'] ?? 0), 2) : '0.00'; ?></h3>
                                                <p class="card-text mb-0">GST: ₹<?php echo isset($summary_data['total_tax']) ? number_format($summary_data['total_tax'], 2) : '0.00'; ?></p>
                                                <p class="card-text">Delivery: ₹<?php echo isset($summary_data['total_delivery']) ? number_format($summary_data['total_delivery'], 2) : '0.00'; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card card-summary bg-warning text-dark">
                                            <div class="card-body">
                                                <h5 class="card-title">Discounts</h5>
                                                <h3 class="card-text">₹<?php echo isset($summary_data['total_discounts']) ? number_format($summary_data['total_discounts'], 2) : '0.00'; ?></h3>
                                                <p class="card-text">Applied to orders</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Chart Section -->
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-body">
                                                <h5 class="card-title">Sales Overview</h5>
                                                <canvas id="salesChart" height="100"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Detailed Report Table -->
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-body">
                                                <h5 class="card-title">Detailed Report</h5>
                                                <div class="table-responsive">
                                                    <table class="table table-hover mb-0" id="reportTable">
                                                        <thead>
                                                            <tr>
                                                                <th>Date</th>
                                                                <th>Orders</th>
                                                                <th>Subtotal</th>
                                                                <th>Discounts</th>
                                                                <th>Tax</th>
                                                                <th>Delivery</th>
                                                                <th>Total Sales</th>
                                                                <th>Avg. Order</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if (empty($sales_data)): ?>
                                                                <tr>
                                                                    <td colspan="8" class="text-center">No data found for the selected period</td>
                                                                </tr>
                                                            <?php else: ?>
                                                                <?php foreach ($sales_data as $row): ?>
                                                                    <tr>
                                                                        <td><?php echo date('M d, Y', strtotime($row['sale_date'])); ?></td>
                                                                        <td><?php echo $row['total_orders']; ?></td>
                                                                        <td>₹<?php echo number_format($row['subtotal'], 2); ?></td>
                                                                        <td>₹<?php echo number_format($row['total_discounts'], 2); ?></td>
                                                                        <td>₹<?php echo number_format($row['total_tax'], 2); ?></td>
                                                                        <td>₹<?php echo number_format($row['total_delivery'], 2); ?></td>
                                                                        <td>₹<?php echo number_format($row['total_sales'], 2); ?></td>
                                                                        <td>₹<?php echo number_format($row['total_sales'] / $row['total_orders'], 2); ?></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
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
                </div>
                <?php include 'footer.php'; ?>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
    $(document).ready(function() {
        // Initialize Chart
        const ctx = document.getElementById('salesChart').getContext('2d');
        
        <?php if (!empty($sales_data)): ?>
            const dates = <?php echo json_encode(array_column($sales_data, 'sale_date')); ?>;
            const sales = <?php echo json_encode(array_column($sales_data, 'total_sales')); ?>;
            const orders = <?php echo json_encode(array_column($sales_data, 'total_orders')); ?>;
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: dates.map(date => new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
                    datasets: [
                        {
                            label: 'Sales (₹)',
                            data: sales,
                            backgroundColor: 'rgba(54, 162, 235, 0.7)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Orders',
                            data: orders,
                            backgroundColor: 'rgba(255, 99, 132, 0.7)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1,
                            type: 'line',
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: '<?php 
                                if (array_key_exists($report_type, $status_types)) {
                                    echo $status_types[$report_type] . " Overview";
                                } else {
                                    echo "Daily Sales and Orders";
                                }
                            ?>'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label.includes('Sales')) {
                                        label += ': ₹' + context.raw.toFixed(2);
                                    } else {
                                        label += ': ' + context.raw;
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Sales (₹)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Orders'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
        <?php else: ?>
            // Empty chart when no data
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: [],
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

        // Export to PDF
        $('#exportPdf').click(function() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            // Title
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(16);
            doc.text('<?php echo addslashes($business_name); ?> - Sales Report', 105, 15, { align: 'center' });
            
            // Report details
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(12);
            doc.text(`Report Type: ${$('#report_type option:selected').text()}`, 14, 25);
            doc.text(`Period: ${$('#start_date').val()} to ${$('#end_date').val()}`, 14, 32);
            
            // Summary
            doc.setFontSize(14);
            doc.text('Summary', 14, 42);
            doc.setFontSize(12);
            
            doc.text(`Total Sales: ₹<?php echo isset($summary_data['total_sales']) ? number_format($summary_data['total_sales'], 2) : '0.00'; ?>`, 14, 50);
            doc.text(`Total Orders: <?php echo isset($summary_data['total_orders']) ? $summary_data['total_orders'] : '0'; ?>`, 14, 57);
            doc.text(`Subtotal: ₹<?php echo isset($summary_data['subtotal']) ? number_format($summary_data['subtotal'], 2) : '0.00'; ?>`, 14, 64);
            doc.text(`Discounts: ₹<?php echo isset($summary_data['total_discounts']) ? number_format($summary_data['total_discounts'], 2) : '0.00'; ?>`, 14, 71);
            
            // Table
            doc.setFontSize(14);
            doc.text('Detailed Report', 14, 85);
            
            const headers = [];
            const rows = [];
            
            // Get headers
            $('#reportTable thead th').each(function() {
                headers.push($(this).text());
            });
            
            // Get rows
            $('#reportTable tbody tr').each(function() {
                const row = [];
                $(this).find('td').each(function() {
                    row.push($(this).text());
                });
                rows.push(row);
            });
            
            // AutoTable
            doc.autoTable({
                startY: 90,
                head: [headers],
                body: rows,
                margin: { left: 14 },
                styles: { fontSize: 10 }
            });
            
            // Save PDF
            const reportType = $('#report_type option:selected').text();
            doc.save(`${reportType}_Report_${$('#start_date').val()}_to_${$('#end_date').val()}.pdf`);
        });

        // Enhanced Fullscreen Handling
        const toggleBtn = document.getElementById('fullscreenToggle');
        let isManualExit = false;

        function isFullscreen() {
            return !!document.fullscreenElement || 
                   !!document.webkitFullscreenElement || 
                   !!document.msFullscreenElement;
        }

        function enterFullscreen() {
            const elem = document.documentElement;
            isManualExit = false;
            
            if (elem.requestFullscreen) {
                elem.requestFullscreen().catch(err => {
                    console.error('Fullscreen error:', err);
                });
            } else if (elem.webkitRequestFullscreen) {
                elem.webkitRequestFullscreen();
            } else if (elem.msRequestFullscreen) {
                elem.msRequestFullscreen();
            }
        }

        function exitFullscreen() {
            isManualExit = true;
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
        }

        function handleFullscreenChange() {
            if (!isFullscreen() && !isManualExit) {
                // Fullscreen was exited unexpectedly (like when navigating away)
                // Reset the button state
                updateButtonLabel();
            }
            updateButtonLabel();
        }

        function updateButtonLabel() {
            if (toggleBtn) {
                toggleBtn.textContent = isFullscreen() ? 'Exit Fullscreen' : 'Enter Fullscreen';
            }
        }

        toggleBtn.addEventListener('click', () => {
            if (isFullscreen()) {
                exitFullscreen();
            } else {
                enterFullscreen();
            }
        });

        // Set up event listeners
        document.addEventListener('fullscreenchange', handleFullscreenChange);
        document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
        document.addEventListener('msfullscreenchange', handleFullscreenChange);

        // Initialize button state
        updateButtonLabel();

        // Clean up on page unload
        window.addEventListener('beforeunload', () => {
            if (isFullscreen()) {
                exitFullscreen();
            }
        });
    });
    </script>
</body>
</html>