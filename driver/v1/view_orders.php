<!DOCTYPE html>
<html lang="en">

<head>
    <style>
        .ordersTable th,
        .ordersTable td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th{
            background-color: #000;
        }
    </style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Orders</title>
    <link rel="stylesheet" href="../css/view_orders.css">
    
</head>

<body>
<?php include "driver_navbar.php"; ?>
    <div class="container">
        <div class="livesearch">
            <input type="text" id="liveSearch" placeholder="Search for Order...">
            <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>
        </div>
        <h1>My Orders</h1>
    </div>

    <table id="orderSummaryTable" class="ordersTable">
        <thead>
            <tr>
                <th> Order ID</th>
                <th>Order Date</th>
                <th>Delivery Fee (N)</th>
                <th>Delivery Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id = "ordersTableBody">
            <!-- Orders will be dynamically inserted here -->
        </tbody>

    </table>
    <div id="pagination" class="pagination"></div>


    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Order Details</h2>
            <table id="orderDetailsTable" class="ordersTable">
                <thead>
                    <tr>
                        <th>Order Date</th>
                        <th>Food Name</th>
                        <th>Number of Portions</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Order details will be dynamically inserted here -->
                </tbody>

            </table>

            <button type="button" id="receipt-btn">Print Receipt</button>
        </div>
    </div>

    <script src="../scripts/view_orders.js"></script>
</body>

</html>