<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$mysqli = app_db();
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
    redirect_to('login.php');
}
$stmt->bind_result($id, $hash, $userType);
$stmt->fetch();

if (password_verify($password, $hash)) {
    // Authentication success
    session_regenerate_id(true);
    $_SESSION['user_id']   = $id;
    $_SESSION['username']  = $username;
    $_SESSION['user_type'] = $userType;
    redirect_for_user_type();
}

$_SESSION['error'] = 'Invalid username or password.';
redirect_to('login.php');
