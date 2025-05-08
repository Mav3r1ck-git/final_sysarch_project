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
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);

    if (empty($title) || empty($content)) {
        $_SESSION['error'] = "Title and content are required.";
        header('Location: admin_dashboard.php');
        exit();
    }

    try {
        $stmt = $conn->prepare("UPDATE announcements SET title = ?, content = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$title, $content, $announcement_id]);
        
        $_SESSION['success'] = "Announcement updated successfully.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating announcement: " . $e->getMessage();
    }
}

header('Location: admin_dashboard.php');
exit(); 