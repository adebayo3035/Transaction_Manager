<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Customer Information</title>
  <link rel="stylesheet" href="css/add_customer.css">
  <style type="text/css">
    
    #edit_photo a {
      color: yellow;
      padding-left: 14px;
      text-decoration: none;
      font-size: 15px;
    }
    #edit_photo a i{
      
      padding-left: 4px;
      
    }

    </style>
</head>
<body>
<?php 
include "navbar.php"; 
$id=$_GET['id'];
$query=mysqli_query($conn,"select * from `customers` where customer_id='$id'");
$row=mysqli_fetch_array($query);

$group_id = $row['group_id'];
$query2=mysqli_query($conn,"select * from `groups` where group_id='$group_id'");
$row2=mysqli_fetch_array($query2);
if(!($row2['group_name'])){
  $row2['group_name'] = "Group Not Found";
}

$unit_id = $row['unit_id'];
$query3=mysqli_query($conn,"select * from `unit` where unit_id='$unit_id'");
$row3=mysqli_fetch_array($query3);
if(!($row3['unit_name'])){
  $row3['unit_name'] = "Unit Not Found";
}
?>

  <header>
    <h1>Edit Customer Info</h1>
    <?php echo "<button id ='edit_photo'><a href='change_customer_picture.php?id=" . $id . "'>Change Profile Picture<i class='fa fa-pencil-square-o' aria-hidden='true'></i></a></button>"; ?>
    <!-- <button onclick="changePicture()"><a href='change_picture.php?id=" . $id . "'><i class="fa fa-pencil-square-o" aria-hidden="true"></i> Change Customer Picture </a></button> -->
  </header>

  <main>
    <form method="POST" action="backend/update_customer.php" enctype="multipart/form-data" autocomplete="off">
    <input type="text" id="unit_id" name="customer_id" value = '<?php echo $row['customer_id'] ?>' hidden>
      <label for="firstName">First Name:</label>
      <input type="text" id="firstName" name="firstName" required value = "<?php echo $row['firstname']; ?>">

      <label for="lastName">Last Name:</label>
      <input type="text" id="lastName" name="lastName" required value = "<?php echo $row['lastname']; ?>">

      <label for="gender">Gender:</label>
      <select id="gender" name="gender" required>
        <option value = "<?php echo $row['gender']; ?>"><?php echo $row['gender']; ?> </option>
        <?php 
          if ($row['gender'] == 'male'){
            echo '<option value = "female"> Female </option>';
          }
          else{
            echo '<option value = "male"> Male </option>';
          }
          ?>
      </select>

      <label for="email">Email:</label>
      <input type="email" id="email" name="email" required value = "<?php echo $row['email']; ?>">
      <input type="email" id="email" name="current_email" hidden value = "<?php echo $row['email']; ?>">

      <label for="email">Password:</label>
      <input type="password" id="password" name="password" required value = "<?php echo md5($row['password']); ?>">

      <label for="phoneNumber">Phone Number:</label>
      <input type="tel" id="phoneNumber" name="phoneNumber" required value = "<?php echo $row['mobile_number']; ?>">
      <input type="tel" id="phoneNumber" name="current_phoneNumber" hidden value = "<?php echo $row['mobile_number']; ?>">

      <label for="address">Address:</label>
      <input type="text" id="address" name="address" required value = "<?php echo $row['address']; ?>">

      <div id="photoContainer">
        <img id="uploadedPhoto" src="backend/customer_photos/<?php echo $row['photo']; ?>" alt="Customer Photo">
      </div>

      <label for="address">Current Group:</label>
      <input type="text" id="current_group" name="current_group" value = "<?php echo $row2['group_name']; ?>" disabled>

      <label for="address">Current Unit:</label>
      <input type="text" id="current_unit" name="current_unit" value = "<?php echo $row3['unit_name']; ?>" disabled>


      <label for="selectOption">Select  Group:</label>
    <select id="selectOption" onchange="handleClick()" name="group">
        <!-- Add options for groups -->
        
        
        <?php
            include 'backend/config.php';
            // Query to retrieve data from the groups table
            $sql = "SELECT group_id, group_name FROM groups WHERE group_id != {$row['group_id']}";
            $result = mysqli_query($conn, $sql);

            // Check if any rows are returned
            if (mysqli_num_rows($result) > 0) {
              // Start the select input
              echo '<option value="">--Select a Group--</option>';
              echo '<option value ="'.  $row['group_id'] .'"> '. $row2['group_name'] .' </option>';

              // Fetch data and generate options
              while ($row4 = mysqli_fetch_assoc($result)) {
                echo '<option value="' . $row4['group_id'] . '">' . $row4['group_name'] . '</option>';
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

    <div id="additionalInput" style="display: none;">
        <label for="selectedUnit">Select Unit:</label>
        <select id="selectedUnit" name="unit" onchange = "displayButton()">
        <option value = "<?php echo $row['unit_id']; ?>"><?php echo $row3['unit_name']; ?> </option>
        
        </select>
    </div>
     

      <button type="submit" name="add_customer" id="add_customer">Update Customer Info</button>
    </form>
  </main>

  <script src="scripts/photo_upload.js"></script>

  <script src= "scripts/add_customer.js"></script>

  <!-- <script>
    document.addEventListener('DOMContentLoaded', function() {
        var selectGroup = document.getElementById('selectOption');
        var currentGroup = document.getElementById('current_group');

        var selectUnit = document.getElementById('selectedUnit');
        var currentUnit = document.getElementById('current_unit');

        // Add event listener to detect changes in the select input
        selectGroup.addEventListener('change', function() {
            // Update the value of the text input with the selected value of the select input
            current_group.value = selectGroup.value;
        });
        selectUnit.addEventListener('change', function() {
            // Update the value of the text input with the selected value of the select input
            current_unit.value = selectUnit.value;
        });
    });
</script> -->
</body>
</html>
