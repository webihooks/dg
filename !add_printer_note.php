<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'printer') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_printer_note'])) {
    $assignment_id = $_POST['assignment_id'];
    $printer_id = $_POST['printer_id'];
    $note_content = trim($_POST['note_content']);
    
    if (!empty($note_content)) {
        $sql = "INSERT INTO printer_notes (assignment_id, printer_id, note) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $assignment_id, $printer_id, $note_content);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Note added successfully!";
        } else {
            $_SESSION['error_message'] = "Error adding note: " . $conn->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Note content cannot be empty!";
    }
    
    header("Location: printer-dashboard.php");
    exit();
}
?>