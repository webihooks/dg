<?php
// guest-notifications.php
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
$success_message = '';
$error_message = '';

// Check if room tables exist
$check_table_sql = "SHOW TABLES LIKE 'rooms_$user_id'";
$table_result = $conn->query($check_table_sql);
if ($table_result->num_rows == 0) {
    header("Location: create_room_tables.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_notification'])) {
        $notification_type = $_POST['notification_type'];
        $guest_id = $_POST['guest_id'];
        $message = $_POST['message'];
        $schedule_time = $_POST['schedule_time'];
        
        // Insert notification into user-specific table
        $insert_sql = "INSERT INTO guest_notifications_$user_id 
                      (guest_id, notification_type, message, schedule_time, status, created_at) 
                      VALUES (?, ?, ?, ?, 'scheduled', NOW())";
        
        $stmt = $conn->prepare($insert_sql);
        $schedule_time = $schedule_time ? $schedule_time : null;
        $stmt->bind_param("isss", $guest_id, $notification_type, $message, $schedule_time);
        
        if ($stmt->execute()) {
            $success_message = "Notification scheduled successfully!";
            
            // Send immediately if no schedule time
            if (!$schedule_time) {
                sendImmediateNotification($user_id, $guest_id, $notification_type, $message, $conn);
            }
        } else {
            $error_message = "Error scheduling notification: " . $stmt->error;
        }
        $stmt->close();
    }
    
    if (isset($_POST['send_bulk_notification'])) {
        $bulk_type = $_POST['bulk_type'];
        $bulk_message = $_POST['bulk_message'];
        
        $guest_ids = [];
        
        // Get guest IDs based on bulk type
        switch ($bulk_type) {
            case 'all_guests':
                $guest_sql = "SELECT id FROM guests_$user_id WHERE phone IS NOT NULL";
                break;
            case 'current_guests':
                $guest_sql = "SELECT DISTINCT g.id 
                             FROM guests_$user_id g 
                             INNER JOIN bookings_$user_id b ON g.id = b.guest_id 
                             WHERE b.status IN ('checked_in', 'reserved') 
                             AND g.phone IS NOT NULL";
                break;
            case 'past_guests':
                $guest_sql = "SELECT DISTINCT g.id 
                             FROM guests_$user_id g 
                             INNER JOIN bookings_$user_id b ON g.id = b.guest_id 
                             WHERE b.status = 'checked_out' 
                             AND g.phone IS NOT NULL";
                break;
            case 'loyalty_guests':
                $guest_sql = "SELECT id FROM guests_$user_id WHERE loyalty_points > 0 AND phone IS NOT NULL";
                break;
        }
        
        $result = $conn->query($guest_sql);
        while ($row = $result->fetch_assoc()) {
            $guest_ids[] = $row['id'];
        }
        
        if (!empty($guest_ids)) {
            $success_count = 0;
            foreach ($guest_ids as $guest_id) {
                $insert_sql = "INSERT INTO guest_notifications_$user_id 
                              (guest_id, notification_type, message, status, created_at) 
                              VALUES (?, 'bulk', ?, 'sent', NOW())";
                
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("is", $guest_id, $bulk_message);
                
                if ($stmt->execute()) {
                    sendImmediateNotification($user_id, $guest_id, 'bulk', $bulk_message, $conn);
                    $success_count++;
                }
                $stmt->close();
            }
            $success_message = "Bulk notification sent to $success_count guests!";
        } else {
            $error_message = "No guests found for the selected criteria.";
        }
    }
}

// Function to send immediate notification
function sendImmediateNotification($user_id, $guest_id, $type, $message, $conn) {
    // Get guest phone number
    $guest_sql = "SELECT phone, name FROM guests_$user_id WHERE id = ?";
    $stmt = $conn->prepare($guest_sql);
    $stmt->bind_param("i", $guest_id);
    $stmt->execute();
    $guest_result = $stmt->get_result();
    
    if ($guest_result->num_rows > 0) {
        $guest = $guest_result->fetch_assoc();
        $phone = $guest['phone'];
        $name = $guest['name'];
        
        // Send WhatsApp message
        sendWhatsAppNotification($phone, $name, $message, $user_id);
    }
    $stmt->close();
}

