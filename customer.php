<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css/customer.css">
  <script>
    // Confirmation Modal to Delete a customer record
    function confirmDelete(customerId) {
      if (confirm('Are you sure you want to delete this customer?')) {
        document.getElementById('deleteForm').customer_id.value = customerId;
        document.getElementById('deleteForm').submit();
      }
    }
  </script>
</head>

<body>
  <?php include "navbar.php" ?>
  <div class="container">
    <h2>Customer Information</h2>

    <!-- Separate row for "Add New Customer" button -->
    <div id="customer-form">
      <button onclick="addNewCustomer()"><i class="fa fa-plus" aria-hidden="true"></i> Add New Customer</button>
    </div>

    <!-- Live search input -->
    <div class="livesearch">
      
      <input type="text" id="liveSearch" placeholder="Search for customers...">
      <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>

    </div>

    <!-- Delete form -->
    <form id="deleteForm" action="backend/delete_customer.php" method="post">
      <input type="hidden" name="customer_id" value="">
    </form>




    <!-- Customer table -->
    <table id="customer-table">
      <thead>
        <tr>
          <th>Customer ID</th>
          <th>Customer Name</th>
          <th>Gender</th>
          <th>Email Address</th>
          <th>Mobile Number</th>
          <th>Group</th>
          <th>Unit</th>
          <th colspan="2">Action</th>
        </tr>
      </thead>
      <tbody>
        <!-- Customer rows will be dynamically added here -->
        <?php include "backend/display_customer.php"; ?>

      </tbody>
    </table>

  </div>


  <script>
    function addNewCustomer() {
      window.location.href = 'add_customer.php';
    }

  </script>
  <script src="scripts/filter_table.js"></script>

</body>

</html>