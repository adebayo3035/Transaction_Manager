<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css/customer.css">
</head>

<body>
  <?php include "navbar.php" ?>
  <div class="container">
    <h2>Units Manager</h2>

    <!-- Separate row for "Add New Customer" button -->
    <div id="customer-form">
      <button onclick="toggleModal()"><i class="fa fa-plus" aria-hidden="true"></i> Add New Unit</button>
    </div>

    <!-- Live search input -->
    <div class="livesearch">
      <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>
      <input type="text" id="liveSearch" placeholder="Search for Unit...">

    </div>




    <!-- Customer table -->
    <table id="customer-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Unit Name</th>
          <th>Group Name</th>
          <?php include ('backend/check_role.php'); ?>

        </tr>
      </thead>
      <tbody>
        <!-- Customer rows will be dynamically added here -->
        <?php include "backend/display_unit.php"; ?>

      </tbody>
    </table>

  </div>
  <!-- The Modal -->
  <div id="addGroupModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="toggleModal()">&times;</span>
      <!-- Form to add new group -->
      <form action="" method="POST" class="add_unit add_group">
        
        <span class="error_notifier"> </span>
        <div class="form-input">
          <label for="group_name">Select Group:</label>
          <select name="group_id" id="group_name" class="group_name">
            <?php
            include 'backend/config.php';
            // Query to retrieve data from the groups table
            $sql = "SELECT group_id, group_name FROM groups";
            $result = mysqli_query($conn, $sql);

            // Check if any rows are returned
            if (mysqli_num_rows($result) > 0) {
              // Start the select input
              echo '<option value="">Select a Group</option>';

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
        </div>
        <div class="form-input">
          <label for="group_name">Unit Name:</label>
          <input type="text" name="unit_name" class="group_name" required>
        </div>
        
        <button type="submit" name="add_group">Add Unit</button>
      </form>
    </div>
  </div>

  <!-- The Modal -->

  <script>
    // JavaScript to show/hide the modal
    function toggleModal() {
      var userRole = "<?php echo $_SESSION['role']; ?>";
      var modal = document.getElementById("addGroupModal");
      if (userRole === "Super Admin") {
        modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";
      }
      else {
        // Handle any other roles or unauthenticated users
        window.location.href = 'unauthorized.php';
      }
    }

  
      function toggleModal2() {
        var modal2 = document.getElementById('editGroupModal');
        if (modal2.style.display === "none" || modal2.style.display === "") {
          modal2.style.display = "block";
        } else {
          modal2.style.display = "none";
        }
      }
    

    // function to filter table row based on Search Query
    function filterTable() {
      // Get input value and convert to lowercase
      var input = document.getElementById("liveSearch").value.toLowerCase();
      // Get table rows
      var rows = document.getElementById("customer-table").getElementsByTagName("tr");

      // Loop through table rows
      for (var i = 0; i < rows.length; i++) {
        // Get cells in current row
        var cells = rows[i].getElementsByTagName("td");
        var found = false;
        // Loop through cells
        for (var j = 0; j < cells.length; j++) {
          // Check if cell text matches search query
          if (cells[j]) {
            var cellText = cells[j].textContent.toLowerCase();
            if (cellText.indexOf(input) > -1) {
              found = true;
              break;
            }
          }
        }
        // Show or hide row based on search result
        if (found) {
          rows[i].style.display = "";
        } else {
          rows[i].style.display = "none";
        }
      }
    }

    // Add event listener to input field
    document.getElementById("liveSearch").addEventListener("input", filterTable);
  </script>
  <script src="scripts/add_unit.js" charset="UTF-8"></script>
  <script src="scripts/toggle_modal.js" charset="UTF-8"> </script>



</body>

</html>