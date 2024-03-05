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
    <input type="text" id="liveSearch" placeholder="Search for customers">
    <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>
  </div>
  

  

  <!-- Customer table -->
  <table id="customer-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Group Name</th>
        <th colspan = "2">Action</th>
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
            var modal = document.getElementById("addGroupModal");
            modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";
        }

  // // Sample data (replace this with your actual data)
  // const customers = [
  //   { id: 1, name: "Laliga" },
  //   { id: 2, name: "Serie A"}
  //   // Add more customer objects as needed
  // ];

  // // Function to render customer table rows
  // function renderCustomerTable() {
  //   const tableBody = document.querySelector('#customer-table tbody');
  //   tableBody.innerHTML = '';

  //   customers.forEach(customer => {
  //     const row = document.createElement('tr');
  //     row.innerHTML = `
  //       <td>${customer.id}</td>
  //       <td>${customer.name}</td>
       
  //       <td>
  //         <span class="edit-icon" onclick="editCustomer(${customer.id})">&#9998;</span>
  //         <span class="delete-icon" onclick="deleteCustomer(${customer.id})">&#128465;</span>
  //       </td>
  //     `;
  //     tableBody.appendChild(row);
  //   });
  // }

  // // Function to add a new customer
  // function addNewCustomer() {
  //   window.location.href = 'add_group.php';
  // }

  // // Function to edit a customer
  // function editCustomer(customerId) {
  //   // Implement your logic to edit a customer
  //   // You may show a form or use a modal for user input
  //   // After editing, update the customers array and re-render the table
  //   alert(`Implement logic to edit customer with ID ${customerId}`);
  // }

  // // Function to delete a customer
  // function deleteCustomer(customerId) {
  //   // Implement your logic to delete a customer
  //   // After deleting, update the customers array and re-render the table
  //   alert(`Implement logic to delete customer with ID ${customerId}`);
  // }

  // // Initial render
  // renderCustomerTable();
</script>
<script src = "scripts/add_group.js" charset="UTF-8"></script>

</body>
</html>
