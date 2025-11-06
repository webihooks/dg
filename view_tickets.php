<?php
session_start();
require 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if ticket ID is provided
if (!isset($_GET['id'])) {
    header("Location: tickets.php");
    exit();
}

$ticket_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Check if user is admin or ticket owner
$is_admin = false;
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

// Fetch ticket details
$ticket_sql = "SELECT t.*, u.name as user_name, u.email as user_email 
               FROM tickets t
               JOIN users u ON t.user_id = u.id
               WHERE t.id = ?" . ($is_admin ? "" : " AND t.user_id = ?");

$ticket_stmt = $conn->prepare($ticket_sql);
if ($is_admin) {
    $ticket_stmt->bind_param("i", $ticket_id);
} else {
    $ticket_stmt->bind_param("ii", $ticket_id, $user_id);
}
$ticket_stmt->execute();
$ticket_result = $ticket_stmt->get_result();

if ($ticket_result->num_rows === 0) {
    $_SESSION['error'] = "Ticket not found or you don't have permission to view it";
    header("Location: tickets.php");
    exit();
}

$ticket = $ticket_result->fetch_assoc();
$ticket_stmt->close();

// Fetch ticket attachments
$attachments_sql = "SELECT * FROM ticket_attachments WHERE ticket_id = ?";
$attachments_stmt = $conn->prepare($attachments_sql);
$attachments_stmt->bind_param("i", $ticket_id);
$attachments_stmt->execute();
$attachments = $attachments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$attachments_stmt->close();

// Fetch user name for display
$user_name = '';
$name_sql = "SELECT name FROM users WHERE id = ?";
$name_stmt = $conn->prepare($name_sql);
$name_stmt->bind_param("i", $user_id);
$name_stmt->execute();
$name_stmt->bind_result($user_name);
$name_stmt->fetch();
$name_stmt->close();

// Handle status update if admin
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = in_array($_POST['status'], ['Open', 'In Progress', 'Resolved', 'Closed']) ? $_POST['status'] : 'Open';
    
    $update_sql = "UPDATE tickets SET status = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_status, $ticket_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success'] = "Ticket status updated successfully";
        $ticket['status'] = $new_status;
    } else {
        $_SESSION['error'] = "Error updating ticket status";
    }
    $update_stmt->close();
    
    header("Location: view_tickets.php?id=" . $ticket_id);
    exit();
}

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $reply_message = trim($_POST['reply_message']);
    
    if (!empty($reply_message)) {
        $reply_sql = "INSERT INTO ticket_replies (ticket_id, user_id, message, created_at) 
                      VALUES (?, ?, ?, NOW())";
        $reply_stmt = $conn->prepare($reply_sql);
        $reply_stmt->bind_param("iis", $ticket_id, $user_id, $reply_message);
        
        if ($reply_stmt->execute()) {
            $_SESSION['success'] = "Reply added successfully";
            
            // If user is not admin, mark ticket as "Customer Reply"
            if (!$is_admin) {
                $update_sql = "UPDATE tickets SET status = 'Customer Reply' WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("i", $ticket_id);
                $update_stmt->execute();
                $update_stmt->close();
            }
        } else {
            $_SESSION['error'] = "Error adding reply";
        }
        $reply_stmt->close();
        
        header("Location: view_tickets.php?id=" . $ticket_id);
        exit();
    }
}

// Fetch ticket replies
$replies_sql = "SELECT tr.*, u.name as user_name, u.role as user_role 
                FROM ticket_replies tr
                JOIN users u ON tr.user_id = u.id
                WHERE tr.ticket_id = ?
                ORDER BY tr.created_at ASC";
