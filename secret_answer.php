<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Secret Question and Answer</title>
  <link rel="stylesheet" href="css/add_customer.css">
</head>
<body>
<?php 
include "navbar.php"; 
    $id=$_GET['id'];
	$query=mysqli_query($conn,"select * from `admin_tbl` where unique_id='$id'");
	$row=mysqli_fetch_array($query);
?>

  <header>
    <h1>Reset Your Secret Question and Answer</h1>
    
  </header>

  <main>
    <form id="adminForm" method="POST" action="backend/secret_answer.php">
      <label for="email">Enter E-mail Address:</label>
      <input type="email" id="email" name="email" required>

      <label for="question">Enter New Secret Question:</label>
      <input type="text" id="question" name="secret_question" required>

      <label for="answer">Enter New Secret Answer:</label>
      <input type="password" id="answer" name="secret_answer" required>

      <label for="answer2">Confirm Secret Answer:</label>
      <input type="password" id="answer2" name="confirm_answer" required>

      <button type="submit" name ="btnUpdateAnswer">Update Secret Info</button>
    </form>
  </main>
</body>
</html> 

