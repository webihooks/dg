<?php
// Start the session
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
date_default_timezone_set('Asia/Kolkata');

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

// Get user role
$role_sql = "SELECT role FROM users WHERE id = ?";
$role_stmt = $conn->prepare($role_sql);
if ($role_stmt) {
    $role_stmt->bind_param("i", $user_id);
    $role_stmt->execute();
    $role_stmt->bind_result($role);
    $role_stmt->fetch();
    $role_stmt->close();
} else {
    $error_message = "Error preparing role query: " . $conn->error;
    $role = 'user';
}

// Fetch user name
$sql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($user_name);
    $stmt->fetch();
    $stmt->close();
} else {
    $error_message = "Error preparing user name query: " . $conn->error;
    $user_name = 'User';
}

// DASHBOARD STATISTICS - ALIGNED WITH FINANCE-MANAGEMENT.PHP

// 1. TOTAL REVENUE (Same as Income in finance-management.php)
$revenue_sql = "SELECT COALESCE(SUM(amount), 0) as total_revenue 
                FROM dg_transactions 
                WHERE type = 'income'";
if ($role !== 'admin') {
    $revenue_sql .= " AND user_id = ?";
}

$revenue_stmt = $conn->prepare($revenue_sql);
if ($revenue_stmt) {
    if ($role !== 'admin') {
        $revenue_stmt->bind_param("i", $user_id);
    }
    $revenue_stmt->execute();
    $revenue_stmt->bind_result($total_revenue);
    $revenue_stmt->fetch();
    $revenue_stmt->close();
} else {
    $total_revenue = 0;
}

// 2. TOTAL EXPENSES (Same as Expenses in finance-management.php)
$expenses_sql = "SELECT COALESCE(SUM(amount), 0) as total_expenses 
                 FROM dg_transactions 
                 WHERE type = 'expense'";
if ($role !== 'admin') {
    $expenses_sql .= " AND user_id = ?";
}

$expenses_stmt = $conn->prepare($expenses_sql);
if ($expenses_stmt) {
    if ($role !== 'admin') {
        $expenses_stmt->bind_param("i", $user_id);
    }
    $expenses_stmt->execute();
    $expenses_stmt->bind_result($total_expenses);
    $expenses_stmt->fetch();
    $expenses_stmt->close();
} else {
    $total_expenses = 0;
}

// 3. TOTAL USERS (Only for admin)
$total_users = 0;
if ($role === 'admin') {
    $users_sql = "SELECT COUNT(*) as total_users FROM users WHERE role = 'user'";
    $users_result = $conn->query($users_sql);
    if ($users_result) {
        $total_users = $users_result->fetch_assoc()['total_users'];
    }
}

// 4. ACTIVE ORDERS
$active_orders_sql = "SELECT COUNT(*) as active_orders FROM orders 
                      WHERE status IN ('Pending', 'Confirmed', 'Preparing', 'Ready')";
if ($role !== 'admin') {
    $active_orders_sql .= " AND user_id = ?";
}

$active_orders_stmt = $conn->prepare($active_orders_sql);
if ($active_orders_stmt) {
    if ($role !== 'admin') {
        $active_orders_stmt->bind_param("i", $user_id);
    }
    $active_orders_stmt->execute();
    $active_orders_stmt->bind_result($active_orders);
    $active_orders_stmt->fetch();
    $active_orders_stmt->close();
} else {
    $active_orders = 0;
}

// 5. DEEGEECARD BANK (Same calculation as finance-management.php)
$deegee_bank_sql = "SELECT COALESCE(SUM(amount), 0) as deegee_bank_total 
                    FROM dg_transactions 
                    WHERE category_id = 13"; // 13 is the ID for DeeGeeCard Bank
if ($role !== 'admin') {
    $deegee_bank_sql .= " AND user_id = ?";
}

$deegee_bank_stmt = $conn->prepare($deegee_bank_sql);
if ($deegee_bank_stmt) {
    if ($role !== 'admin') {
        $deegee_bank_stmt->bind_param("i", $user_id);
    }
    $deegee_bank_stmt->execute();
    $deegee_bank_stmt->bind_result($deegee_bank_total);
    $deegee_bank_stmt->fetch();
    $deegee_bank_stmt->close();
} else {
    $deegee_bank_total = 0;
}

