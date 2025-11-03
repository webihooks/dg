<?php
// Start the session
session_start();

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
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_stmt->bind_result($role);
$role_stmt->fetch();
$role_stmt->close();

// Check if user is admin, otherwise redirect
if ($role !== 'admin') {
    header("Location: index.php");
    exit();
}

// Fetch user name
$sql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name);
$stmt->fetch();
$stmt->close();

// Get current month and year
$current_month = date('m');
$current_year = date('Y');

// Get filter parameters
$filter_user = isset($_GET['user_filter']) ? $_GET['user_filter'] : '';
$filter_date = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';

// Base query - Only show current month orders by default
$orders_sql = "SELECT o.*, 
                      u.id as user_id, 
                      u.name as user_name, 
                      u.email as user_email,
                      p.profile_url
               FROM orders o 
               JOIN users u ON o.user_id = u.id 
               LEFT JOIN profile_url_details p ON u.id = p.user_id
               WHERE u.id != 28 
               AND MONTH(o.created_at) = ? 
               AND YEAR(o.created_at) = ?";

// Add filters to query
if (!empty($filter_user)) {
    $orders_sql .= " AND u.name LIKE '%" . $conn->real_escape_string($filter_user) . "%'";
}
if (!empty($filter_date)) {
    $orders_sql .= " AND DATE(o.created_at) = '" . $conn->real_escape_string($filter_date) . "'";
}

$orders_sql .= " ORDER BY o.created_at DESC";

// Prepare and execute the orders query
$orders_stmt = $conn->prepare($orders_sql);
$orders_stmt->bind_param("ii", $current_month, $current_year);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();

// Fetch all users for filter dropdown
$users_sql = "SELECT id, name FROM users WHERE id != 28 ORDER BY name";
$users_result = $conn->query($users_sql);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>All Orders | Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        .order-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-processing {
            background-color: #cce5ff;
            color: #004085;
        }
        .dataTables_wrapper .dataFilters_filter input {
            margin-left: 0.5em;
            border: 1px solid #dee2e6;
            padding: 5px 10px;
            border-radius: 4px;
        }
        .dataTables_length select {
            border: 1px solid #dee2e6;
            padding: 5px;
            border-radius: 4px;
        }
        .profile-url {
            color: #3b5de7;
            text-decoration: none;
        }
        .profile-url:hover {
            text-decoration: underline;
        }
        .filter-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .filter-section .form-group {
            margin-bottom: 15px;
        }
        .filter-btn {
            margin-right: 10px;
        }
        th, td {
          padding: 3px !important;
          font-size: 12px !important;
        }
        .order-status {
          padding: 5px;
          border-radius: 20px;
          font-size: 10px;
          font-weight: 600;
          text-transform: capitalize;
        }
        th:nth-child(11), th:nth-child(12) {
            width: 75px !important;
        }
        .btn-primary {
            font-size: 10px !important;
        }
        .scroll-to-top {
            bottom: 15px;
        }
        /* Remove pagination styling */
        .dataTables_paginate {
            display: none !important;
        }
        .dataTables_info {
            padding-top: 10px !important;
        }
        .current-month-notice {
            background-color: #e7f3ff;
            border-left: 4px solid #3b5de7;
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-size: 14px;
        }
    </style>
</head>

<body>

    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'admin_menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">All Orders - <?php echo date('F Y'); ?></h4>
                                <p class="card-title-desc">View and manage all customer orders for current month</p>
                            </div>
                            <div class="card-body">
                                <!-- Current Month Notice -->
                                <div class="current-month-notice">
                                    <strong>Note:</strong> Currently showing orders for <?php echo date('F Y'); ?> only. Use date filter to view orders from specific dates.
                                </div>

                                <!-- Filter Section -->
                                <div class="filter-section">
                                    <form method="GET" action="">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="user_filter">Filter by User:</label>
                                                    <select class="form-control" id="user_filter" name="user_filter">
                                                        <option value="">All Users</option>
                                                        <?php while ($user = $users_result->fetch_assoc()): ?>
                                                        <option value="<?php echo htmlspecialchars($user['name']); ?>" <?php echo ($filter_user == $user['name']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($user['name']); ?>
                                                        </option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="date_filter">Filter by Date:</label>
                                                    <input type="date" class="form-control" id="date_filter" name="date_filter" value="<?php echo htmlspecialchars($filter_date); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4 d-flex align-items-end">
                                                <div class="form-group">
                                                    <button type="submit" class="btn btn-primary filter-btn">Apply Filters</button>
                                                    <a href="all_orders.php" class="btn btn-secondary">Reset</a>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                
                                <!-- Orders Table -->
                                <div class="table-responsive">
                                    <table id="ordersTable" class="table" style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                                        <thead>
                                            <tr>
                                                <th>Sr. No.</th>
                                                <th>Order ID</th>
                                                <th>User ID</th>
                                                <th>User Name</th>
                                                <th>Profile URL</th>
                                                <th>Customer</th>
                                                <th>Phone</th>
                                                <th>Type</th>
                                                <th>Subtotal</th>
                                                <th>Total</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $counter = 1;
                                            while ($order = $orders_result->fetch_assoc()): 
                                            ?>
                                            <tr>
                                                <td><?php echo $counter++; ?></td>
                                                <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                                                <td><?php echo htmlspecialchars($order['user_id']); ?></td>
                                                <td><?php echo htmlspecialchars($order['user_name']); ?></td>
                                                <td>
                                                    <?php if (!empty($order['profile_url'])): ?>
                                                        <a href="https://deegeecard.com/<?php echo htmlspecialchars($order['profile_url']); ?>" 
                                                           class="profile-url" 
                                                           target="_blank">
                                                           <?php echo htmlspecialchars($order['profile_url']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not set</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                                <td><?php echo htmlspecialchars($order['customer_phone']); ?></td>
                                                <td><?php echo htmlspecialchars(ucfirst($order['order_type'])); ?></td>
                                                <td>₹<?php echo number_format($order['subtotal'], 2); ?></td>
                                                <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                                <td>
                                                    <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                                                        <?php echo ucfirst($order['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d M Y h:i A', strtotime($order['created_at'])); ?></td>
                                                <td>
                                                    <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-primary">View</a>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
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
        $(document).ready(function() {
            // Initialize date picker
            flatpickr("#date_filter", {
                dateFormat: "Y-m-d",
                allowInput: true
            });

            // Initialize DataTable with proper configuration
            $('#ordersTable').DataTable({
                responsive: true,
                paging: false, // Remove pagination
                info: true, // Keep showing "Showing X of Y entries"
                ordering: true,
                order: [[11, 'desc']], // Sort by date column (index 11) descending
                columnDefs: [
                    { responsivePriority: 1, targets: 1 }, // Order ID
                    { responsivePriority: 2, targets: 3 }, // User Name
                    { responsivePriority: 3, targets: 5 }, // Customer Name
                    { responsivePriority: 4, targets: 10 }, // Status
                    { responsivePriority: 5, targets: 12 }, // Actions
                    { orderable: false, targets: [0, 12] }, // Make Sr. No. and Actions columns not sortable
                    { searchable: false, targets: [0, 12] } // Make Sr. No. and Actions columns not searchable
                ],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search orders...",
                    info: "Showing _TOTAL_ orders",
                    infoEmpty: "No orders available",
                    infoFiltered: "(filtered from _MAX_ total orders)",
                    zeroRecords: "No orders found"
                },
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
            });
        });
    </script>

</body>
</html>