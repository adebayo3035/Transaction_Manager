<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add New Admin</title>
  <link rel="stylesheet" href="css/add_customer.css">
</head>
<body>
<?php 
include "navbar.php"; 
?>

  <header>
    <h1>Staff Onboarding</h1>
    
  </header>

  <main>
    <form id="adminForm" method="POST" action="backend/admin_onboarding.php" enctype="multipart/form-data" autocomplete="off">
      <label for="firstName">First Name:</label>
      <input type="text" id="firstName" name="firstName" required>

      <label for="lastName">Last Name:</label>
      <input type="text" id="lastName" name="lastName" required>

      <label for="email">Email:</label>
      <input type="email" id="email" name="email" required>

      <label for="phone">Phone:</label>
      <input type="tel" id="phone" name="phone" required>

      <label for="password">Password:</label>
      <input type="password" id="password" name="password" required>
      <span id ="password_hint"> Password must not be less than 8 characters, it must contain uppercase, special character and digit </span>

      <label for="secret_question">Secret Question:</label>
      <input type="text" id="secret_question" name="secret_question" required>

      <label for="secret_answer">Secret Answer:</label>
      <input type="password" id="secret_answer" name="secret_answer" required>

      <label for="selectOption">Select Role:</label>
      <select id="selectOption" name="role" required>
        <option value="Super Admin">Super Admin</option>
        <option value="Admin">Admin</option>

      <label for="photo">Photo:</label>
      <input type="file" id="photo" name="photo" accept="image/*" onchange="displayPhoto(this)" required>

      <div id="photoContainer">
        <img id="uploadedPhoto" src="#" alt="Uploaded Photo">
      </div>

      <button type="submit">Register</button>
    </form>
  </main>

  <script src="scripts/photo_upload.js">
    
  </script>
  <script>
    document.getElementById('adminForm').addEventListener('submit', function(event)) {
      // Prevent the default form submission behavior
      event.preventDefault();
    }
  </script>

</body>
</html>
