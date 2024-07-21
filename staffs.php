<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css/customer.css">
  <title> Transaction Manager - Staff Repository</title>
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
    
      <input type="text" id="liveSearch" placeholder="Search Here...">
      <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>
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
  
</script>
<script src="scripts/filter_table.js"></script>




  

</body>

</html>