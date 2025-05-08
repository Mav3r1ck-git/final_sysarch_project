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
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    $year_level = $_POST['year_level'];
    $course = $_POST['course'];

    // Validate input
    if (empty($first_name) || empty($last_name) || empty($year_level) || empty($course)) {
        $_SESSION['error'] = "All required fields must be filled.";
        header("Location: admin_dashboard.php");
        exit();
    }

    try {
        $stmt = $conn->prepare("
            UPDATE users 
            SET first_name = ?, 
                middle_name = ?, 
                last_name = ?, 
                year_level = ?, 
                course = ? 
            WHERE user_id = ?
        ");
        $stmt->execute([$first_name, $middle_name, $last_name, $year_level, $course, $user_id]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Student information updated successfully.";
        } else {
            $_SESSION['error'] = "No changes were made.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating student information: " . $e->getMessage();
    }

    header("Location: admin_dashboard.php");
    exit();
} else {
    header("Location: admin_dashboard.php");
    exit();
} 