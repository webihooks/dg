<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'printer') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_printer_note'])) {
    $note_id = $_POST['note_id'];
    $assignment_id = $_POST['assignment_id'];
    
    // Verify that the note belongs to the current printer
    $verify_sql = "SELECT pn.id FROM printer_notes pn 
                   WHERE pn.id = ? AND pn.printer_id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $note_id, $_SESSION['user_id']);
    $verify_stmt->execute();
    $verify_stmt->store_result();
    
    if ($verify_stmt->num_rows > 0) {
        $delete_sql = "DELETE FROM printer_notes WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $note_id);
        
        if ($delete_stmt->execute()) {
            $_SESSION['success_message'] = "Note deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Error deleting note: " . $conn->error;
        }
        $delete_stmt->close();
    } else {
        $_SESSION['error_message'] = "You can only delete your own notes!";
    }
    
    $verify_stmt->close();
    header("Location: printer-dashboard.php");
    exit();
}
?>