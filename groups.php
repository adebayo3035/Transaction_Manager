<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css/customer.css">
  <script>
    // Confirmation Modal to Delete a customer record
function confirmDelete(event, element) {
    event.preventDefault(); // Prevent the default action (navigation)
    
    var userConfirmed = confirm("Are you sure you want to delete this record?");
    
    if (userConfirmed) {
        // If the user confirmed, navigate to the delete URL
        window.location.href = element.href;
    }
}
</script>
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
      
      <input type="text" id="liveSearch" placeholder="Search for Groups...">
      <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>

    </div>

    <!-- Delete form -->
    <form id="deleteForm" action="backend/delete_group.php" method="post">
      <input type="hidden" name="group_id" value="">
    </form>




    <!-- Customer table -->
    <table id="customer-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Group Name</th>
          <?php include ('backend/check_role.php'); ?>

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
        <span class="error_notifier" id ="error_notifier"> </span>
        <button type="submit" name="add_group">Add Group</button>
      </form>
    </div>
  </div>

  <!-- The Modal -->
  <div id="editGroupModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="toggleModal2()">&times;</span>
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
      var error_notifier = document.getElementById('error_notifier');
      if (userRole === "Super Admin") {
        modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";
        error_notifier.innerHTML = "";
        error_notifier.textContent = "";
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
    
  </script>
  <script src="scripts/add_group.js" charset="UTF-8"></script>
  <script src="scripts/filter_table.js"></script>



</body>

</html>