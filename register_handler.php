<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$mysqli = app_db();

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Basic validation
if (strlen($username) < 3 || strlen($password) < 6) {
    $_SESSION['error'] = 'Username must be ≥3 chars; password ≥6 chars.';
    redirect_to('register.php');
}

// Prevent duplicate usernames
$stmt = $mysqli->prepare('SELECT id FROM users WHERE username = ?');
$stmt->bind_param('s', $username);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $_SESSION['error'] = 'Username already taken.';
    redirect_to('register.php');
}
$stmt->close();

// Hash & insert
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $mysqli->prepare(
    'INSERT INTO users (username, password_hash) VALUES (?, ?)'
);
$stmt->bind_param('ss', $username, $hash);
if ($stmt->execute()) {
    redirect_to('login.php?registered=1');
}

$_SESSION['error'] = 'Registration failed. Please try again.';
redirect_to('register.php');
