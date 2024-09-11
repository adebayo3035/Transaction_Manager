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
  <link rel="stylesheet" href="../../css/add_customer.css">
  <style>
    
  </style>
</head>
<body>
  <header>
    <h1>Reset Your Password</h1>
    <a href="index.php"><i class="fa-solid fa-backward"></i>Go Back</a>
  </header>

  <main>
    <form id="adminForm" method="POST" action="backend/reset_password2.php">
      <label for="email">Enter Your E-mail Address:</label>
      <input type="email" id="email" name="email" required>

      <label for="secret_answer">Enter Your Secret Answer:</label>
      <input type="password" id="secret_answer" name="secret_answer" required>
      <button type="submit" name="btnPasswordChange">Confirm E-mail</button>
    </form>
  </main>

  <!-- The Modal -->
  <div id="resetModal" class="modal">
    <div class="modal-content">
      <span class="close" id="closeModal">&times;</span>
      <h2>Reset Your Password</h2>
      <form id="resetPasswordForm">
        <label for="new_password">New Password:</label>
        <input type="password" id="new_password" name="new_password" required>

        <label for="confirm_password">Confirm New Password:</label>
        <input type="password" id="confirm_password" name="confirm_password" required>

        <button type="submit">Reset Password</button>
      </form>
    </div>
  </div>

  <script src="../scripts/reset_password.js"></script>
</body>
</html>