// 6. BALANCE (Income - Expenses) - Same as finance-management.php
$balance = $total_revenue - $total_expenses;

// 7. PENDING CARD ASSIGNMENTS
$pending_cards_sql = "SELECT COUNT(*) as pending_cards FROM cards_assignment WHERE status = 'pending'";
if ($role !== 'admin') {
    $pending_cards_sql .= " AND user_id = ?";
}

$pending_cards_stmt = $conn->prepare($pending_cards_sql);
if ($pending_cards_stmt) {
    if ($role !== 'admin') {
        $pending_cards_stmt->bind_param("i", $user_id);
    }
    $pending_cards_stmt->execute();
    $pending_cards_stmt->bind_result($pending_cards);
    $pending_cards_stmt->fetch();
    $pending_cards_stmt->close();
} else {
    $pending_cards = 0;
}

// CHARTS DATA - Revenue Trend (Last 6 months)
$monthly_revenue_sql = "SELECT 
                          DATE_FORMAT(date, '%Y-%m') as month,
                          COALESCE(SUM(amount), 0) as revenue
                        FROM dg_transactions 
                        WHERE type = 'income' 
                        AND date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
if ($role !== 'admin') {
    $monthly_revenue_sql .= " AND user_id = ?";
}
$monthly_revenue_sql .= " GROUP BY DATE_FORMAT(date, '%Y-%m')
                          ORDER BY month DESC
                          LIMIT 6";

$monthly_revenue_stmt = $conn->prepare($monthly_revenue_sql);
$chart_labels = [];
$chart_data = [];

if ($monthly_revenue_stmt) {
    if ($role !== 'admin') {
        $monthly_revenue_stmt->bind_param("i", $user_id);
    }
    $monthly_revenue_stmt->execute();
    $result = $monthly_revenue_stmt->get_result();
    
    $monthly_revenue = [];
    while ($row = $result->fetch_assoc()) {
        $monthly_revenue[] = $row;
        $chart_labels[] = date('M Y', strtotime($row['month'] . '-01'));
        $chart_data[] = floatval($row['revenue']);
    }
    
    // Reverse to show chronological order
    $chart_labels = array_reverse($chart_labels);
    $chart_data = array_reverse($chart_data);
    $monthly_revenue_stmt->close();
}

// User Growth Data (Only for admin)
$user_growth_labels = [];
$user_growth_data = [];
if ($role === 'admin') {
    $user_growth_sql = "SELECT 
                          DATE_FORMAT(created_at, '%Y-%m') as month,
                          COUNT(*) as new_users
                        FROM users
                        WHERE role = 'user'
                        AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                        ORDER BY month DESC
                        LIMIT 6";
    $user_growth_result = $conn->query($user_growth_sql);
    
    if ($user_growth_result) {
        while ($row = $user_growth_result->fetch_assoc()) {
            $user_growth_labels[] = date('M Y', strtotime($row['month'] . '-01'));
            $user_growth_data[] = intval($row['new_users']);
        }
        // Reverse to show chronological order
        $user_growth_labels = array_reverse($user_growth_labels);
        $user_growth_data = array_reverse($user_growth_data);
    }
}

// Recent Transactions
$recent_transactions_sql = "SELECT 
                              t.amount, 
                              t.type, 
                              t.description, 
                              t.date,
                              c.name as category,
                              u.name as user_name
                            FROM dg_transactions t
                            LEFT JOIN dg_categories c ON t.category_id = c.id
                            LEFT JOIN users u ON t.user_id = u.id";
if ($role !== 'admin') {
    $recent_transactions_sql .= " WHERE t.user_id = ?";
}
$recent_transactions_sql .= " ORDER BY t.date DESC, t.created_at DESC LIMIT 10";

$recent_transactions_stmt = $conn->prepare($recent_transactions_sql);
$recent_transactions = [];
if ($recent_transactions_stmt) {
    if ($role !== 'admin') {
        $recent_transactions_stmt->bind_param("i", $user_id);
    }
    $recent_transactions_stmt->execute();
    $result = $recent_transactions_stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recent_transactions[] = $row;
    }
    $recent_transactions_stmt->close();
}

