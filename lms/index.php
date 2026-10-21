<?php
// lms/index.php — Entry point: redirect to login or dashboard
session_start();
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: student/dashboard.php');
    }
} else {
    header('Location: auth/login.php');
}
exit;
