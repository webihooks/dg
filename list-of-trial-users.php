<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_sql = "SELECT role, name FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->bind_result($role, $logged_in_name);
$user_stmt->fetch();
$user_stmt->close();

if ($role !== 'admin') {
    header("Location: index.php");
    exit();
}

// Handle form submission for approving trial users
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_trial'])) {
    $trial_user_id = $_POST['user_id'];
    $package_id = $_POST['package_id'];
    
    // Get package details
    $package_sql = "SELECT id, name, price, duration FROM packages WHERE id = ?";
    $package_stmt = $conn->prepare($package_sql);
    $package_stmt->bind_param("i", $package_id);
    $package_stmt->execute();
    $package_result = $package_stmt->get_result();
    $package = $package_result->fetch_assoc();
    $package_stmt->close();
    
    if ($package) {
        $start_date = date('Y-m-d H:i:s');
        $end_date = date('Y-m-d H:i:s', strtotime("+{$package['duration']} days"));
        $renewal_date = $end_date;
        $package_name = $package['name'];
        
        // Insert new subscription - corrected parameter count
        $insert_sql = "INSERT INTO subscriptions (
            user_id, package_id, subscription_type, start_date, end_date, renewal_date, 
            status, last_payment_date, next_payment_date, auto_renewal, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?, 1, NOW(), NOW())";
        
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param(
            "iissssss", // 8 parameters: user_id, package_id, subscription_type, start_date, end_date, renewal_date, last_payment_date, next_payment_date
            $trial_user_id, 
            $package_id, 
            $package_name,
            $start_date, 
            $end_date, 
            $renewal_date,
            $start_date, 
            $renewal_date
        );
        
        if ($insert_stmt->execute()) {
            // Deactivate the trial subscription
            $deactivate_sql = "UPDATE trial_subscriptions SET is_active = 0 WHERE user_id = ?";
            $deactivate_stmt = $conn->prepare($deactivate_sql);
            $deactivate_stmt->bind_param("i", $trial_user_id);
            $deactivate_stmt->execute();
            $deactivate_stmt->close();
            
            $success_message = "Trial user successfully converted to regular subscriber! Package: " . htmlspecialchars($package_name);
        } else {
            $error_message = "Error converting trial user: " . $conn->error;
        }
        $insert_stmt->close();
    } else {
        $error_message = "Selected package not found!";
    }
}

// Pagination
$records_per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $records_per_page;

// Count total records in trial_subscriptions excluding users with regular subscriptions
$count_sql = "SELECT COUNT(*) AS total 
              FROM trial_subscriptions ts
              WHERE NOT EXISTS (
                  SELECT 1 FROM subscriptions s 
                  WHERE s.user_id = ts.user_id
              )";
$count_result = $conn->query($count_sql);
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch trial subscriptions data with user details, excluding users with regular subscriptions
$sql = "SELECT ts.id, ts.user_id, u.name as user_name, u.email, u.phone, 
               ts.start_date, ts.end_date, ts.is_active, ts.created_at 
        FROM trial_subscriptions ts
        JOIN users u ON ts.user_id = u.id
        WHERE NOT EXISTS (
            SELECT 1 FROM subscriptions s 
            WHERE s.user_id = ts.user_id
        )
        ORDER BY ts.created_at DESC 
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $offset, $records_per_page);
$stmt->execute();
$result = $stmt->get_result();

// Fetch active packages for the dropdown
$packages_sql = "SELECT id, name, price, duration FROM packages WHERE is_active = 1";
$packages_result = $conn->query($packages_sql);

// Fetch user name
$sql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name);
$stmt->fetch();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>List of Trial Subscriptions | Admin</title>
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
    
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <style>
        .badge {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            line-height: 1;
            color: #fff;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
        }

        .badge-secondary {
            background-color: #6c757d;
        }

        /* Optional: Add some transition effects */
        .badge {
            transition: color 0.15s ease-in-out, 
                        background-color 0.15s ease-in-out, 
                        border-color 0.15s ease-in-out, 
                        box-shadow 0.15s ease-in-out;
        }

        /* Optional: Add hover effects */
        .badge-secondary:hover {
            background-color: #5a6268;
        }

        /* For the success badge you're also using */
        .badge-success {
            background-color: #28a745;
        }

        .badge-success:hover {
            background-color: #218838;
        }
        /* Style for the form container */
