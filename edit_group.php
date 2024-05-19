
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Group Name</title>
  <link rel="stylesheet" href="css/add_customer.css">
</head>
<body>
<?php 
include "navbar.php"; 
    $id=$_GET['id'];
	$query=mysqli_query($conn,"select * from `groups` where group_id='$id'");
	$row=mysqli_fetch_array($query);
?>

  <header>
    <h1>Edit Group Profile</h1>
    
  </header>

  <main>
    <form id="adminForm" method="POST" action="backend/update_group.php"autocomplete="off">
    <input type="text" id="group_id" name="group_id" value="<?php echo $row['group_id']; ?>" hidden>
      <label for="group_name">Group Name:</label>
      <input type="text" id="group_name" name="group_name" value="<?php echo $row['group_name']; ?>" required>

      <button type="submit" name = "btnUpdateGroup">Update Group Name</button>
    </form>
  </main>

  <script>
    document.getElementById('adminForm').addEventListener('submit', function(event)) {
      // Prevent the default form submission behavior
      event.preventDefault();
    }
  </script>

</body>
</html>
