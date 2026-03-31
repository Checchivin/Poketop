<?php
// register_handler.php
session_start();
require_once 'config.php';      // defines DB_HOST, DB_USER, DB_PASS, DB_NAME
// Optionally use your existing mysqli_database wrapper here:
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
    die('DB connection failed: ' . $mysqli->connect_error);
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Basic validation
if (strlen($username) < 3 || strlen($password) < 6) {
    $_SESSION['error'] = 'Username must be ≥3 chars; password ≥6 chars.';
    header('Location: register.php');
    exit;
}

// Prevent duplicate usernames
$stmt = $mysqli->prepare('SELECT id FROM users WHERE username = ?');
$stmt->bind_param('s', $username);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $_SESSION['error'] = 'Username already taken.';
    header('Location: register.php');
    exit;
}
$stmt->close();

// Hash & insert
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $mysqli->prepare(
    'INSERT INTO users (username, password_hash) VALUES (?, ?)'
);
$stmt->bind_param('ss', $username, $hash);
if ($stmt->execute()) {
    // Registration successful → redirect to login
    header('Location: login.php?registered=1');
    exit;
} else {
    $_SESSION['error'] = 'Registration failed. Please try again.';
    header('Location: register.php');
    exit;
}
