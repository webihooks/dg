<?php
// bookings.php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();
date_default_timezone_set('Asia/Kolkata');

// Include the enhanced session manager
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

// Get user details
$user_sql = "SELECT name, role, is_trial, trial_end FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->bind_result($user_name, $role, $is_trial, $trial_end);
$user_stmt->fetch();
$user_stmt->close();

// Check if room tables exist, if not redirect to table creation
$check_table_sql = "SHOW TABLES LIKE 'bookings_$user_id'";
$table_result = $conn->query($check_table_sql);
if ($table_result->num_rows == 0) {
    header("Location: create_room_tables.php");
    exit();
}

// Handle booking actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $booking_id = $_POST['booking_id'] ?? null;
        
        switch ($action) {
            case 'checkin':
                if ($booking_id) {
                    $update_sql = "UPDATE bookings_$user_id SET status = 'checked_in', updated_at = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($update_sql);
                    $stmt->bind_param("i", $booking_id);
                    if ($stmt->execute()) {
                        $success_message = "Booking checked in successfully!";
                    } else {
                        $error_message = "Error checking in booking: " . $conn->error;
                    }
                    $stmt->close();
                    
                    // Update room status
                    $room_sql = "UPDATE rooms_$user_id r 
                                JOIN bookings_$user_id b ON r.id = b.room_id 
                                SET r.status = 'occupied' 
                                WHERE b.id = ?";
                    $room_stmt = $conn->prepare($room_sql);
                    $room_stmt->bind_param("i", $booking_id);
                    $room_stmt->execute();
                    $room_stmt->close();
                }
                break;
                
            case 'checkout':
                if ($booking_id) {
                    $update_sql = "UPDATE bookings_$user_id SET status = 'checked_out', updated_at = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($update_sql);
                    $stmt->bind_param("i", $booking_id);
                    if ($stmt->execute()) {
                        $success_message = "Booking checked out successfully!";
                    } else {
                        $error_message = "Error checking out booking: " . $conn->error;
                    }
                    $stmt->close();
                    
                    // Update room status
                    $room_sql = "UPDATE rooms_$user_id r 
                                JOIN bookings_$user_id b ON r.id = b.room_id 
                                SET r.status = 'cleaning' 
                                WHERE b.id = ?";
                    $room_stmt = $conn->prepare($room_sql);
                    $room_stmt->bind_param("i", $booking_id);
                    $room_stmt->execute();
                    $room_stmt->close();
                }
                break;
                
            case 'cancel':
                if ($booking_id) {
                    $reason = $_POST['cancel_reason'] ?? 'No reason provided';
                    $update_sql = "UPDATE bookings_$user_id SET status = 'cancelled', cancellation_reason = ?, updated_at = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($update_sql);
                    $stmt->bind_param("si", $reason, $booking_id);
                    if ($stmt->execute()) {
                        $success_message = "Booking cancelled successfully!";
                    } else {
                        $error_message = "Error cancelling booking: " . $conn->error;
                    }
                    $stmt->close();
                    
                    // Update room status if it was reserved
                    $room_sql = "UPDATE rooms_$user_id r 
                                JOIN bookings_$user_id b ON r.id = b.room_id 
                                SET r.status = 'available' 
                                WHERE b.id = ? AND b.status = 'reserved'";
                    $room_stmt = $conn->prepare($room_sql);
                    $room_stmt->bind_param("i", $booking_id);
                    $room_stmt->execute();
                    $room_stmt->close();
                }
                break;
        }
    }
}

// Handle filters
$filter_status = $_GET['status'] ?? '';
$filter_date = $_GET['date'] ?? '';
$filter_search = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];
$types = '';

if ($filter_status && $filter_status !== 'all') {
    $where_conditions[] = "b.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if ($filter_date) {
    $where_conditions[] = "DATE(b.check_in_date) = ?";
    $params[] = $filter_date;
    $types .= 's';
}

if ($filter_search) {
    $where_conditions[] = "(b.guest_name LIKE ? OR b.guest_phone LIKE ? OR b.booking_reference LIKE ? OR r.room_number LIKE ?)";
    $search_term = "%$filter_search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ssss';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Get bookings with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Count total records
$count_sql = "SELECT COUNT(*) as total 
              FROM bookings_$user_id b
              LEFT JOIN rooms_$user_id r ON b.room_id = r.id
              LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id
              $where_clause";
              
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_records / $limit);

// Get bookings data
$bookings_sql = "SELECT 
                    b.*,
                    r.room_number,
                    r.floor,
                    rt.name as room_type,
                    rt.base_rate as room_type_rate
                FROM bookings_$user_id b
                LEFT JOIN rooms_$user_id r ON b.room_id = r.id
                LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id
                $where_clause
                ORDER BY b.created_at DESC
                LIMIT ? OFFSET ?";
                
