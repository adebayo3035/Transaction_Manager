<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css/customer.css">
  <script>
    // Confirmation Modal to Delete a customer record
    // function confirmDelete(customerId) {
    //   if (confirm('Are you sure you want to delete this food?')) {
    //     document.getElementById('deleteForm').customer_id.value = customerId;
    //     document.getElementById('deleteForm').submit();
    //   }
    // }
  </script>
  <title>Food Repository</title>
</head>

<body>
  <?php include "navbar.php" ?>
  <div class="container">
    <h2>Food Repository</h2>

    <!-- Separate row for "Add New Customer" button -->
    <div id="customer-form">
      <button onclick="addNewFood()"><i class="fa fa-plus" aria-hidden="true"></i> Add New Food</button>
    </div>

    <!-- Live search input -->
    <div class="livesearch">
      
      <input type="text" id="liveSearch" placeholder="Search for Food...">
      <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>

    </div>

    <!-- Delete form -->
    <!-- <form id="deleteForm" action="backend/delete_food.php" method="post">
      <input type="hidden" name="food_id" value="">
    </form> -->




    <!-- Customer table -->
    <table id="customer-table">
      <thead>
        <tr>
          <th>Food ID</th>
          <th>Food Name</th>
          <!-- <th>Description</th> -->
          <th>Price Per Portion (N)</th>
          <th>Quantity Available</th>
          <th>Availability Status</th>
          
          <th colspan="2">Action</th>
        </tr>
      </thead>
      <tbody>
        <!-- Customer rows will be dynamically added here -->
        <?php include "backend/display_food.php"; ?>

      </tbody>
    </table>

  </div>


  <script>
    function addNewFood() {
      window.location.href = 'add_food.php';
    }
  </script>
  <script src="scripts/filter_table.js"></script>
  <script src="scripts/delete_food.js"></script>
</body>

</html>