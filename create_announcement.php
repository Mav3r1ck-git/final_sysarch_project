<?php
session_start();
require_once 'database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);

    if (empty($title) || empty($content)) {
        $_SESSION['error'] = "Title and content are required.";
        header('Location: admin_dashboard.php');
        exit();
    }

    try {
        $stmt = $conn->prepare("INSERT INTO announcements (title, content, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        $stmt->execute([$title, $content]);
        
        $_SESSION['success'] = "Announcement created successfully.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error creating announcement: " . $e->getMessage();
    }
}

header('Location: admin_dashboard.php');
exit(); 