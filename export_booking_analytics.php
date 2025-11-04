<?php
// export_booking_analytics.php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    die("Access denied");
}

$user_id = $_SESSION['user_id'];
$year = $_GET['year'] ?? date('Y');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=booking_analytics_' . $year . '.csv');

$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, [
    'Month', 'Bookings', 'Revenue', 'Average Booking Value', 
    'Total Nights', 'Unique Guests', 'Cancellations'
]);

// Get monthly data
$monthly_sql = "SELECT 
    DATE_FORMAT(check_in_date, '%Y-%m') as month,
    COUNT(*) as booking_count,
    SUM(total_amount) as monthly_revenue,
    AVG(total_amount) as avg_booking_value,
    SUM(total_nights) as total_nights,
    COUNT(DISTINCT guest_phone) as unique_guests,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancellations
FROM bookings_$user_id 
WHERE YEAR(check_in_date) = ?
GROUP BY DATE_FORMAT(check_in_date, '%Y-%m')
ORDER BY month ASC";

$stmt = $conn->prepare($monthly_sql);
$stmt->bind_param("s", $year);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        date('F Y', strtotime($row['month'] . '-01')),
        $row['booking_count'],
        $row['monthly_revenue'],
        $row['avg_booking_value'],
        $row['total_nights'],
        $row['unique_guests'],
        $row['cancellations']
    ]);
}

$stmt->close();
$conn->close();
fclose($output);
exit;