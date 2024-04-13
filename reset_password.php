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
include_once "backend/config.php";
// Check if the user is logged in
if (isset($_SESSION['unique_id'])) {
    // Redirect the logged-in user to home page)
    header("Location: homepage.php");
    exit(); // Stop further execution
}

// Set session variable to indicate user is coming from password_reset.php


// // Redirect the user to reset_password.php
// header("Location: reset_password.php");
// exit(); // Stop further execution

?>

  <header>
    <h1>Reset Your Password</h1>
    <a href="index.php">Login</a>
    
  </header>

  <main>
    <form id="adminForm" method="POST" action="backend/reset_password.php">
      <label for="email">Enter Your E-mail Address:</label>
      <input type="email" id="emaile" name="email" required>

      <label for="secret_answer">Enter Your Secret Answer:</label>
      <input type="password" id="secret_answer" name="secret_answer" required>
      <button type="submit" name ="btnPasswordChange">Confirm E-mail</button>
    </form>
  </main>
</body>
</html> 

<?php
// include_once "backend/config.php";
// if (isset($_POST['btnPasswordChange'])) {
//     $email = mysqli_real_escape_string($conn, $_POST['email']);
//     $secret_answer = (md5(filter_var($_POST['secret_answer'], FILTER_SANITIZE_STRING)));
//     // $secretAnswer = (md5(filter_var($_POST['secret_answer'], FILTER_SANITIZE_STRING)));
//     // Validate email and secret answer

//     echo $email . 'and'. $secret_answer;
// }
// else{
//   echo "<script>alert(' Something went wrong, Please try again')".mysqli_error($conn)."; window.location.href='index.php';</script>";
// }
