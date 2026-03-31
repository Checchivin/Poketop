<?php
// index.php
session_start();

// If the user is not logged in, send them to the login page
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// If they are logged in, redirect based on their user_type
switch ($_SESSION['user_type']) {
    case 'admin':
        header('Location: admin_dashboard.php');
        break;

    case 'standard':
    default:
        header('Location: dashboard.php');
        break;
}

exit;
?>