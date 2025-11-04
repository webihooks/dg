<?php
// guest-history.php
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
$user_name = $_SESSION['name'] ?? '';
$role = $_SESSION['role'] ?? 'user';

// Check if room tables exist
$check_table_sql = "SHOW TABLES LIKE 'guests_$user_id'";
$table_result = $conn->query($check_table_sql);
if ($table_result->num_rows == 0) {
    header("Location: create_room_tables.php");
    exit();
}

// Initialize variables
$search_term = '';
$date_filter = '';
$guest_stats = [];
$guest_history = [];
$guest_details = null;

// Handle search and filters
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $search_term = $_GET['search'] ?? '';
    $date_filter = $_GET['date_filter'] ?? '';
    $guest_id = $_GET['guest_id'] ?? '';
    
    // Get guest statistics
    $stats_sql = "SELECT 
                    COUNT(*) as total_guests,
                    COUNT(DISTINCT g.phone) as unique_guests,
                    AVG(b.total_amount) as avg_booking_value,
                    MAX(b.total_amount) as max_booking_value,
                    SUM(b.total_amount) as total_revenue
                  FROM guests_$user_id g
                  LEFT JOIN bookings_$user_id b ON g.phone = b.guest_phone";
    $stats_result = $conn->query($stats_sql);
    $guest_stats = $stats_result->fetch_assoc();
    
    // Get guest history with filters
    $history_sql = "SELECT 
                    g.id,
                    g.name,
                    g.phone,
                    g.email,
                    g.loyalty_points,
                    COUNT(b.id) as total_bookings,
                    SUM(b.total_amount) as total_spent,
                    MAX(b.check_in_date) as last_visit,
                    MIN(b.check_in_date) as first_visit
                  FROM guests_$user_id g
                  LEFT JOIN bookings_$user_id b ON g.phone = b.guest_phone
                  WHERE 1=1";
    
    $params = [];
    $types = '';
    
    if (!empty($search_term)) {
        $history_sql .= " AND (g.name LIKE ? OR g.phone LIKE ? OR g.email LIKE ?)";
        $search_param = "%$search_term%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
        $types .= 'sss';
    }
    
    if (!empty($date_filter)) {
        $history_sql .= " AND b.check_in_date >= ?";
        $params[] = $date_filter;
        $types .= 's';
    }
    
    $history_sql .= " GROUP BY g.id, g.name, g.phone, g.email, g.loyalty_points
                     ORDER BY total_spent DESC, total_bookings DESC";
    
    $stmt = $conn->prepare($history_sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $guest_history = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get specific guest details if requested
    if (!empty($guest_id)) {
        $guest_sql = "SELECT * FROM guests_$user_id WHERE id = ?";
        $stmt = $conn->prepare($guest_sql);
        $stmt->bind_param("i", $guest_id);
        $stmt->execute();
        $guest_details = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Get guest's booking history
        if ($guest_details) {
            $booking_sql = "SELECT 
                            b.*,
                            r.room_number,
                            rt.name as room_type
                          FROM bookings_$user_id b
                          LEFT JOIN rooms_$user_id r ON b.room_id = r.id
                          LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id
                          WHERE b.guest_phone = ?
                          ORDER BY b.check_in_date DESC";
            $stmt = $conn->prepare($booking_sql);
            $stmt->bind_param("s", $guest_details['phone']);
            $stmt->execute();
            $guest_bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Guest History - Room Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .guest-card {
            transition: all 0.3s ease;
            border-left: 4px solid #007bff;
        }
        .guest-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .vip-guest {
            border-left-color: #ffc107;
            background: linear-gradient(135deg, #fff3cd 0%, #ffffff 100%);
        }
        .frequent-guest {
            border-left-color: #28a745;
        }
        .new-guest {
            border-left-color: #6c757d;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
        }
        .loyalty-badge {
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            color: #000;
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
        }
        .booking-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-checked_in { background: #28a745; color: white; }
        .status-checked_out { background: #6c757d; color: white; }
        .status-reserved { background: #007bff; color: white; }
        .status-cancelled { background: #dc3545; color: white; }
        .guest-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 20px;
        }
        .filter-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
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
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="room-dashboard.php">Room Management</a></li>
                                    <li class="breadcrumb-item active">Guest History</li>
                                </ol>
                            </div>
                            <h4 class="page-title">Guest History & Analytics</h4>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card text-white">
                            <div class="card-body text-center">
                                <h3><?php echo $guest_stats['total_guests'] ?? 0; ?></h3>
                                <p class="mb-0">Total Guests</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card text-white">
                            <div class="card-body text-center">
                                <h3><?php echo $guest_stats['unique_guests'] ?? 0; ?></h3>
                                <p class="mb-0">Unique Guests</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card text-white">
                            <div class="card-body text-center">
                                <h3>₹<?php echo number_format($guest_stats['avg_booking_value'] ?? 0); ?></h3>
                                <p class="mb-0">Avg Booking Value</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card text-white">
                            <div class="card-body text-center">
                                <h3>₹<?php echo number_format($guest_stats['total_revenue'] ?? 0); ?></h3>
                                <p class="mb-0">Total Revenue</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="filter-section">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <label for="search" class="form-label">Search Guests</label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           value="<?php echo htmlspecialchars($search_term); ?>" 
                                           placeholder="Search by name, phone, or email...">
                                </div>
                                <div class="col-md-4">
                                    <label for="date_filter" class="form-label">Filter by Date</label>
                                    <input type="date" class="form-control" id="date_filter" name="date_filter" 
                                           value="<?php echo htmlspecialchars($date_filter); ?>">
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                                    <a href="guest-history.php" class="btn btn-secondary">Clear</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <?php if ($guest_details): ?>
                <!-- Guest Details Modal -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Guest Details - <?php echo htmlspecialchars($guest_details['name']); ?></h4>
                                <a href="guest-history.php" class="btn btn-sm btn-secondary">Back to List</a>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="guest-avatar mx-auto mb-3">
                                            <?php echo strtoupper(substr($guest_details['name'], 0, 2)); ?>
                                        </div>
                                        <h5 class="text-center"><?php echo htmlspecialchars($guest_details['name']); ?></h5>
                                        <div class="text-center mb-3">
                                            <span class="loyalty-badge">
                                                ★ <?php echo $guest_details['loyalty_points']; ?> Loyalty Points
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="row">
                                            <div class="col-6">
                                                <strong>Phone:</strong><br>
                                                <?php echo htmlspecialchars($guest_details['phone']); ?>
                                            </div>
                                            <div class="col-6">
                                                <strong>Email:</strong><br>
                                                <?php echo htmlspecialchars($guest_details['email'] ?? 'N/A'); ?>
                                            </div>
                                            <div class="col-12 mt-3">
                                                <strong>Address:</strong><br>
                                                <?php echo htmlspecialchars($guest_details['address'] ?? 'N/A'); ?>
                                            </div>
                                            <?php if ($guest_details['id_proof_type']): ?>
                                            <div class="col-12 mt-3">
                                                <strong>ID Proof:</strong><br>
                                                <?php echo htmlspecialchars($guest_details['id_proof_type']); ?>: 
                                                <?php echo htmlspecialchars($guest_details['id_proof_number']); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Guest Booking History -->
                                <div class="mt-4">
                                    <h5>Booking History</h5>
                                    <?php if (!empty($guest_bookings)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Booking Ref</th>
                                                        <th>Room</th>
                                                        <th>Check-in</th>
                                                        <th>Check-out</th>
                                                        <th>Amount</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($guest_bookings as $booking): ?>
                                                        <tr>
                                                            <td><?php echo $booking['booking_reference']; ?></td>
                                                            <td>
                                                                <?php echo $booking['room_number']; ?><br>
                                                                <small class="text-muted"><?php echo $booking['room_type']; ?></small>
                                                            </td>
                                                            <td><?php echo date('M j, Y', strtotime($booking['check_in_date'])); ?></td>
                                                            <td><?php echo date('M j, Y', strtotime($booking['check_out_date'])); ?></td>
                                                            <td>₹<?php echo number_format($booking['total_amount']); ?></td>
                                                            <td>
                                                                <span class="booking-status status-<?php echo $booking['status']; ?>">
                                                                    <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted">No booking history found for this guest.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Guest List -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Guest History</h4>
                                <p class="text-muted mb-0">Showing <?php echo count($guest_history); ?> guests</p>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($guest_history)): ?>
                                    <div class="row">
                                        <?php foreach ($guest_history as $guest): ?>
                                            <?php
                                            $guest_class = 'guest-card';
                                            $bookings_count = $guest['total_bookings'] ?? 0;
                                            $total_spent = $guest['total_spent'] ?? 0;
                                            
                                            if ($bookings_count >= 5) {
                                                $guest_class .= ' vip-guest';
                                            } elseif ($bookings_count >= 2) {
                                                $guest_class .= ' frequent-guest';
                                            } else {
                                                $guest_class .= ' new-guest';
                                            }
                                            ?>
                                            <div class="col-md-6 col-lg-4 mb-3">
                                                <div class="card <?php echo $guest_class; ?>">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($guest['name']); ?></h5>
                                                            <span class="loyalty-badge">
                                                                ★ <?php echo $guest['loyalty_points']; ?>
                                                            </span>
                                                        </div>
                                                        
                                                        <p class="card-text mb-1">
                                                            <small class="text-muted">📞 <?php echo htmlspecialchars($guest['phone']); ?></small>
                                                        </p>
                                                        <?php if ($guest['email']): ?>
                                                        <p class="card-text mb-1">
                                                            <small class="text-muted">✉️ <?php echo htmlspecialchars($guest['email']); ?></small>
                                                        </p>
                                                        <?php endif; ?>
                                                        
                                                        <div class="row mt-3">
                                                            <div class="col-6">
                                                                <small class="text-muted">Bookings</small><br>
                                                                <strong><?php echo $bookings_count; ?></strong>
                                                            </div>
                                                            <div class="col-6">
                                                                <small class="text-muted">Total Spent</small><br>
                                                                <strong>₹<?php echo number_format($total_spent); ?></strong>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="row mt-2">
                                                            <div class="col-6">
                                                                <small class="text-muted">First Visit</small><br>
                                                                <small><?php echo $guest['first_visit'] ? date('M Y', strtotime($guest['first_visit'])) : 'N/A'; ?></small>
                                                            </div>
                                                            <div class="col-6">
                                                                <small class="text-muted">Last Visit</small><br>
                                                                <small><?php echo $guest['last_visit'] ? date('M Y', strtotime($guest['last_visit'])) : 'N/A'; ?></small>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mt-3">
                                                            <a href="guest-history.php?guest_id=<?php echo $guest['id']; ?>" 
                                                               class="btn btn-sm btn-outline-primary">View Details</a>
                                                            <?php if ($guest['phone']): ?>
                                                            <a href="https://wa.me/91<?php echo $guest['phone']; ?>?text=Hello%20<?php echo urlencode($guest['name']); ?>%2C%20thank%20you%20for%20staying%20with%20us%21" 
                                                               target="_blank" 
                                                               class="btn btn-sm btn-outline-success">WhatsApp</a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <div class="mb-3">
                                            <iconify-icon icon="mdi:account-group" style="font-size: 64px; color: #6c757d;"></iconify-icon>
                                        </div>
                                        <h5>No Guests Found</h5>
                                        <p class="text-muted">No guest history matches your search criteria.</p>
                                        <a href="guest-history.php" class="btn btn-primary">Clear Filters</a>
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
        // Initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
        
        // Auto-submit form when date filter changes
        $('#date_filter').change(function() {
            if ($(this).val()) {
                $(this).closest('form').submit();
            }
        });
        
        // Enhanced search with debounce
        let searchTimeout;
        $('#search').on('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if ($(this).val().length >= 3 || $(this).val().length === 0) {
                    $(this).closest('form').submit();
                }
            }, 500);
        });
        
        // Print guest history
        $('.print-guest-history').click(function() {
            window.print();
        });
    });
    </script>
</body>
</html>