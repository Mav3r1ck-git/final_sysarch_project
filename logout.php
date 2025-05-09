<?php
session_start();
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
session_unset();
session_destroy();
if ($user_type === 'admin') {
    header('Location: admin_login.php');
} else {
    header('Location: user_login.php');
}
exit(); 