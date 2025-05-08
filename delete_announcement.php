<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $announcement_id = $_POST['announcement_id'];

    try {
        $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt->execute([$announcement_id]);
        
        $_SESSION['success'] = "Announcement deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting announcement: " . $e->getMessage();
    }
}

header('Location: admin_dashboard.php');
exit(); 