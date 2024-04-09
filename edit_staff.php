<?php

	
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Your Profile</title>
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
    <h1>Edit Your Profile</h1>
    
  </header>

  <main>
    <form id="adminForm" method="POST" action="backend/update_staff.php" enctype="multipart/form-data" autocomplete="off">

      <label for="email">Email:</label>
      <input type="email" id="email" name="email" value="<?php echo $row['email']; ?>" required>

      <label for="phone">Phone:</label>
      <input type="tel" id="phone" name="phone" value="<?php echo $row['phone']; ?>" required>

      
      <label for="secret_answer">Enter Your Secret Answer:</label>
      <input type="password" id="secret_answer" name="secret_answer" required>

      <button type="submit" name = "btnUpdate">Update Profile</button>
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
