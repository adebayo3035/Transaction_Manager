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
    $id=$_GET['id'];
	$query=mysqli_query($conn,"select * from `admin_tbl` where unique_id='$id'");
	$row=mysqli_fetch_array($query);
    $oldPhoto = $row['photo'];
?>


  <header>
    <h1>Change Profile Picture</h1>
    
  </header>

  <main>
    <form id="adminForm" action="backend/change_profile_picture.php" method="POST" enctype="multipart/form-data" autocomplete="off">
    <div id="photoContainer">
        <img id="uploadedPhoto" src="backend/admin_photos/<?php echo $row['photo']; ?>" alt="Uploaded Photo">
    </div>
    <label for="photo">Photo:</label>
      <input type="file" id="photo" name="photo" accept="image/*" onchange="displayPhoto(this)" required>

      <label for="secret_answer">Secret Answer:</label>
      <input type="password" id="secret_answer" name="secret_answer" required>

      <button type="submit" name = "btnChangePicture">Change Picture</button>
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
