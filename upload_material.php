<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['material'])) {
    $target_dir = 'uploads/materials/';
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    $original_name = basename($_FILES['material']['name']);
    $filename = uniqid('material_') . '_' . $original_name;
    $target_file = $target_dir . $filename;

    if (move_uploaded_file($_FILES['material']['tmp_name'], $target_file)) {
        $stmt = $conn->prepare("INSERT INTO materials (filename, original_name) VALUES (?, ?)");
        $stmt->execute([$filename, $original_name]);
        $_SESSION['success'] = "Material uploaded successfully.";
    } else {
        $_SESSION['error'] = "Failed to upload material.";
    }
    header("Location: admin_dashboard.php");
    exit();
} else {
    header("Location: admin_dashboard.php");
    exit();
} 