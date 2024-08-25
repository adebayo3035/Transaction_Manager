<!DOCTYPE html>
<html lang="en">
<head>
<script src="https://kit.fontawesome.com/7cab3097e7.js" crossorigin="anonymous"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Nunito&family=Roboto&display=swap" rel="stylesheet" />
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
?>

  <header>
    <h1>Reset Your Password</h1>
    <a href="index.php"><i class="fa-solid fa-backward"></i>Go Back</a>

    
  </header>

  <main>
    <form id="adminForm" method="POST" action="backend/reset_password2.php">
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
