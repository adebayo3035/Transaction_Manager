<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Unit Information</title>
  <link rel="stylesheet" href="css/add_customer.css">
</head>
<body>
<?php 
include "navbar.php"; 
    $id=$_GET['id'];
	$query=mysqli_query($conn,"select * from `unit` where unit_id='$id'");
	$row=mysqli_fetch_array($query);
    
    $group_id = $row['group_id'];
    $query2=mysqli_query($conn,"select * from `groups` where group_id='$group_id'");
	$row2=mysqli_fetch_array($query2);
?>

  <header>
    <h1>Edit Unit Info</h1>
  </header>

  <main>
    <form method="POST" action="backend/update_unit.php"  autocomplete="off">
    <input type="text" id="unit_id" name="unit_id" value = '<?php echo $row['unit_id'] ?>' hidden>
      <label for="unit_name">Unit Name:</label>
      <input type="text" id="unit_name" name="unit_name" value = '<?php echo $row['unit_name'] ?>' required>
      <input type="text" name="unit_name_hidden" value = '<?php echo $row['unit_name'] ?>' hidden>

      

      <label for="group_id">Select  Group:</label>
        <select id="group_id" name="group_id" >
        <!-- Add options for groups -->
        
        <?php
            include 'backend/config.php';
            // Query to retrieve data from the groups table
            $sql = "SELECT group_id, group_name FROM groups";
            $result = mysqli_query($conn, $sql);

            // Check if any rows are returned
            if (mysqli_num_rows($result) > 0) {
              // Start the select input
              echo '<option value="' . $row2['group_id'] . '">' . $row2['group_name'] . '</option>';
            //   echo '<option value="$row2[">--Select a Group--</option>';

              // Fetch data and generate options
              while ($row3 = mysqli_fetch_assoc($result)) {
                echo '<option value="' . $row3['group_id'] . '">' . $row3['group_name'] . '</option>';
              }

              // Close the select input
              echo '</select>';
            } else {
              echo '<option> No groups found </option>.';
            }

            // Close the database connection
            mysqli_close($conn);
        ?>

          </select>
      <button type="submit" name="btnUpdateUnit">Update Unit</button>
    </form>
  </main>

</body>
</html>
