<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: admin_login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservation_id'], $_POST['action'])) {
    $reservation_id = intval($_POST['reservation_id']);
    $action = $_POST['action'];

    // Fetch reservation details
    $stmt = $conn->prepare("SELECT * FROM sit_in_reservations WHERE id = ?");
    $stmt->execute([$reservation_id]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        $_SESSION['error'] = 'Reservation not found.';
        header('Location: admin_dashboard.php');
        exit();
    }

    $user_id = $reservation['user_id'];
    $lab = $reservation['lab'];
    $pc_number = $reservation['pc_number'];
    $purpose = $reservation['purpose'];
    $date = $reservation['date'];
    $time = $reservation['time'];

    if ($action === 'approve') {
        try {
            // Start transaction
            $conn->beginTransaction();
            // Insert into current_sitins
            $stmt = $conn->prepare("INSERT INTO current_sitins (user_id, lab, pc_number, purpose, sitin_time) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $lab, $pc_number, $purpose, $date . ' ' . $time]);
            // Update reservation status
            $stmt = $conn->prepare("UPDATE sit_in_reservations SET status = 'approved' WHERE id = ?");
            $stmt->execute([$reservation_id]);
            // Add notification
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $stmt->execute([$user_id, "Your sit-in reservation for $lab PC $pc_number on $date at $time has been approved."]);
            $conn->commit();
            $_SESSION['success'] = 'Reservation approved and student notified.';
        } catch (PDOException $e) {
            $conn->rollBack();
            $_SESSION['error'] = 'Failed to approve reservation: ' . $e->getMessage();
        }
    } elseif ($action === 'reject') {
        try {
            $stmt = $conn->prepare("UPDATE sit_in_reservations SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$reservation_id]);
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $stmt->execute([$user_id, "Your sit-in reservation for $lab PC $pc_number on $date at $time has been rejected."]);
            $_SESSION['success'] = 'Reservation rejected and student notified.';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to reject reservation: ' . $e->getMessage();
        }
    }
    header('Location: admin_dashboard.php');
    exit();
} else {
    header('Location: admin_dashboard.php');
    exit();
} 