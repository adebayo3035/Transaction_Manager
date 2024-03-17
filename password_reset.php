<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password</title>
  <link rel="stylesheet" href="css/add_customer.css">
</head>
<body>

  <header>
    <h1>Reset Your Password</h1>
    
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

