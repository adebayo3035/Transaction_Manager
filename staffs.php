<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css/customer.css">
</head>

<body>
  <?php include "navbar.php"; ?>
  <div class="container">
    <h2>Staff Manager</h2>

    <!-- Separate row for "Add New Customer" button -->
  <div id="customer-form">
    <button onclick="addNewStaff()"><i class="fa fa-plus" aria-hidden="true"></i> Add New Staff</button>
  </div>

    <!-- Live search input -->
    <div class="livesearch">
    <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>
      <input type="text" id="liveSearch" placeholder="Search Here...">
      <!--  -->
    </div>




    <!-- Customer table -->
    <table id="customer-table">
      <thead>
        <tr>
          <!-- <th>ID</th> -->
          <th>First Name</th>
          <th>Last Name</th>
          <th>E-mail Address</th>
          <th>Phone Number</th>
        </tr>
      </thead>
      <tbody>
        <!-- Customer rows will be dynamically added here -->
        <?php 
          include "backend/display_staff.php"; 
          echo $_SESSION['role'];
        
        ?>

      </tbody>
    </table>

  </div>
<script>
  
  function addNewStaff() {
    var userRole = "<?php echo $_SESSION['role']; ?>";
      if (userRole === "Super Admin") {
        // Show admin-specific content or redirect to the admin dashboard
        window.location.href = "admin_onboarding.php";
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

  


  

</body>

</html>