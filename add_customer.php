<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Customer Information</title>
  <link rel="stylesheet" href="css/add_customer.css">
</head>
<body>
<?php 
include "navbar.php"; 
?>

  <header>
    <h1>Add Customer Information</h1>
  </header>

  <main>
    <form method="POST" action="backend/add_customer.php" enctype="multipart/form-data" autocomplete="off">
      <label for="firstName">First Name:</label>
      <input type="text" id="firstName" name="firstName" required>

      <label for="lastName">Last Name:</label>
      <input type="text" id="lastName" name="lastName" required>

      <label for="gender">Gender:</label>
      <select id="gender" name="gender" required>
        <option value="male">Male</option>
        <option value="female">Female</option>
        <option value="other">Other</option>
      </select>

      <label for="email">Email:</label>
      <input type="email" id="email" name="email" required>

      <label for="phoneNumber">Phone Number:</label>
      <input type="tel" id="phoneNumber" name="phoneNumber" required>

      <label for="address">Address:</label>
      <input type="text" id="address" name="address" required>

      
        <!-- Add options for units -->

      <label for="photo">Photo:</label>
      <input type="file" id="photo" name="photo" accept="image/*" onchange="displayPhoto(this)" required>

      <div id="photoContainer">
        <img id="uploadedPhoto" src="#" alt="Uploaded Photo">
      </div>

      <label for="selectOption">Select  Group:</label>
    <select id="selectOption" onchange="handleClick()" name="group">
        <!-- Add options for groups -->
        
        <?php
            include 'backend/config.php';
            // Query to retrieve data from the groups table
            $sql = "SELECT group_id, group_name FROM groups";
            $result = mysqli_query($conn, $sql);

            // Check if any rows are returned
            if (mysqli_num_rows($result) > 0) {
              // Start the select input
              echo '<option value="">--Select a Group--</option>';

              // Fetch data and generate options
              while ($row = mysqli_fetch_assoc($result)) {
                echo '<option value="' . $row['group_id'] . '">' . $row['group_name'] . '</option>';
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
        <select id="selectedUnit" name="unit">
        
        </select>
    </div>
     

      <button type="submit" name="add_customer">Add Customer</button>
    </form>
  </main>

  <script src="scripts/photo_upload.js"></script>

  <script>
        function displayInput() {
            var selectElement = document.getElementById("selectOption");
            var additionalInputDiv = document.getElementById("additionalInput");

            // Display the additional input only if "option2" is selected
            if (selectElement.value !== "") {
                additionalInputDiv.style.display = "flex";
                additionalInputDiv.style.flexDirection = "column";
            } else {
                additionalInputDiv.style.display = "none";
            }
        }
    </script>

<script>
        var selectedGroupId; // Variable to store the selected group ID

        function handleGroupSelection() {
            var selectElement = document.getElementById("selectOption");
            selectedGroupId = selectElement.value;

            // You can use selectedGroupId in another query or perform other actions
            // console.log("Selected Group ID:", selectedGroupId);

            // Fetch and update the units based on the selected group
            fetchUnits(selectedGroupId);
        }

        function fetchUnits(groupId) {
            var unitSelectElement = document.getElementById("selectedUnit");

            // Clear existing options
            unitSelectElement.innerHTML = "";
            var option = document.createElement("option");
                        option.value = "";
                        option.text = "--Select a Unit--";
                        unitSelectElement.add(option);

            // Use AJAX to fetch units from the server
            var xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    // Parse the JSON response
                    var units = JSON.parse(this.responseText);

                    // Populate the unit dropdown with the fetched units
                    units.forEach(function(unit) {
                        var option = document.createElement("option");
                        option.value = unit.unit_id;
                        option.text = unit.unit_name;
                        unitSelectElement.add(option);
                    });
                }
            };

            // Make a GET request to the PHP script with the selected group ID
            xhr.open("GET", "fetchUnits.php?groupId=" + groupId, true);
            xhr.send();
        }
    </script>

<script>
        function handleClick(){
            displayInput();
            handleGroupSelection();
        }
    </script>
</body>
</html>
