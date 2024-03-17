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
    <form id="adminForm" method="POST" action="backend/reset_password.php">
      <label for="email">Enter Your E-mail Address:</label>
      <input type="email" id="emaile" name="email" required>

      <label for="secret_answer">Enter Your Secret Answer:</label>
      <input type="text" id="secret_answer" name="secret_answer" required>
      <button type="submit">Confirm E-mail</button>
    </form>
  </main>
</body>
</html> 

