<?php
// send_booking_reminder.php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$booking_id = $_POST['booking_id'] ?? null;
$guest_phone = $_POST['guest_phone'] ?? '';
$guest_name = $_POST['guest_name'] ?? '';

if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Missing booking ID']);
    exit();
}

try {
    // Get booking details
    $table_name = "bookings_$user_id";
    $sql = "SELECT b.*, r.room_number, rt.name as room_type 
            FROM $table_name b
            LEFT JOIN rooms_$user_id r ON b.room_id = r.id
            LEFT JOIN room_types_$user_id rt ON r.room_type_id = rt.id
            WHERE b.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$booking) {
        throw new Exception('Booking not found');
    }
    
    // Create reminder message
    $checkin_date = date('M j, Y', strtotime($booking['check_in_date']));
    $message = "Dear {$guest_name},\n\n";
    $message .= "Reminder: Your booking #{$booking['booking_reference']} is confirmed.\n";
    $message .= "Check-in: {$checkin_date}\n";
    $message .= "Room: {$booking['room_number']} ({$booking['room_type']})\n";
    $message .= "Amount: ₹{$booking['total_amount']}\n\n";
    $message .= "We look forward to welcoming you!\n\n";
    $message .= "Thank you for choosing us!";
    
    // For now, just log the message (you can integrate with WhatsApp API later)
    error_log("Reminder for booking #{$booking['booking_reference']}: " . $message);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Reminder prepared successfully',
        'whatsapp_message' => $message
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>