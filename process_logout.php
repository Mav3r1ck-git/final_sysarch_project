<?php
session_start();
require_once 'database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $session_id = $_POST['session_id'];
    $user_id = $_POST['user_id'];
    $was_rewarded = $_POST['was_rewarded'];

    try {
        // Get current sit-in session details
        $stmt = $conn->prepare("
            SELECT * FROM current_sitins 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$session_id, $user_id]);
        $session = $stmt->fetch();

        if (!$session) {
            $_SESSION['error'] = "Session not found.";
            header("Location: admin_dashboard.php");
            exit();
        }

        // Start transaction
        $conn->beginTransaction();

        // Move session to logged_out_sitins
        $stmt = $conn->prepare("
            INSERT INTO logged_out_sitins (
                user_id, lab, pc_number, purpose, 
                sitin_time, logout_time, was_rewarded
            ) VALUES (?, ?, ?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([
            $session['user_id'],
            $session['lab'],
            $session['pc_number'],
            $session['purpose'],
            $session['sitin_time'],
            $was_rewarded
        ]);

        // Delete from current_sitins
        $stmt = $conn->prepare("DELETE FROM current_sitins WHERE id = ?");
        $stmt->execute([$session_id]);

        // Update PC availability
        $stmt = $conn->prepare("
            UPDATE lab_pcs 
            SET is_available = 1 
            WHERE lab = ? AND pc_number = ?
        ");
        $stmt->execute([$session['lab'], $session['pc_number']]);

        // If rewarded, add points
        $bonus_awarded = false;
        if ($was_rewarded) {
            // Add 1 point
            $stmt = $conn->prepare("
                UPDATE users 
                SET points = points + 1 
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);

            // Check if points is now divisible by 3
            $stmt = $conn->prepare("SELECT points FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            if ($user && $user['points'] % 3 == 0) {
                // Add 1 bonus session
                $stmt = $conn->prepare("UPDATE users SET remaining_sessions = remaining_sessions + 1 WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $bonus_awarded = true;
            }
        }

        // Decrease remaining_sessions by 1 (for both logout and reward)
        $stmt = $conn->prepare("
            UPDATE users 
            SET remaining_sessions = remaining_sessions - 1 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);

        // Commit transaction
        $conn->commit();

        $_SESSION['success'] = "Student logged out successfully" . ($was_rewarded ? " and rewarded" : "") . ($bonus_awarded ? " (Bonus session awarded!)" : "") . ".";
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $_SESSION['error'] = "Error processing logout: " . $e->getMessage();
    }

    header("Location: admin_dashboard.php");
    exit();
} else {
    header("Location: admin_dashboard.php");
    exit();
} 