$bookings_stmt = $conn->prepare($bookings_sql);
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

if (!empty($params)) {
    $bookings_stmt->bind_param($types, ...$params);
}
$bookings_stmt->execute();
$bookings_result = $bookings_stmt->get_result();
$bookings = $bookings_result->fetch_all(MYSQLI_ASSOC);
$bookings_stmt->close();

// Get booking statistics
$stats_sql = "SELECT 
                status,
                COUNT(*) as count,
                SUM(total_amount) as total_revenue
              FROM bookings_$user_id 
              GROUP BY status";
$stats_result = $conn->query($stats_sql);
$booking_stats = [];
while ($row = $stats_result->fetch_assoc()) {
    $booking_stats[$row['status']] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Bookings Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- WebToNative Script -->
    <script src="https://unpkg.com/webtonative@1.0.77/webtonative.min.js"></script>
    
    <style>
        .booking-card {
            transition: all 0.3s ease;
            border-left: 4px solid #007bff;
        }
        
        .booking-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .booking-status-reserved { border-left-color: #007bff; }
        .booking-status-checked_in { border-left-color: #28a745; }
        .booking-status-checked_out { border-left-color: #6c757d; }
        .booking-status-cancelled { border-left-color: #dc3545; }
        .booking-status-no_show { border-left-color: #fd7e14; }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge-reserved { background-color: #007bff; color: white; }
        .badge-checked_in { background-color: #28a745; color: white; }
        .badge-checked_out { background-color: #6c757d; color: white; }
        .badge-cancelled { background-color: #dc3545; color: white; }
        .badge-no_show { background-color: #fd7e14; color: white; }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .action-buttons .btn {
            margin: 2px;
            font-size: 12px;
        }
        
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .action-buttons .btn {
                display: block;
                width: 100%;
                margin-bottom: 5px;
            }
            
            .booking-card .row > div {
                margin-bottom: 10px;
            }
        }
        
        .pagination {
            justify-content: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'room_management_menu.php'; ?>

        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-xl-12">
                        <!-- Page Header -->
                        <div class="page-title-box">
                            <h4 class="page-title">Bookings Management</h4>
                            <div class="page-title-right">
                                <a href="add-booking.php" class="btn btn-success">
                                    <i class="fas fa-plus-circle"></i> New Booking
                                </a>
                            </div>
                        </div>

                        <!-- Success/Error Messages -->
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Booking Statistics -->
                        <div class="row mb-4">
                            <div class="col-md-2">
                                <div class="stats-card text-center">
                                    <h3><?php echo $total_records; ?></h3>
                                    <p>Total Bookings</p>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card text-center" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                                    <h3><?php echo $booking_stats['reserved']['count'] ?? 0; ?></h3>
                                    <p>Reserved</p>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card text-center" style="background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);">
                                    <h3><?php echo $booking_stats['checked_in']['count'] ?? 0; ?></h3>
                                    <p>Checked In</p>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card text-center" style="background: linear-gradient(135deg, #6c757d 0%, #495057 100%);">
                                    <h3><?php echo $booking_stats['checked_out']['count'] ?? 0; ?></h3>
                                    <p>Checked Out</p>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card text-center" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);">
                                    <h3><?php echo $booking_stats['cancelled']['count'] ?? 0; ?></h3>
                                    <p>Cancelled</p>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card text-center" style="background: linear-gradient(135deg, #fd7e14 0%, #e55a08 100%);">
                                    <h3><?php echo $booking_stats['no_show']['count'] ?? 0; ?></h3>
                                    <p>No Show</p>
                                </div>
                            </div>
                        </div>

                        <!-- Filters Section -->
                        <div class="filter-section">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="all">All Status</option>
                                        <option value="reserved" <?php echo $filter_status === 'reserved' ? 'selected' : ''; ?>>Reserved</option>
                                        <option value="checked_in" <?php echo $filter_status === 'checked_in' ? 'selected' : ''; ?>>Checked In</option>
                                        <option value="checked_out" <?php echo $filter_status === 'checked_out' ? 'selected' : ''; ?>>Checked Out</option>
                                        <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        <option value="no_show" <?php echo $filter_status === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Date</label>
                                    <input type="date" name="date" class="form-control" value="<?php echo $filter_date; ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Search</label>
                                    <input type="text" name="search" class="form-control" placeholder="Search by guest name, phone, reference..." value="<?php echo htmlspecialchars($filter_search); ?>">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                                </div>
                            </form>
                        </div>

                        <!-- Bookings List -->
                        <div class="card">
                            <div class="card-body">
                                <?php if (empty($bookings)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                        <h5>No bookings found</h5>
                                        <p class="text-muted"><?php echo $total_records === 0 ? 'No bookings have been created yet.' : 'No bookings match your filters.'; ?></p>
                                        <?php if ($total_records === 0): ?>
                                            <a href="add-booking.php" class="btn btn-primary">Create First Booking</a>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Booking Ref</th>
                                                    <th>Guest</th>
                                                    <th>Room</th>
                                                    <th>Check-in/out</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($bookings as $booking): ?>
                                                    <tr class="booking-card booking-status-<?php echo $booking['status']; ?>">
                                                        <td>
                                                            <strong>#<?php echo htmlspecialchars($booking['booking_reference']); ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?php echo date('M j, Y', strtotime($booking['created_at'])); ?></small>
                                                        </td>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($booking['guest_name']); ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($booking['guest_phone']); ?></small>
                                                            <?php if ($booking['guest_email']): ?>
                                                                <br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($booking['guest_email']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($booking['room_number']); ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($booking['room_type']); ?></small>
                                                            <?php if ($booking['floor']): ?>
                                                                <br>
                                                                <small class="text-muted">Floor: <?php echo htmlspecialchars($booking['floor']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <strong>In: <?php echo date('M j, Y', strtotime($booking['check_in_date'])); ?></strong>
                                                            <br>
                                                            <strong>Out: <?php echo date('M j, Y', strtotime($booking['check_out_date'])); ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?php echo $booking['total_nights']; ?> night(s)</small>
                                                        </td>
                                                        <td>
                                                            <strong>₹<?php echo number_format($booking['total_amount'], 2); ?></strong>
                                                            <br>
                                                            <small class="text-muted">
                                                                <?php 
                                                                $payment_status = $booking['payment_status'];
                                                                $payment_badge_class = $payment_status === 'paid' ? 'badge bg-success' : 
                                                                                      ($payment_status === 'partial' ? 'badge bg-warning' : 
                                                                                      ($payment_status === 'refunded' ? 'badge bg-info' : 'badge bg-secondary'));
                                                                ?>
                                                                <span class="<?php echo $payment_badge_class; ?>"><?php echo ucfirst($payment_status); ?></span>
                                                            </small>
                                                            <?php if ($booking['advance_paid'] > 0): ?>
                                                                <br>
                                                                <small class="text-muted">Advance: ₹<?php echo number_format($booking['advance_paid'], 2); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="status-badge badge-<?php echo $booking['status']; ?>">
                                                                <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="action-buttons">
                                                                <?php if ($booking['status'] === 'reserved'): ?>
                                                                    <form method="POST" style="display: inline;">
                                                                        <input type="hidden" name="action" value="checkin">
                                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                                        <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Check in this booking?')">Check In</button>
                                                                    </form>
                                                                    <button type="button" class="btn btn-danger btn-sm" onclick="showCancelModal(<?php echo $booking['id']; ?>)">Cancel</button>
                                                                <?php elseif ($booking['status'] === 'checked_in'): ?>
                                                                    <form method="POST" style="display: inline;">
                                                                        <input type="hidden" name="action" value="checkout">
                                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                                        <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Check out this booking?')">Check Out</button>
                                                                    </form>
                                                                <?php endif; ?>
                                                                <a href="booking-details.php?id=<?php echo $booking['id']; ?>" class="btn btn-info btn-sm">View</a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Pagination -->
                                    <?php if ($total_pages > 1): ?>
                                        <nav>
                                            <ul class="pagination">
                                                <?php if ($page > 1): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                                                    </li>
                                                <?php endif; ?>
                                                
                                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                                    </li>
                                                <?php endfor; ?>
                                                
                                                <?php if ($page < $total_pages): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </nav>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Cancel Booking Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="cancelForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="booking_id" id="cancel_booking_id">
                        
                        <div class="mb-3">
                            <label for="cancel_reason" class="form-label">Cancellation Reason</label>
                            <textarea class="form-control" id="cancel_reason" name="cancel_reason" rows="3" placeholder="Please provide a reason for cancellation..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">Confirm Cancellation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
    function showCancelModal(bookingId) {
        document.getElementById('cancel_booking_id').value = bookingId;
        var modal = new bootstrap.Modal(document.getElementById('cancelModal'));
        modal.show();
    }
    
    // Enhanced session protection for bookings page
    function setupBookingsSessionProtection() {
        if (typeof WTN === 'undefined') return;
        
        console.log('🏨 Bookings: Setting up Android session protection');
        
        // Force immediate cookie update
        setTimeout(() => {
            if (WTN.forceUpdateCookies) {
                WTN.forceUpdateCookies();
            }
        }, 1000);
        
        // Update cookies on page interactions
        document.addEventListener('click', function() {
            setTimeout(() => {
                if (WTN.forceUpdateCookies) {
                    WTN.forceUpdateCookies();
                }
            }, 2000);
        });
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        setupBookingsSessionProtection();
        
        // Auto-refresh bookings every 30 seconds
        setInterval(() => {
            if (!document.hidden) {
                window.location.reload();
            }
        }, 30000);
    });
    </script>
</body>
</html>