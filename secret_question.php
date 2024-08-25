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
  <title>Get Secret Question</title>
  <link rel="stylesheet" href="css/add_customer.css">
</head>
<body>

  <header>
    <h1>Check your Secret Question</h1>
    <a href="index.php"><i class="fa-solid fa-backward"></i>Go Back</a>

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