td form.form-inline {
    display: flex;
    align-items: center; /* Vertically center items */
    gap: 0.5rem; /* Space between elements */
    width: 100%; /* Take full width of cell */
}

/* Style for the select dropdown */
td form.form-inline select.form-control-sm {
    flex: 1; /* Allow dropdown to grow and fill available space */
    min-width: 150px; /* Minimum width to prevent squeezing */
}

/* Style for the approve button */
td form.form-inline button.btn-success {
    white-space: nowrap; /* Prevent button text from wrapping */
    flex-shrink: 0; /* Prevent button from shrinking */
}

/* Optional: Responsive adjustments */
@media (max-width: 768px) {
    td form.form-inline {
        flex-direction: column;
        align-items: stretch;
        gap: 0.3rem;
    }
    
    td form.form-inline select.form-control-sm {
        width: 100%;
    }
    
    td form.form-inline button.btn-success {
        width: 100%;
    }
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
                                <h4 class="card-title">List of Trial Subscriptions (Excluding Users with Regular Subscriptions)</h4>
                            </div>
                            <div class="card-body">
                                <?php if (isset($success_message)): ?>
                                    <div class="alert alert-success"><?= $success_message ?></div>
                                <?php endif; ?>
                                <?php if (isset($error_message)): ?>
                                    <div class="alert alert-danger"><?= $error_message ?></div>
                                <?php endif; ?>
                                
                                <div class="table-responsive">
                                    <table class="table table-centered table-striped table-hover">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>User Details</th>
                                                <th>Contact Info</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Status</th>
                                                <!-- <th>Created At</th> -->
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($row = $result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['id']) ?></td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($row['user_name']) ?></strong><br>
                                                        <small class="text-muted">ID: <?= $row['user_id'] ?></small>
                                                    </td>
                                                    <td>
                                                        <div><?= htmlspecialchars($row['email']) ?></div>
                                                        <div><?= htmlspecialchars($row['phone']) ?></div>
                                                    </td>
                                                    <td><?= date('M d, Y', strtotime($row['start_date'])) ?></td>
                                                    
                                                    <td>
                                                        <span class="badge <?= $row['is_active'] ? 'badge-success' : 'badge-secondary' ?>">
                                                            <?= $row['is_active'] ? 'Active' : 'Inactive' ?>
                                                        </span>
                                                    </td>
                                                    <td><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></td>
                                                    <td>
                                                        <form method="POST" action="" class="form-inline">
                                                            <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                                                            <select name="package_id" class="form-control form-control-sm mr-2" required>
                                                                <option value="">Select Package</option>
                                                                <?php while ($package = $packages_result->fetch_assoc()): ?>
                                                                    <option value="<?= $package['id'] ?>">
                                                                        <?= htmlspecialchars($package['name']) ?> 
                                                                        (₹<?= number_format($package['price']) ?>, 
                                                                        <?= $package['duration'] ?> days)
                                                                    </option>
                                                                <?php endwhile; ?>
                                                                <?php $packages_result->data_seek(0); // Reset pointer for next row ?>
                                                            </select>
                                                            <button type="submit" name="approve_trial" class="btn btn-success btn-sm">
                                                                Approve
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>

                                    <?php if ($total_pages > 1): ?>
                                    <nav aria-label="Page navigation example">
                                        <ul class="pagination justify-content-center mb-0">
                                            <!-- Previous Button -->
                                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link" href="<?= $page > 1 ? '?page=' . ($page - 1) : 'javascript:void(0);' ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>

                                            <!-- Page Numbers -->
                                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>

                                            <!-- Next Button -->
                                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                                <a class="page-link" href="<?= $page < $total_pages ? '?page=' . ($page + 1) : 'javascript:void(0);' ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </nav>
                                    <?php endif; ?>
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
</body>
</html>