$replies_stmt = $conn->prepare($replies_sql);
$replies_stmt->bind_param("i", $ticket_id);
$replies_stmt->execute();
$replies = $replies_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$replies_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>View Ticket #<?= $ticket_id ?> | Support System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <link href="https://cdn.materialdesignicons.com/5.4.55/css/materialdesignicons.min.css" rel="stylesheet">
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        @media (max-width:768px){.container{padding:0 10px}.card-header{padding:15px}.card-header .d-flex{flex-direction:column;align-items:flex-start!important;gap:10px}.card-header .badge{align-self:flex-start}.card-body{padding:15px}.ticket-info-grid{display:flex;flex-direction:column;gap:15px}.info-item{display:flex;justify-content:space-between;align-items:flex-start;padding:10px 0;border-bottom:1px solid #f0f0f0}.info-item:last-child{border-bottom:none}.info-label{font-weight:600;color:#495057;min-width:120px}.info-value{text-align:right;flex:1}.ticket-message{padding:15px;margin:15px 0;background:#f8f9fa;border-radius:8px;line-height:1.5}.attachments-grid{display:grid;grid-template-columns:1fr;gap:10px;margin:15px 0}.attachment-card{display:flex;align-items:center;padding:12px;border:1px solid #e0e0e0;border-radius:8px;background:#fff}.attachment-icon{font-size:24px;margin-right:12px;color:#6c757d}.attachment-info{flex:1}.attachment-name{font-weight:500;margin-bottom:2px}.attachment-size{font-size:.8rem;color:#6c757d}.status-update-form .row{flex-direction:column;gap:10px}.status-update-form .col-md-8,.status-update-form .col-md-4{width:100%}.reply-form .btn{width:100%;margin-bottom:10px}.reply-form .btn-secondary{margin-top:0}.timeline-mobile{margin:20px 0}.reply-card{border:1px solid #e0e0e0;border-radius:8px;padding:15px;margin-bottom:15px;background:#fff}.reply-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;padding-bottom:10px;border-bottom:1px solid #f0f0f0}.reply-user{display:flex;align-items:center;gap:8px}.user-badge{font-size:.7rem;padding:3px 8px}.reply-time{font-size:.8rem;color:#6c757d}.reply-content{line-height:1.5;color:#333}.admin-reply{border-left:4px solid #0d6efd;background:#f8fbff}.user-reply{border-left:4px solid #198754;background:#f8fff9}.desktop-timeline{display:none}.mobile-timeline{display:block}}@media (min-width:769px){.desktop-timeline{display:block}.mobile-timeline{display:none}}@media (max-width:576px){.card-header h4{font-size:1.25rem}.info-item{flex-direction:column;align-items:flex-start;gap:5px}.info-value{text-align:left;width:100%}.reply-header{flex-direction:column;align-items:flex-start;gap:5px}.reply-time{align-self:flex-start}.attachment-card{flex-direction:column;align-items:flex-start;text-align:left}.attachment-icon{margin-bottom:8px}.attachment-actions{align-self:flex-end;margin-top:8px}}.timeline{position:relative;padding-left:1rem}.timeline-item{position:relative;padding-bottom:1.5rem}.timeline-item-marker{position:absolute;left:-1rem;width:2rem;text-align:center}.timeline-item-marker-indicator{display:inline-block;width:12px;height:12px;border-radius:100%}.timeline-item-content{padding:.5rem 1rem;border-radius:.375rem;background-color:#f8f9fa}.admin-reply-desktop .timeline-item-content{background-color:#e7f5ff;border-left:3px solid #0d6efd;width:100%}.user-reply-desktop .timeline-item-content{background-color:#ebfbee;border-left:3px solid #198754}.attachment-item{max-width:300px}.timeline-item::before,.ticket-replies .timeline::before{width:0;display:none}.scroll-to-top.show{bottom:15px}
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
                                    <h4 class="card-title">Ticket #<?= $ticket_id ?></h4>
                                    <span class="badge bg-<?= 
                                        $ticket['status'] === 'Open' ? 'warning' : 
                                        ($ticket['status'] === 'In Progress' ? 'info' : 
                                        ($ticket['status'] === 'Resolved' ? 'success' : 
                                        ($ticket['status'] === 'Closed' ? 'secondary' : 'primary')))
                                    ?>">
                                        <?= htmlspecialchars($ticket['status']) ?>
                                    </span>
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

                                <div class="mb-4">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <h5>Subject: <?= htmlspecialchars($ticket['subject']) ?></h5>
                                        </div>
                                        <div class="col-md-6 text-md-end">
                                            <small class="text-muted">Created: <?= date('M j, Y g:i A', strtotime($ticket['created_at'])) ?></small>
                                        </div>
                                    </div>

                                    <!-- Desktop Layout -->
                                    <div class="d-none d-md-block">
                                        <div class="row mb-3">
                                            <div class="col-md-4">
                                                <p><strong>Department:</strong> <?= htmlspecialchars($ticket['department']) ?></p>
                                            </div>
                                            <div class="col-md-4">
                                                <p><strong>Priority:</strong> <?= htmlspecialchars($ticket['priority']) ?></p>
                                            </div>
                                            <div class="col-md-4">
                                                <p><strong>Submitted By:</strong> <?= htmlspecialchars($ticket['user_name']) ?></p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Mobile Layout -->
                                    <div class="d-md-none ticket-info-grid">
                                        <div class="info-item">
                                            <span class="info-label">Department:</span>
                                            <span class="info-value"><?= htmlspecialchars($ticket['department']) ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Priority:</span>
                                            <span class="info-value">
                                                <span class="badge bg-<?= 
                                                    $ticket['priority'] === 'High' ? 'danger' : 
                                                    ($ticket['priority'] === 'Medium' ? 'warning' : 'info')
                                                ?>">
                                                    <?= htmlspecialchars($ticket['priority']) ?>
                                                </span>
                                            </span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Submitted By:</span>
                                            <span class="info-value"><?= htmlspecialchars($ticket['user_name']) ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Created:</span>
                                            <span class="info-value"><?= date('M j, Y g:i A', strtotime($ticket['created_at'])) ?></span>
                                        </div>
                                    </div>

                                    <div class="ticket-message">
                                        <h6>Message:</h6>
                                        <p><?= nl2br(htmlspecialchars($ticket['message'])) ?></p>
                                    </div>

                                    <?php if (!empty($attachments)): ?>
                                        <div class="attachments mb-4">
                                            <h6>Attachments:</h6>
                                            <!-- Desktop Layout -->
                                            <div class="d-none d-md-flex flex-wrap gap-2">
                                                <?php foreach ($attachments as $attachment): ?>
                                                    <div class="attachment-item border p-2 rounded">
                                                        <?php
                                                        $file_icon = '';
                                                        $file_type = strtolower(pathinfo($attachment['file_name'], PATHINFO_EXTENSION));
                                                        
                                                        switch ($file_type) {
                                                            case 'jpg':
                                                            case 'jpeg':
                                                            case 'png':
                                                            case 'gif':
                                                                $file_icon = 'mdi-image';
                                                                break;
                                                            case 'pdf':
                                                                $file_icon = 'mdi-file-pdf';
                                                                break;
                                                            case 'doc':
                                                            case 'docx':
                                                                $file_icon = 'mdi-file-word';
                                                                break;
                                                            case 'xls':
                                                            case 'xlsx':
                                                            case 'csv':
                                                                $file_icon = 'mdi-file-excel';
                                                                break;
                                                            default:
                                                                $file_icon = 'mdi-file';
                                                        }
                                                        ?>
                                                        <div class="d-flex align-items-center">
                                                            <i class="mdi <?= $file_icon ?> me-2" style="font-size: 24px;"></i>
                                                            <div>
                                                                <div><?= htmlspecialchars($attachment['file_name']) ?></div>
                                                                <small class="text-muted"><?= round($attachment['file_size'] / 1024, 1) ?> KB</small>
                                                            </div>
                                                            <a href="<?= htmlspecialchars($attachment['file_path']) ?>" 
                                                               class="ms-2 btn btn-sm btn-outline-primary" 
                                                               target="_blank" download>
                                                                <i class="mdi mdi-download"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            
                                            <!-- Mobile Layout -->
                                            <div class="d-md-none attachments-grid">
                                                <?php foreach ($attachments as $attachment): ?>
                                                    <div class="attachment-card">
                                                        <?php
                                                        $file_icon = '';
                                                        $file_type = strtolower(pathinfo($attachment['file_name'], PATHINFO_EXTENSION));
                                                        
                                                        switch ($file_type) {
                                                            case 'jpg':
                                                            case 'jpeg':
                                                            case 'png':
                                                            case 'gif':
                                                                $file_icon = 'mdi-image';
                                                                break;
                                                            case 'pdf':
                                                                $file_icon = 'mdi-file-pdf';
                                                                break;
                                                            case 'doc':
                                                            case 'docx':
                                                                $file_icon = 'mdi-file-word';
                                                                break;
                                                            case 'xls':
                                                            case 'xlsx':
                                                            case 'csv':
                                                                $file_icon = 'mdi-file-excel';
                                                                break;
                                                            default:
                                                                $file_icon = 'mdi-file';
                                                        }
                                                        ?>
                                                        <i class="mdi <?= $file_icon ?> attachment-icon"></i>
                                                        <div class="attachment-info">
                                                            <div class="attachment-name"><?= htmlspecialchars($attachment['file_name']) ?></div>
                                                            <div class="attachment-size"><?= round($attachment['file_size'] / 1024, 1) ?> KB</div>
                                                        </div>
                                                        <div class="attachment-actions">
                                                            <a href="<?= htmlspecialchars($attachment['file_path']) ?>" 
                                                               class="btn btn-sm btn-outline-primary" 
                                                               target="_blank" download>
                                                                <i class="mdi mdi-download"></i> Download
                                                            </a>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($is_admin): ?>
                                    <div class="status-update mb-4">
                                        <form method="POST" action="view_tickets.php?id=<?= $ticket_id ?>">
                                            <div class="row">
                                                <div class="col-md-8">
                                                    <select class="form-select" name="status">
                                                        <option value="Open" <?= $ticket['status'] === 'Open' ? 'selected' : '' ?>>Open</option>
                                                        <option value="In Progress" <?= $ticket['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                                        <option value="Resolved" <?= $ticket['status'] === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                                                        <option value="Closed" <?= $ticket['status'] === 'Closed' ? 'selected' : '' ?>>Closed</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <button type="submit" name="update_status" class="btn btn-primary w-100">
                                                        Update Status
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                <?php endif; ?>

                                <div class="ticket-replies mb-4">
                                    <h5 class="mb-3">Conversation</h5>
                                    
                                    <?php if (empty($replies)): ?>
                                        <div class="alert alert-info">No replies yet.</div>
                                    <?php else: ?>
                                        <!-- Desktop Timeline -->
                                        <div class="desktop-timeline">
                                            <div class="timeline">
                                                <?php foreach ($replies as $reply): ?>
                                                    <div class="timeline-item <?= $reply['user_role'] === 'admin' ? 'admin-reply-desktop' : 'user-reply-desktop' ?>">
                                                        <div class="timeline-item-marker">
                                                            <div class="timeline-item-marker-indicator bg-<?= $reply['user_role'] === 'admin' ? 'primary' : 'success' ?>"></div>
                                                        </div>
                                                        <div class="timeline-item-content">
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span class="fw-bold"><?= htmlspecialchars($reply['user_name']) ?> 
                                                                    <span class="badge bg-<?= $reply['user_role'] === 'admin' ? 'primary' : 'success' ?>">
                                                                        <?= ucfirst($reply['user_role']) ?>
                                                                    </span>
                                                                </span>
                                                                <small class="text-muted"><?= date('M j, Y g:i A', strtotime($reply['created_at'])) ?></small>
                                                            </div>
                                                            <p><?= nl2br(htmlspecialchars($reply['message'])) ?></p>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Mobile Timeline -->
                                        <div class="mobile-timeline">
                                            <?php foreach ($replies as $reply): ?>
                                                <div class="reply-card <?= $reply['user_role'] === 'admin' ? 'admin-reply' : 'user-reply' ?>">
                                                    <div class="reply-header">
                                                        <div class="reply-user">
                                                            <span class="fw-bold"><?= htmlspecialchars($reply['user_name']) ?></span>
                                                            <span class="badge user-badge bg-<?= $reply['user_role'] === 'admin' ? 'primary' : 'success' ?>">
                                                                <?= ucfirst($reply['user_role']) ?>
                                                            </span>
                                                        </div>
                                                        <small class="reply-time"><?= date('M j, Y g:i A', strtotime($reply['created_at'])) ?></small>
                                                    </div>
                                                    <div class="reply-content">
                                                        <?= nl2br(htmlspecialchars($reply['message'])) ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="reply-form">
                                    <form method="POST" action="view_tickets.php?id=<?= $ticket_id ?>">
                                        <div class="mb-3">
                                            <label for="reply_message" class="form-label">Add Reply</label>
                                            <textarea class="form-control" id="reply_message" name="reply_message" rows="4" required placeholder="Type your reply here..."></textarea>
                                        </div>
                                        <div class="d-grid gap-2 d-md-flex">
                                            <button type="submit" class="btn btn-primary">Submit Reply</button>
                                            <a href="tickets.php" class="btn btn-secondary">Back to Tickets</a>
                                        </div>
                                    </form>
                                </div>
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
            
            // Auto-focus reply textarea
            $('#reply_message').focus();
            
            // Add animation to reply cards on mobile
            $('.reply-card').each(function(index) {
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
        });
    </script>
</body>
</html>