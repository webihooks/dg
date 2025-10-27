<?php
// Start the session
session_start();
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $role = htmlspecialchars($_GET['role']);
    $_SESSION['user_id'] = $_GET['user_id'];
    $_SESSION['role'] = $_GET['role'];
    $url = $_GET['page'];
    //header("Location: /".$url."");
    echo "<script>window.location.href = '/$url';</script>";
exit;

}
?>