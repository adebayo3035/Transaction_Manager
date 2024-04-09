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
    <h2>Groups Manager</h2>

    <!-- Separate row for "Add New Customer" button -->
    <div id="customer-form">
      <button onclick="toggleModal()"><i class="fa fa-plus" aria-hidden="true"></i> Add New Group</button>
    </div>

    <!-- Live search input -->
    <div class="livesearch">
    <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>
      <input type="text" id="liveSearch" placeholder="Search for customers">
     
    </div>




    <!-- Customer table -->
    <table id="customer-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Group Name</th>
          <th colspan="2">Action</th>
        </tr>
      </thead>
      <tbody>
        <!-- Customer rows will be dynamically added here -->
        <?php include "backend/display_group.php"; ?>

      </tbody>
    </table>

  </div>
  <!-- The Modal -->
  <div id="addGroupModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="toggleModal()">&times;</span>
      <!-- Form to add new group -->
      <form action="" method="POST" class="add_group">
        <div class="form-input">
          <label for="group_name">Group Name:</label>
          <input type="text" name="group_name" class="group_name" required>
        </div>
        <span class="error_notifier"> </span>
        <button type="submit" name="add_group">Add Group</button>
      </form>
    </div>
  </div>

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
  <script src="scripts/add_group.js" charset="UTF-8"></script>

  

</body>

</html>