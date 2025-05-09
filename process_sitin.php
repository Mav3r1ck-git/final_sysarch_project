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
    $lab = $_POST['lab'];
    $pc_number = $_POST['pc_number'];
    $purpose = $_POST['purpose'];
    
    // If purpose is "Others", use the other_purpose value
    if ($purpose === 'Others' && isset($_POST['other_purpose'])) {
        $purpose = $_POST['other_purpose'];
    }

    try {
        // Check if user has remaining sessions
        $stmt = $conn->prepare("SELECT remaining_sessions FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user || $user['remaining_sessions'] <= 0) {
            $_SESSION['error'] = "User has no remaining sit-in sessions.";
            header("Location: admin_dashboard.php");
            exit();
        }

        // Check if PC is available
        $stmt = $conn->prepare("SELECT is_available FROM lab_pcs WHERE lab = ? AND pc_number = ?");
        $stmt->execute([$lab, $pc_number]);
        $pc = $stmt->fetch();

        if (!$pc || !$pc['is_available']) {
            $_SESSION['error'] = "Selected PC is not available.";
            header("Location: admin_dashboard.php");
            exit();
        }

        // Start transaction
        $conn->beginTransaction();

        // Create sit-in session
        $stmt = $conn->prepare("
            INSERT INTO current_sitins (user_id, lab, pc_number, purpose, sitin_time) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $lab, $pc_number, $purpose]);

        // Update PC availability
        $stmt = $conn->prepare("
            UPDATE lab_pcs 
            SET is_available = 0 
            WHERE lab = ? AND pc_number = ?
        ");
        $stmt->execute([$lab, $pc_number]);

        // Commit transaction
        $conn->commit();

        $_SESSION['success'] = "Sit-in session created successfully.";
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $_SESSION['error'] = "Error creating sit-in session: " . $e->getMessage();
    }

    header("Location: admin_dashboard.php");
    exit();
} else {
    header("Location: admin_dashboard.php");
    exit();
} 