// Function to send WhatsApp notification
function sendWhatsAppNotification($phone, $name, $message, $user_id) {
    // Get business info
    $business_sql = "SELECT business_name, business_phone FROM business WHERE user_id = ?";
    $stmt = $GLOBALS['conn']->prepare($business_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $business_result = $stmt->get_result();
    
    $business_name = "Our Hotel";
    $business_phone = "";
    
    if ($business_result->num_rows > 0) {
        $business = $business_result->fetch_assoc();
        $business_name = $business['business_name'] ?? "Our Hotel";
        $business_phone = $business['business_phone'] ?? "";
    }
    $stmt->close();
    
    // Format phone number
    $formatted_phone = preg_replace('/\D/', '', $phone);
    if (strlen($formatted_phone) === 10) {
        $formatted_phone = '91' . $formatted_phone;
    }
    
    // Create personalized message
    $personalized_message = "👋 Dear $name,\n\n";
    $personalized_message .= "$message\n\n";
    $personalized_message .= "Best regards,\n";
    $personalized_message .= "$business_name";
    
    if ($business_phone) {
        $personalized_message .= "\n📞 $business_phone";
    }
    
    // Create WhatsApp URL
    $whatsapp_url = "https://wa.me/$formatted_phone?text=" . urlencode($personalized_message);
    
    // Return URL for JavaScript handling
    return $whatsapp_url;
}

// Get guests for dropdown
$guests_sql = "SELECT id, name, phone FROM guests_$user_id WHERE phone IS NOT NULL ORDER BY name";
$guests_result = $conn->query($guests_sql);

// Get notification history
$notifications_sql = "SELECT gn.*, g.name as guest_name, g.phone as guest_phone 
                     FROM guest_notifications_$user_id gn 
                     LEFT JOIN guests_$user_id g ON gn.guest_id = g.id 
                     ORDER BY gn.created_at DESC 
                     LIMIT 50";
$notifications_result = $conn->query($notifications_sql);

// Get stats
$stats_sql = "SELECT 
    COUNT(*) as total_notifications,
    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_notifications,
    SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_notifications,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_notifications
    FROM guest_notifications_$user_id";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Guest Notifications - Room Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/jquery.validate.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- WebToNative Script -->
    <script src="https://unpkg.com/webtonative@1.0.77/webtonative.min.js"></script>
    <style>
        .notification-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .notification-sent { border-left-color: #28a745; }
        .notification-scheduled { border-left-color: #ffc107; }
        .notification-failed { border-left-color: #dc3545; }
        .stats-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .template-card { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 10px; padding: 15px; margin-bottom: 15px; cursor: pointer; }
        .template-card:hover { background: #e9ecef; }
        .bulk-options { display: none; }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'room_management_menu.php'; ?>

        <div class="page-content">
            <div class="container-fluid">
                <!-- Notifications -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Guest Notifications Management</h4>
                                <p class="text-muted mb-0">Send personalized messages to your guests via WhatsApp</p>
                            </div>
                            <div class="card-body">
                                <!-- Statistics Cards -->
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="card stats-card">
                                            <div class="card-body text-center">
                                                <h3><?php echo $stats['total_notifications'] ?? 0; ?></h3>
                                                <p class="mb-0">Total Notifications</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card stats-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                                            <div class="card-body text-center">
                                                <h3><?php echo $stats['sent_notifications'] ?? 0; ?></h3>
                                                <p class="mb-0">Sent</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card stats-card" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                                            <div class="card-body text-center">
                                                <h3><?php echo $stats['scheduled_notifications'] ?? 0; ?></h3>
                                                <p class="mb-0">Scheduled</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card stats-card" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);">
                                            <div class="card-body text-center">
                                                <h3><?php echo $stats['failed_notifications'] ?? 0; ?></h3>
                                                <p class="mb-0">Failed</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Individual Notification Form -->
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="card-title">Send Individual Notification</h5>
                                            </div>
                                            <div class="card-body">
                                                <form method="POST" id="individualForm">
                                                    <div class="mb-3">
                                                        <label class="form-label">Select Guest</label>
                                                        <select class="form-select" name="guest_id" required>
                                                            <option value="">Choose a guest...</option>
                                                            <?php while ($guest = $guests_result->fetch_assoc()): ?>
                                                                <option value="<?php echo $guest['id']; ?>">
                                                                    <?php echo htmlspecialchars($guest['name']); ?> (<?php echo $guest['phone']; ?>)
                                                                </option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Notification Type</label>
                                                        <select class="form-select" name="notification_type" required>
                                                            <option value="welcome">Welcome Message</option>
                                                            <option value="checkin_reminder">Check-in Reminder</option>
                                                            <option value="checkout_reminder">Check-out Reminder</option>
                                                            <option value="special_offer">Special Offer</option>
                                                            <option value="feedback_request">Feedback Request</option>
                                                            <option value="custom">Custom Message</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Message</label>
                                                        <textarea class="form-control" name="message" rows="4" placeholder="Enter your message here..." required></textarea>
                                                        <small class="text-muted">Max 500 characters</small>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Schedule Time (Optional)</label>
                                                        <input type="datetime-local" class="form-control" name="schedule_time">
                                                        <small class="text-muted">Leave empty to send immediately</small>
                                                    </div>
                                                    
                                                    <button type="submit" name="send_notification" class="btn btn-success">
                                                        <i class="fas fa-paper-plane"></i> Send Notification
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Bulk Notification Form -->
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="card-title">Bulk Notifications</h5>
                                            </div>
                                            <div class="card-body">
                                                <form method="POST" id="bulkForm">
                                                    <div class="mb-3">
                                                        <label class="form-label">Send To</label>
                                                        <select class="form-select" name="bulk_type" id="bulkType" required>
                                                            <option value="">Select group...</option>
                                                            <option value="all_guests">All Guests</option>
                                                            <option value="current_guests">Current Guests (Checked-in/Reserved)</option>
                                                            <option value="past_guests">Past Guests</option>
                                                            <option value="loyalty_guests">Loyalty Program Guests</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Message</label>
                                                        <textarea class="form-control" name="bulk_message" rows="4" placeholder="Enter your bulk message here..." required></textarea>
                                                        <small class="text-muted">This message will be sent to all selected guests</small>
                                                    </div>
                                                    
                                                    <div class="alert alert-warning">
                                                        <i class="fas fa-exclamation-triangle"></i>
                                                        <strong>Warning:</strong> Bulk messages will be sent immediately to all selected guests.
                                                    </div>
                                                    
                                                    <button type="submit" name="send_bulk_notification" class="btn btn-warning" onclick="return confirm('Are you sure you want to send this message to all selected guests?')">
                                                        <i class="fas fa-bullhorn"></i> Send Bulk Notification
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Message Templates -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="card-title">Quick Message Templates</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <div class="template-card" onclick="useTemplate('welcome')">
                                                            <h6>👋 Welcome Message</h6>
                                                            <p class="text-muted mb-0">Welcome to our hotel! We're excited to have you stay with us.</p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="template-card" onclick="useTemplate('checkin_reminder')">
                                                            <h6>🕐 Check-in Reminder</h6>
                                                            <p class="text-muted mb-0">Reminder: Your check-in time is approaching. We look forward to welcoming you!</p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="template-card" onclick="useTemplate('checkout_reminder')">
                                                            <h6>🏃 Check-out Reminder</h6>
                                                            <p class="text-muted mb-0">Friendly reminder: Your check-out time is at 12 PM tomorrow.</p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="template-card" onclick="useTemplate('special_offer')">
                                                            <h6>🎉 Special Offer</h6>
                                                            <p class="text-muted mb-0">Exclusive offer! Get 20% off on your next booking with us.</p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="template-card" onclick="useTemplate('feedback_request')">
                                                            <h6>⭐ Feedback Request</h6>
                                                            <p class="text-muted mb-0">How was your stay? We'd love to hear your feedback to improve our services.</p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="template-card" onclick="useTemplate('loyalty_reward')">
                                                            <h6>🏆 Loyalty Reward</h6>
                                                            <p class="text-muted mb-0">Congratulations! You've earned loyalty points with your recent stay.</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Notification History -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="card-title">Notification History</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-striped">
                                                        <thead>
                                                            <tr>
                                                                <th>Guest</th>
                                                                <th>Type</th>
                                                                <th>Message</th>
                                                                <th>Status</th>
                                                                <th>Created</th>
                                                                <th>Scheduled</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if ($notifications_result->num_rows > 0): ?>
                                                                <?php while ($notification = $notifications_result->fetch_assoc()): ?>
                                                                    <tr>
                                                                        <td>
                                                                            <strong><?php echo htmlspecialchars($notification['guest_name']); ?></strong><br>
                                                                            <small class="text-muted"><?php echo $notification['guest_phone']; ?></small>
                                                                        </td>
                                                                        <td>
                                                                            <span class="badge bg-primary">
                                                                                <?php echo ucfirst(str_replace('_', ' ', $notification['notification_type'])); ?>
                                                                            </span>
                                                                        </td>
                                                                        <td>
                                                                            <small><?php echo htmlspecialchars(substr($notification['message'], 0, 50)); ?>...</small>
                                                                        </td>
                                                                        <td>
                                                                            <?php 
                                                                            $status_class = '';
                                                                            switch ($notification['status']) {
                                                                                case 'sent': $status_class = 'bg-success'; break;
                                                                                case 'scheduled': $status_class = 'bg-warning'; break;
                                                                                case 'failed': $status_class = 'bg-danger'; break;
                                                                                default: $status_class = 'bg-secondary';
                                                                            }
                                                                            ?>
                                                                            <span class="badge <?php echo $status_class; ?>">
                                                                                <?php echo ucfirst($notification['status']); ?>
                                                                            </span>
                                                                        </td>
                                                                        <td>
                                                                            <small><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></small>
                                                                        </td>
                                                                        <td>
                                                                            <small>
                                                                                <?php 
                                                                                if ($notification['schedule_time']) {
                                                                                    echo date('M j, Y g:i A', strtotime($notification['schedule_time']));
                                                                                } else {
                                                                                    echo '<span class="text-muted">Immediate</span>';
                                                                                }
                                                                                ?>
                                                                            </small>
                                                                        </td>
                                                                    </tr>
                                                                <?php endwhile; ?>
                                                            <?php else: ?>
                                                                <tr>
                                                                    <td colspan="6" class="text-center text-muted py-4">
                                                                        <i class="fas fa-bell-slash fa-2x mb-2"></i><br>
                                                                        No notifications sent yet
                                                                    </td>
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
                </div>
            </div>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>

    <script>
    // Template messages
    const templates = {
        'welcome': "Welcome to our hotel! We're excited to have you stay with us. Your room is ready and our team is here to make your stay comfortable. If you need anything, don't hesitate to ask!",
        'checkin_reminder': "Reminder: Your check-in time is approaching. We look forward to welcoming you! Please have your ID proof ready for verification.",
        'checkout_reminder': "Friendly reminder: Your check-out time is at 12 PM tomorrow. Please ensure all your belongings are packed. We hope you enjoyed your stay!",
        'special_offer': "Exclusive offer for our valued guests! Get 20% off on your next booking with us. Use code: STAY20. Valid for 30 days.",
        'feedback_request': "How was your stay with us? We'd love to hear your feedback to improve our services. Your opinion matters to us!",
        'loyalty_reward': "Congratulations! You've earned loyalty points with your recent stay. Keep staying with us to unlock exclusive benefits and rewards."
    };

    function useTemplate(templateKey) {
        document.querySelector('textarea[name="message"]').value = templates[templateKey];
        document.querySelector('select[name="notification_type"]').value = templateKey;
        
        // Scroll to individual form
        document.getElementById('individualForm').scrollIntoView({ behavior: 'smooth' });
        
        // Show success message
        showToast('Template applied successfully!', 'success');
    }

    function showToast(message, type) {
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show`;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
        `;
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(toast);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 3000);
    }

    // Character counter for message textareas
    document.addEventListener('DOMContentLoaded', function() {
        const messageTextareas = document.querySelectorAll('textarea[name="message"], textarea[name="bulk_message"]');
        
        messageTextareas.forEach(textarea => {
            const counter = document.createElement('small');
            counter.className = 'text-muted character-counter';
            counter.style.float = 'right';
            textarea.parentNode.appendChild(counter);
            
            function updateCounter() {
                const remaining = 500 - textarea.value.length;
                counter.textContent = `${remaining} characters remaining`;
                counter.className = `text-muted character-counter ${remaining < 50 ? 'text-danger' : ''}`;
            }
            
            textarea.addEventListener('input', updateCounter);
            updateCounter();
        });
    });
    </script>
</body>
</html>