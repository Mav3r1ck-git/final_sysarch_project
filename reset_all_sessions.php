<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $conn->prepare("UPDATE users SET remaining_sessions = 30");
        $stmt->execute();
        $_SESSION['success'] = "All users' usable sit-in sessions have been reset to 30.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to reset all sessions: " . $e->getMessage();
    }
    header("Location: admin_dashboard.php");
    exit();
} else {
    header("Location: admin_dashboard.php");
    exit();
} 