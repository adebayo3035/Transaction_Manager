<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password</title>
  <link rel="stylesheet" href="css/add_customer.css">
</head>
<body>
<?php
session_start();
echo  $_SESSION['customerID'];
include_once "backend/config.php";
// Check if the user is logged in
if (isset($_SESSION['unique_id'])) {
    // Redirect the logged-in user to the desired page (e.g., home page)
    header("Location: homepage.php");
    exit(); // Stop further execution
}
// Check if the user has come from reset_password.php
if (!isset($_SESSION['from_reset_password']) || !$_SESSION['from_reset_password']) {
  // Redirect the user to password_reset.php
  header("Location: reset_password.php");
  exit(); // Stop further execution
}

// Clear the session variable
//unset($_SESSION['from_reset_password']);
?>
  <header>
    <h1>Reset Your Password</h1>
    <a href="index.php">Login</a>
  </header>

  <main>
    <form id="adminForm" method="POST" action="backend/password_reset.php">
      <label for="new_password">Enter New Password:</label>
      <input type="password" id="new_password" name="new_password" required>

      <label for="confirm_password">Confirm Password</label>
      <input type="password" id="confirm_password" name="confirm_password" required>
      <button type="submit">Reset Password</button>
    </form>
  </main>
</body>

</html> 

