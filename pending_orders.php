<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Pending Orders</title>
    <style>
    </style>
    <link rel="stylesheet" href="css/view_orders.css">
</head>
<body>
<?php 
include('navbar.php');
// echo $_SESSION['restriction_id'];
?>
<header>
    <h1>View Pending Orders</h1>
    <?php include('dashboard_navbar.php'); ?>
  </header>
<div class="container">
        <div class="livesearch">
            <input type="text" id="liveSearch" placeholder="Search for Order...">
            <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>

        </div>
        <!-- <h1>View Pending Orders</h1> -->
    </div>
    <div class="select" id="selectAllContainer">
        <label for ="select-all"> Select All</label>
        <input type="checkbox" name="order-selectAll" id = "select-all">
    </div>
    <table id="customer-table">
        <thead>
            <tr>
                <th> Select Order </th>
                <th>Order ID</th>
                <th>Order Date</th>
                <th>Total Amount (N)</th>
                <th>Status</th>
                <th>Assigned to</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <!-- Orders will be dynamically inserted here -->
        </tbody>
    </table>
    <div id="modalButtons">
                <button id="bulk-approve-btn" class = "btn btn-approve">Approve Selected</button>
                <button id="bulk-decline-btn" class = "btn btn-decline">Decline Selected</button>
        </div>

    <!-- The Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
             <h2 id ="order_details">Order Details</h2>
            <table id="orderDetailsTable">
                <thead>
                    <tr>
                        <th>Food Name</th>
                        <th>Number of Portions</th>
                        <th>Price per Portion (N)</th>
                        <th>Total Price (N)</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Order details will be dynamically inserted here -->
                </tbody>
            </table>
            <div id="modalButtons">
                <button id="approveButton" class = "btn btn-approve">Approve</button>
                <button id="declineButton" class = "btn btn-decline">Decline</button>
            </div>
        </div>
    </div>

    <script src="scripts/pending_orders.js"></script>
    <script src="scripts/filter_table.js"></script>
</body>
</html>
