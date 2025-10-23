<?php
session_start();
require 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$is_admin = false;

// Check if user is admin
$user_sql = "SELECT role FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

// Get user role
$role_sql = "SELECT role FROM users WHERE id = ?";
$role_stmt = $conn->prepare($role_sql);
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_stmt->bind_result($role);
$role_stmt->fetch();
$role_stmt->close();

if ($user['role'] === 'admin') {
    $is_admin = true;
}

// Fetch tickets based on user role
if ($is_admin) {
    $sql = "SELECT t.*, u.name as user_name 
            FROM tickets t
            JOIN users u ON t.user_id = u.id
            ORDER BY t.created_at DESC";
} else {
    $sql = "SELECT t.*, u.name as user_name 
            FROM tickets t
            JOIN users u ON t.user_id = u.id
            WHERE t.user_id = ?
            ORDER BY t.created_at DESC";
}

$stmt = $conn->prepare($sql);
if (!$is_admin) {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch user name for display
$user_name = '';
$name_sql = "SELECT name FROM users WHERE id = ?";
$name_stmt = $conn->prepare($name_sql);
$name_stmt->bind_param("i", $user_id);
$name_stmt->execute();
$name_stmt->bind_result($user_name);
$name_stmt->fetch();
$name_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Support Tickets</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <link href="https://cdn.materialdesignicons.com/5.4.55/css/materialdesignicons.min.css" rel="stylesheet">
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        
        <?php
        if ($role === 'admin') {
            include 'admin_menu.php';
        } elseif ($role === 'sales_person') {
            include 'sales_menu.php';
        } else {
            include 'menu.php';
        }
        ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h4 class="card-title">Support Tickets</h4>
                                    <a href="create_ticket.php" class="btn btn-primary">
                                        <i class="mdi mdi-plus"></i> Create New Ticket
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (isset($_SESSION['success'])): ?>
                                    <div class="alert alert-success">
                                        <?= htmlspecialchars($_SESSION['success']) ?>
                                        <?php unset($_SESSION['success']); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (isset($_SESSION['error'])): ?>
                                    <div class="alert alert-danger">
                                        <?= htmlspecialchars($_SESSION['error']) ?>
                                        <?php unset($_SESSION['error']); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (empty($tickets)): ?>
                                    <div class="alert alert-info">
                                        No tickets found. <a href="create_ticket.php">Create your first ticket</a>.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <?php if ($is_admin): ?>
                                                        <th>User</th>
                                                    <?php endif; ?>
                                                    <th>Ticket ID</th>
                                                    <th>Subject</th>
                                                    <th>Department</th>
                                                    <th>Priority</th>
                                                    <th>Status</th>
                                                    <th>Created</th>
                                                    <th width="170">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($tickets as $ticket): ?>
                                                    <tr>
                                                        <?php if ($is_admin): ?>
                                                            <td><?= htmlspecialchars($ticket['user_name']) ?></td>
                                                        <?php endif; ?>
                                                        <td>#<?= $ticket['id'] ?></td>
                                                        <td><?= htmlspecialchars($ticket['subject']) ?></td>
                                                        <td><?= htmlspecialchars($ticket['department']) ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= 
                                                                $ticket['priority'] === 'Low' ? 'success' : 
                                                                ($ticket['priority'] === 'Medium' ? 'warning' : 
                                                                ($ticket['priority'] === 'High' ? 'danger' : 'primary'))
                                                            ?>">
                                                                <?= htmlspecialchars($ticket['priority']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?= 
                                                                $ticket['status'] === 'Open' ? 'warning' : 
                                                                ($ticket['status'] === 'In Progress' ? 'info' : 
                                                                ($ticket['status'] === 'Resolved' ? 'success' : 'secondary'))
                                                            ?>">
                                                                <?= htmlspecialchars($ticket['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= date('M j, Y', strtotime($ticket['created_at'])) ?></td>
                                                        <td>
                                                            <a href="view_tickets.php?id=<?= $ticket['id'] ?>" class="btn btn-sm btn-primary">
                                                                <i class="mdi mdi-eye"></i> View
                                                            </a>
                                                            <?php if ($is_admin): ?>
                                                                <a href="view_tickets.php?id=<?= $ticket['id'] ?>" class="btn btn-sm btn-warning">
                                                                    <i class="mdi mdi-pencil"></i> Edit
                                                                </a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
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
            // Add data-table class for better styling
            $('table').addClass('table-hover');
            
            // Add confirmation for ticket deletion (if you implement delete functionality)
            $('.btn-delete').click(function(e) {
                if (!confirm('Are you sure you want to delete this ticket?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>