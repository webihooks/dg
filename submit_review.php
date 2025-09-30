<?php
// submit_review.php
require_once 'config/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    try {
        $user_id = $_POST['user_id'];
        $order_id = $_POST['order_id'];
        $reviewer_name = $_POST['reviewer_name'];
        $reviewer_email = $_POST['reviewer_email'] ?? null;
        $reviewer_phone = $_POST['reviewer_phone'];
        $rating = $_POST['rating'];
        $feedback = $_POST['feedback'];
        
        // Get profile_url from the form or session
        $profile_url = $_POST['profile_url'] ?? $_GET['profile_url'] ?? '';
        
        // Insert review into ratings table
        $sql = "INSERT INTO ratings (user_id, reviewer_name, reviewer_email, reviewer_phone, rating, feedback, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id, $reviewer_name, $reviewer_email, $reviewer_phone, $rating, $feedback]);
        
        // Redirect back to order status page with success message
        $redirect_url = "order_status.php?order_id=" . urlencode($order_id) . "&profile_url=" . urlencode($profile_url) . "&review_submitted=1";
        header("Location: " . $redirect_url);
        exit();
        
    } catch (PDOException $e) {
        error_log("Error submitting review: " . $e->getMessage());
        $profile_url = $_POST['profile_url'] ?? $_GET['profile_url'] ?? '';
        $redirect_url = "order_status.php?order_id=" . urlencode($order_id) . "&profile_url=" . urlencode($profile_url) . "&error=1";
        header("Location: " . $redirect_url);
        exit();
    }
} else {
    // If not a POST request, redirect to page not found
    header("Location: page-not-found.php");
    exit();
}
?>