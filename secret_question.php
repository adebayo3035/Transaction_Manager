<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Get Secret Question</title>
  <link rel="stylesheet" href="css/add_customer.css">
</head>
<body>

  <header>
    <h1>Check your Secret Question</h1>
    
  </header>

  <main>
    <form id="adminForm" method="POST" action="backend/secret_question.php">
      <label for="email">Enter Your E-mail Address:</label>
      <input type="email" id="emaile" name="email" required>

      <label for="password">Enter Your password:</label>
      <input type="password" id="password" name="password" required>
      <button type="submit">Get Secret Question</button>
    </form>
  </main>
</body>
</html> 

