<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'user') {
    header('Location: user_login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $lab = trim($_POST['lab']);
    $pc_number = intval($_POST['pc_number']);
    $date = $_POST['date'];
    $time = $_POST['time'];
    $purpose = trim($_POST['purpose']);
    if ($purpose === 'Others' && isset($_POST['other_purpose'])) {
        $purpose = trim($_POST['other_purpose']);
    }

    if (empty($lab) || empty($pc_number) || empty($date) || empty($time) || empty($purpose)) {
        $_SESSION['error'] = 'All fields are required.';
        header('Location: user_dashboard.php');
        exit();
    }

    try {
        $stmt = $conn->prepare("INSERT INTO sit_in_reservations (user_id, lab, pc_number, date, time, purpose) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $lab, $pc_number, $date, $time, $purpose]);
        $_SESSION['success'] = 'Reservation request submitted successfully!';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to submit reservation: ' . $e->getMessage();
    }
    header('Location: user_dashboard.php');
    exit();
} else {
    header('Location: user_dashboard.php');
    exit();
} 