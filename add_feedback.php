<?php
session_start();
require_once 'database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'user') {
    header("Location: user_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $session_id = $_POST['session_id'];
    $feedback = trim($_POST['feedback']);
    $user_id = $_SESSION['user_id'];

    if (empty($feedback)) {
        $_SESSION['error'] = "Feedback cannot be empty.";
        header("Location: user_dashboard.php");
        exit();
    }

    // Check if the session belongs to the user
    $stmt = $conn->prepare("SELECT * FROM logged_out_sitins WHERE id = ? AND user_id = ?");
    $stmt->execute([$session_id, $user_id]);
    $session = $stmt->fetch();

    if (!$session) {
        $_SESSION['error'] = "Invalid session or permission denied.";
        header("Location: user_dashboard.php");
        exit();
    }

    // Update feedback
    $stmt = $conn->prepare("UPDATE logged_out_sitins SET feedback = ? WHERE id = ?");
    if ($stmt->execute([$feedback, $session_id])) {
        $_SESSION['success'] = "Feedback submitted successfully.";
    } else {
        $_SESSION['error'] = "Failed to submit feedback.";
    }
    header("Location: user_dashboard.php");
    exit();
} else {
    header("Location: user_dashboard.php");
    exit();
} 