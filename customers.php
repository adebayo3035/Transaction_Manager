<!DOCTYPE html>
<html lang="en">
<head>
  <style>
.modal {
      display: none;
      position: fixed;
      z-index: 1;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0, 0, 0, 0.5);
    }
    
    .modal-content {
      background-color: #fefefe;
      margin: 10% auto;
      padding: 20px;
      border: 1px solid #888;
      width: 80%;
      max-width: 600px;
    }
    .modal-content form{
      display: flex;
      flex-direction: column;
      justify-content: flex-start;
      align-items: flex-start;
    }
    
    .close {
      color: #aaa;
      float: right;
      font-size: 28px;
      font-weight: bold;
    }
    
    .close:hover,
    .close:focus {
      color: black;
      text-decoration: none;
      cursor: pointer;
    }
  </style>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css/customer.css">
</head>
<body>
<?php include "navbar.php" ?>
<div class="container">
  <h2>Customer Manager</h2>

  <!-- Separate row for "Add New Customer" button -->
  <div id="customer-form">
    <button onclick="addNewCustomer()"><i class="fa fa-plus" aria-hidden="true"></i> Add New Customer</button>
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
        <th>Name</th>
        <th>Email</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <!-- Customer rows will be dynamically added here -->
    </tbody>
  </table>
</div>

<!-- Modal for editing customer -->
<div id="editCustomerModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal()">&times;</span>
    <!-- Your edit customer form or content goes here -->
    <h2>Edit Customer</h2>
    <!-- Example form -->
    <form>
      <label for="name">Name:</label>
      <input type="text" id="name" name="name" value="">
      <label for="email">Email:</label>
      <input type="email" id="email" name="email" value="">
      <button type="submit">Save Changes</button>
    </form>
  </div>
</div>

<script>
  // Sample data (replace this with your actual data)
  const customers = [
    { id: 1, name: "John Doe", email: "john@example.com" },
    { id: 2, name: "Jane Smith", email: "jane@example.com" },
    // Add more customer objects as needed
  ];

  // Function to render customer table rows
  function renderCustomerTable() {
    const tableBody = document.querySelector('#customer-table tbody');
    tableBody.innerHTML = '';

    customers.forEach(customer => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${customer.id}</td>
        <td>${customer.name}</td>
        <td>${customer.email}</td>
        <td>
         
          <span class="edit-icon" onclick="openEditModal(${customer.id})">&#9998;</span>
          <span class="delete-icon" onclick="deleteCustomer(${customer.id})">&#128465;</span>
        </td>
      `;
      tableBody.appendChild(row);
    });
  }

  // Function to add a new customer
  function addNewCustomer() {
    window.location.href = 'add_customer.php';
  }

  // Function to edit a customer
  function editCustomer(customerId) {
    // Implement your logic to edit a customer
    // You may show a form or use a modal for user input
    // After editing, update the customers array and re-render the table
    alert(`Implement logic to edit customer with ID ${customerId}`);
  }

  // Function to delete a customer
  function deleteCustomer(customerId) {
    // Implement your logic to delete a customer
    // After deleting, update the customers array and re-render the table
    alert(`Implement logic to delete customer with ID ${customerId}`);
  }

  // Function to open edit customer modal
  function openEditModal(customerId) {
    const modal = document.getElementById('editCustomerModal');
    modal.style.display = "block";
    // You can add logic here to pre-fill the form fields with customer data for editing
  }

   // Function to close modal
   function closeModal() {
    const modal = document.getElementById('editCustomerModal');
    modal.style.display = "none";
  }

  // Initial render
  renderCustomerTable();
</script>

</body>
</html>
