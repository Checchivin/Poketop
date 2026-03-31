<?php
// login_handler.php
session_start();
require_once 'config.php';
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
    die('DB connection failed: ' . $mysqli->connect_error);
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$stmt = $mysqli->prepare(
    'SELECT id, password_hash, user_type FROM users WHERE username = ?'
);
$stmt->bind_param('s', $username);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $_SESSION['error'] = 'Invalid username or password.';
    header('Location: login.php');
    exit;
}
$stmt->bind_result($id, $hash, $userType);
$stmt->fetch();

if (password_verify($password, $hash)) {
    // Authentication success
    session_regenerate_id(true);
    $_SESSION['user_id']   = $id;
    $_SESSION['username']  = $username;
    $_SESSION['user_type'] = $userType;  // 'standard' or 'admin'
    if ($userType === 'admin') {
        header('Location: admin_dashboard.php'); // your admin landing page
    } else {
        header('Location: dashboard.php');       // your standard user landing page
    }
    exit;
} else {
    $_SESSION['error'] = 'Invalid username or password.';
    header('Location: login.php');
    exit;
}
