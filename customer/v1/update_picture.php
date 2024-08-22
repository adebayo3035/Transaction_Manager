<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Change Profile Picture</title>
  <link rel="stylesheet" href="../../css/add_customer.css">
</head>
<body>
<?php 
    include "../customerNavBar.php"; 
    $id=$_SESSION['customer_id'];
	$query=mysqli_query($conn,"select * from `customers` where customer_id='$id'");
	$row=mysqli_fetch_array($query);
    $oldPhoto = $row['photo'];
?>
    
    

  <main>
  
    <form id="adminForm" method="POST" enctype="multipart/form-data" autocomplete="off">
    <h2>Update Your Picture</h2>
    <div id="photoContainer">
        <img id="uploadedPhoto" src="../../backend/customer_photos/<?php echo $row['photo']; ?>" alt="Uploaded Photo">
    </div>
    <label for="photo">Photo:</label>
      <input type="file" id="photo" name="photo" accept="image/*"required>

      <label for="secret_answer">Secret Answer:</label>
      <input type="password" id="secret_answer" name="secret_answer" required>
      <input type="text" id="customer_id" name="customer_id" value = "<?php echo $row['customer_id']; ?>" hidden>

      <button type="submit" name = "btnChangeCustomerPicture">Change Picture</button>
      <div class="message" id="message"></div>
    </form>
  </main>

  <script src="../scripts/update_photo.js">
    
  </script>
  <script>
    document.getElementById('adminForm').addEventListener('submit', function(event)) {
      // Prevent the default form submission behavior
      event.preventDefault();
    }
  </script>

</body>
</html>
