<?php
// register.php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register — PokéTop</title>
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-6">
        <div class="card shadow-sm">
          <div class="card-body">
            <h3 class="card-title mb-4 text-center">Create an Account</h3>
            <?php if(!empty($_SESSION['error'])): ?>
              <div class="alert alert-danger">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
              </div>
            <?php endif; ?>
            <form action="register_handler.php" method="post" novalidate>
              <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input
                  type="text"
                  class="form-control"
                  id="username"
                  name="username"
                  required
                  minlength="3"
                  maxlength="50">
              </div>
              <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input
                  type="password"
                  class="form-control"
                  id="password"
                  name="password"
                  required
                  minlength="6">
              </div>
              <button type="submit" class="btn btn-primary w-100">Register</button>
            </form>
            <p class="mt-3 text-center">
              Already have an account? <a href="login.php">Log in</a>
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
