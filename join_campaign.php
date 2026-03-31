<?php
session_start();
require_once 'config.php';

// Redirect non-logged-in users to login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Connect to database
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
    die('DB connection failed: ' . $mysqli->connect_error);
}

$message = '';

// Handle join form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_code'])) {
    $join_code = strtoupper(trim($_POST['join_code']));

    // Verify campaign exists
    $stmt = $mysqli->prepare('SELECT id, name FROM campaigns WHERE join_code = ?');
    $stmt->bind_param('s', $join_code);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 1) {
        $stmt->bind_result($campaign_id, $campaign_name);
        $stmt->fetch();
        $stmt->close();

        // Add participant (ignore if already joined)
        $add = $mysqli->prepare(
            'INSERT IGNORE INTO campaign_participants (campaign_id, user_id) VALUES (?, ?)'  
        );
        $add->bind_param('ii', $campaign_id, $_SESSION['user_id']);
        $add->execute();
        $add->close();

        $message = "Successfully joined campaign: <strong>" . htmlspecialchars($campaign_name) . "</strong>";
    } else {
        $message = '<span class="text-danger">Invalid join code. Please try again.</span>';
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Join Campaign — PokéTop</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container py-5">
    <h1 class="mb-4">Join a Campaign</h1>
    <?php if ($message): ?>
      <div class="alert alert-info"><?= $message ?></div>
    <?php endif; ?>
    <div class="card shadow-sm">
      <div class="card-body">
        <form method="post">
          <div class="mb-3">
            <label for="join_code" class="form-label">Enter Join Code</label>
            <input type="text" name="join_code" id="join_code" class="form-control text-uppercase" maxlength="10" required>
          </div>
          <button type="submit" class="btn btn-primary">Join Campaign</button>
          <a href="dashboard.php" class="btn btn-link">Back to Dashboard</a>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