// Sales Performance (Only for admin)
$sales_performance = [];
if ($role === 'admin') {
    $sales_sql = "SELECT 
                    u.name as sales_person,
                    COUNT(st.id) as leads,
                    SUM(CASE WHEN st.status = 'completed' THEN 1 ELSE 0 END) as converted,
                    COALESCE(SUM(sp.package_price + sp.advance_payment), 0) as revenue
                  FROM sales_track st
                  LEFT JOIN users u ON st.user_id = u.id
                  LEFT JOIN subscription_payments sp ON st.user_id = sp.user_id
                  WHERE u.role = 'sales_person'
                  GROUP BY u.id, u.name";
    $sales_result = $conn->query($sales_sql);
    while ($row = $sales_result->fetch_assoc()) {
        $sales_performance[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
        
        .stat-card {
            transition: transform 0.3s ease;
            min-height: 120px;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .revenue-positive {
            color: #28a745;
            font-weight: bold;
        }
        
        .revenue-negative {
            color: #dc3545;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .container .col-md-3.mb-3,
            .container .row.mb-4, 
            .container .col-md-4.mb-3 {
                margin-bottom: 0 !important;
            }
            .stat-card .card-body {
                padding: 15px;
            }
            
            .stat-card h2 {
                font-size: 1.5rem;
            }
            
            .stat-card h5 {
                font-size: 0.9rem;
            }
            
            .chart-container {
                height: 250px;
            }
        }
        .stat-card h3, .stat-card h5, .stat-card small  {
          color: #fff !important;
          text-shadow: 1px 1px 1px #000;
        }
        .scroll-to-top {
            bottom: 15px;
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
        } else {
            include 'menu.php';
        }
        ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        
                        <div class="card mb-3">
                            <div class="card-header">
                                <h4 class="card-title">Dashboard Overview</h4>
                                <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($user_name); ?>! 
                                <?php if ($role === 'admin'): ?>(Administrator)<?php endif; ?></p>
                            </div>
                        </div>
                        
                        <!-- Quick Stats - Aligned with Finance Management -->
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <div class="card stat-card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h5>Total Revenue</h5>
                                        <h3>₹<?php echo number_format($total_revenue); ?></h3>
                                        <small>Total Income</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card stat-card bg-danger text-white">
                                    <div class="card-body text-center">
                                        <h5>Total Expenses</h5>
                                        <h3>₹<?php echo number_format($total_expenses); ?></h3>
                                        <small>All Expenses</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card stat-card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <h5>Net Balance</h5>
                                        <h3 class="<?php echo $balance >= 0 ? 'revenue-positive' : 'revenue-negative'; ?>">
                                            ₹<?php echo number_format($balance); ?>
                                        </h3>
                                        <small>Revenue - Expenses</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card stat-card bg-warning text-dark">
                                    <div class="card-body text-center">
                                        <h5>DeeGeeCard Bank</h5>
                                        <h3>₹<?php echo number_format($deegee_bank_total); ?></h3>
                                        <small>Bank Balance</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Stats Row -->
                        <div class="row">
                            <?php if ($role === 'admin'): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card stat-card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h5>Total Users</h5>
                                        <h3><?php echo $total_users; ?></h3>
                                        <small>Registered Businesses</small>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="col-md-4 mb-3">
                                <div class="card stat-card bg-secondary text-white">
                                    <div class="card-body text-center">
                                        <h5>Active Orders</h5>
                                        <h3><?php echo $active_orders; ?></h3>
                                        <small>In Progress</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <div class="card stat-card bg-dark text-white">
                                    <div class="card-body text-center">
                                        <h5>Pending Cards</h5>
                                        <h3><?php echo $pending_cards; ?></h3>
                                        <small>Awaiting Processing</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Charts Section -->
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Revenue Trend (Last 6 Months)</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="revenueChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <?php if ($role === 'admin'): ?>
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">User Growth</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="userGrowthChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Quick Actions</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-grid gap-2">
                                            <a href="finance-management.php" class="btn btn-primary">Manage Finances</a>
                                            <a href="orders.php" class="btn btn-success">View Orders</a>
                                            <a href="products.php" class="btn btn-info">Manage Products</a>
                                            <a href="cards_assignment.php" class="btn btn-warning">Card Printing</a>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Sales Performance (Admin Only) -->
                        <?php if ($role === 'admin' && !empty($sales_performance)): ?>
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Sales Team Performance</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Sales Person</th>
                                                        <th>Leads</th>
                                                        <th>Converted</th>
                                                        <th>Conversion Rate</th>
                                                        <th>Revenue</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($sales_performance as $sales): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($sales['sales_person']); ?></td>
                                                            <td><?php echo $sales['leads']; ?></td>
                                                            <td><?php echo $sales['converted']; ?></td>
                                                            <td>
                                                                <?php 
                                                                $conversion_rate = $sales['leads'] > 0 ? ($sales['converted'] / $sales['leads']) * 100 : 0;
                                                                echo number_format($conversion_rate, 1) . '%';
                                                                ?>
                                                            </td>
                                                            <td>₹<?php echo number_format($sales['revenue'], 2); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Recent Transactions -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Recent Transactions</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Type</th>
                                                        <th>Category</th>
                                                        <th>Description</th>
                                                        <th>Amount</th>
                                                        <?php if ($role === 'admin'): ?>
                                                        <th>User</th>
                                                        <?php endif; ?>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($recent_transactions as $transaction): ?>
                                                        <tr>
                                                            <td><?php echo date('M d, Y', strtotime($transaction['date'])); ?></td>
                                                            <td>
                                                                <span class="badge bg-<?php echo $transaction['type'] === 'income' ? 'success' : 'danger'; ?>">
                                                                <?php echo ucfirst($transaction['type']); ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($transaction['category']); ?></td>
                                                            <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                                            <td class="<?php echo $transaction['type'] === 'income' ? 'text-success' : 'text-danger'; ?>">
                                                                <?php echo ($transaction['type'] === 'income' ? '+' : '-') . '₹' . number_format($transaction['amount']); ?>
                                                            </td>
                                                            <?php if ($role === 'admin'): ?>
                                                            <td><?php echo htmlspecialchars($transaction['user_name']); ?></td>
                                                            <?php endif; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($recent_transactions)): ?>
                                                        <tr>
                                                            <td colspan="<?php echo $role === 'admin' ? '6' : '5'; ?>" class="text-center">No recent transactions</td>
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
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart');
        if (revenueCtx) {
            const revenueChart = new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chart_labels); ?>,
                    datasets: [{
                        label: 'Revenue (₹)',
                        data: <?php echo json_encode($chart_data); ?>,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Revenue: ₹' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₹' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }

        // User Growth Chart (Admin Only)
        const userGrowthCtx = document.getElementById('userGrowthChart');
        if (userGrowthCtx) {
            const userGrowthChart = new Chart(userGrowthCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($user_growth_labels); ?>,
                    datasets: [{
                        label: 'New Users',
                        data: <?php echo json_encode($user_growth_data); ?>,
                        backgroundColor: '#17a2b8',
                        borderColor: '#138496',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Session status indicator management
        function updateSessionStatus() {
            const indicator = $('#sessionStatusIndicator');
            if (indicator.length) {
                setInterval(() => {
                    $.get('session_health_check.php', function(data) {
                        if (data.status === 'success') {
                            console.log('Admin session active');
                        }
                    }).fail(() => {
                        console.log('Session health check failed');
                    });
                }, 30000);
            }
        }

        // Android-specific session maintenance
        function androidSessionMaintenance() {
            if (navigator.userAgent.includes('WebToNative') || <?php echo $sessionManager->isAndroidApp() ? 'true' : 'false'; ?>) {
                setInterval(() => {
                    $.ajax({
                        url: 'heartbeat.php',
                        method: 'GET',
                        xhrFields: {
                            withCredentials: true
                        },
                        success: function(data) {
                            console.log('Admin Android session maintained');
                        }
                    });
                }, 300000);
            }
        }

        // Initialize session monitoring
        updateSessionStatus();
        androidSessionMaintenance();

        // Auto-refresh dashboard every 2 minutes
        setInterval(() => {
            // You can implement partial refresh here if needed
            console.log('Dashboard auto-refresh check');
        }, 120000);
    });
    </script>

</body>
</html>