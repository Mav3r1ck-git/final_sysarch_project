<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['lab_schedule'])) {
    $target_dir = 'uploads/lab_schedules/';
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    $original_name = basename($_FILES['lab_schedule']['name']);
    $filename = uniqid('labsched_') . '_' . $original_name;
    $target_file = $target_dir . $filename;

    if (move_uploaded_file($_FILES['lab_schedule']['tmp_name'], $target_file)) {
        $stmt = $conn->prepare("INSERT INTO lab_schedules (filename, original_name) VALUES (?, ?)");
        $stmt->execute([$filename, $original_name]);
        $_SESSION['success'] = "Lab schedule uploaded successfully.";
    } else {
        $_SESSION['error'] = "Failed to upload lab schedule.";
    }
    header("Location: admin_dashboard.php");
    exit();
} else {
    header("Location: admin_dashboard.php");
    exit();
} 