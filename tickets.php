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
    <style>
        @media (max-width:768px){.container{padding:0 10px}.card-header{padding:15px}.card-header .d-flex{flex-direction:column;align-items:flex-start!important;gap:15px}.card-header h4{font-size:1.25rem;margin:0}.card-body{padding:15px}.table-responsive table{min-width:800px}.ticket-card{border:1px solid #e0e0e0;border-radius:8px;padding:15px;margin-bottom:15px;background:#fff;box-shadow:0 2px 4px rgba(0,0,0,0.1)}.ticket-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #f0f0f0}.ticket-id{font-weight:600;color:#333;font-size:1.1rem}.ticket-status{font-size:.8rem;padding:4px 8px}.ticket-subject{font-weight:600;font-size:1rem;color:#333;margin-bottom:10px;line-height:1.3}.ticket-details{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:15px}.detail-item{display:flex;flex-direction:column}.detail-label{font-size:.8rem;color:#666;margin-bottom:2px}.detail-value{font-weight:500;color:#333}.priority-badge,.department-badge{font-size:.75rem;padding:3px 8px}.ticket-actions{display:flex;gap:8px;justify-content:flex-end}.ticket-actions .btn{font-size:.8rem;padding:6px 12px}.empty-state{text-align:center;padding:40px 20px;color:#666}.empty-state .mdi{font-size:3rem;margin-bottom:15px;color:#ccc}.empty-state h5{color:#666;margin-bottom:10px}.empty-state p{color:#888;margin-bottom:20px}.desktop-table{display:none}.mobile-cards{display:block}}@media (min-width:769px){.desktop-table{display:block}.mobile-cards{display:none}}@media (max-width:576px){.ticket-details{grid-template-columns:1fr;gap:8px}.ticket-header{flex-direction:column;align-items:flex-start;gap:8px}.ticket-status{align-self:flex-start}.ticket-actions{justify-content:stretch}.ticket-actions .btn{flex:1;text-align:center}.card-header .btn{width:100%}}@media (max-width:400px){.ticket-card{padding:12px}.ticket-actions{flex-direction:column;gap:5px}}.badge{display:inline-block;padding:.25rem .75rem;font-size:.75rem;font-weight:600;text-align:center;border-radius:.375rem;text-transform:uppercase;letter-spacing:.025em}.badge-priority-urgent{background:orange;color:#fff}.badge-priority-low{background:#28a745;color:#fff}.badge-priority-medium{background:#ffc107;color:#000}.badge-priority-high{background:#dc3545;color:#fff}.badge-status-open{background:#ffc107;color:#000}.badge-status-in-progress{background:#17a2b8;color:#fff}.badge-status-resolved{background:#28a745;color:#fff}.badge-status-closed{background:#6c757d;color:#fff}.table-hover tbody tr:hover{background:rgba(0,0,0,.02)}.scroll-to-top.show{bottom:15px}
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php
        if ($role === 'admin') {
            include 'admin_menu.php';
        } elseif ($role === 'sales_person') {
            include 'sales_menu.php';
        } elseif ($role === 'room') {
            include 'room_management_menu.php';
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
                                    <div class="empty-state">
                                        <i class="mdi mdi-ticket-outline"></i>
                                        <h5>No Tickets Found</h5>
                                        <p>You haven't created any support tickets yet.</p>
                                        <a href="create_ticket.php" class="btn btn-primary">
                                            <i class="mdi mdi-plus"></i> Create Your First Ticket
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <!-- Desktop Table View -->
                                    <div class="desktop-table">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover">
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
                                                                <span class="badge badge-priority-<?= strtolower($ticket['priority']) ?>">
                                                                    <?= htmlspecialchars($ticket['priority']) ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="badge badge-status-<?= strtolower(str_replace(' ', '-', $ticket['status'])) ?>">
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
                                    </div>
                                    
                                    <!-- Mobile Card View -->
                                    <div class="mobile-cards">
                                        <?php foreach ($tickets as $ticket): ?>
                                            <div class="ticket-card">
                                                <div class="ticket-header">
                                                    <div class="ticket-id">Ticket #<?= $ticket['id'] ?></div>
                                                    <span class="badge ticket-status badge-status-<?= strtolower(str_replace(' ', '-', $ticket['status'])) ?>">
                                                        <?= htmlspecialchars($ticket['status']) ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="ticket-subject">
                                                    <?= htmlspecialchars($ticket['subject']) ?>
                                                </div>
                                                
                                                <?php if ($is_admin): ?>
                                                    <div class="detail-item">
                                                        <span class="detail-label">User</span>
                                                        <span class="detail-value"><?= htmlspecialchars($ticket['user_name']) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="ticket-details">
                                                    <div class="detail-item">
                                                        <span class="detail-label">Department</span>
                                                        <span class="detail-value"><?= htmlspecialchars($ticket['department']) ?></span>
                                                    </div>
                                                    
                                                    <div class="detail-item">
                                                        <span class="detail-label">Priority</span>
                                                        <span class="badge priority-badge badge-priority-<?= strtolower($ticket['priority']) ?>">
                                                            <?= htmlspecialchars($ticket['priority']) ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <div class="detail-item">
                                                        <span class="detail-label">Created</span>
                                                        <span class="detail-value"><?= date('M j, Y', strtotime($ticket['created_at'])) ?></span>
                                                    </div>
                                                    
                                                    <?php if ($ticket['updated_at'] && $ticket['updated_at'] !== $ticket['created_at']): ?>
                                                        <div class="detail-item">
                                                            <span class="detail-label">Last Updated</span>
                                                            <span class="detail-value"><?= date('M j, Y', strtotime($ticket['updated_at'])) ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="ticket-actions">
                                                    <a href="view_tickets.php?id=<?= $ticket['id'] ?>" class="btn btn-sm btn-primary">
                                                        <i class="mdi mdi-eye"></i> View
                                                    </a>
                                                    <?php if ($is_admin): ?>
                                                        <a href="view_tickets.php?id=<?= $ticket['id'] ?>" class="btn btn-sm btn-warning">
                                                            <i class="mdi mdi-pencil"></i> Edit
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
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

    <!-- Scroll to Top Button -->
    <button class="scroll-to-top" id="scrollToTop">
        <i class="mdi mdi-chevron-up"></i>
    </button>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
        $(document).ready(function() {
            // Scroll to top functionality
            const scrollToTopBtn = document.getElementById('scrollToTop');
            
            window.addEventListener('scroll', function() {
                if (window.pageYOffset > 300) {
                    scrollToTopBtn.classList.add('show');
                } else {
                    scrollToTopBtn.classList.remove('show');
                }
            });
            
            scrollToTopBtn.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
            
            // Add animation to ticket cards
            $('.ticket-card').each(function(index) {
                $(this).css({
                    'opacity': '0',
                    'transform': 'translateY(20px)'
                });
                
                setTimeout(() => {
                    $(this).animate({
                        'opacity': '1',
                        'transform': 'translateY(0)'
                    }, 300);
                }, index * 100);
            });
            
            // Add confirmation for ticket deletion (if you implement delete functionality)
            $('.btn-delete').click(function(e) {
                if (!confirm('Are you sure you want to delete this ticket?')) {
                    e.preventDefault();
                }
            });
            
            // Filter functionality for mobile (optional enhancement)
            $('.filter-btn').click(function() {
                const filter = $(this).data('filter');
                $('.ticket-card').show();
                if (filter !== 'all') {
                    $('.ticket-card').not('.ticket-status-' + filter).hide();
                }
            });
        });
    </script>
</body>
</html>