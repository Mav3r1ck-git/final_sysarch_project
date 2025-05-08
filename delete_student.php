<?php
session_start();
require_once 'database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];

    try {
        // Start transaction
        $conn->beginTransaction();

        // Delete current sit-in sessions
        $stmt = $conn->prepare("DELETE FROM current_sitins WHERE user_id = ?");
        $stmt->execute([$user_id]);

        // Delete logged out sit-in sessions
        $stmt = $conn->prepare("DELETE FROM logged_out_sitins WHERE user_id = ?");
        $stmt->execute([$user_id]);

        // Delete user
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);

        // Commit transaction
        $conn->commit();

        $_SESSION['success'] = "Student deleted successfully.";
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $_SESSION['error'] = "Error deleting student: " . $e->getMessage();
    }

    header("Location: admin_dashboard.php");
    exit();
} else {
    header("Location: admin_dashboard.php");
    exit();
} 