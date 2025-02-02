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
    
    </style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Ongoing Promos</title>
    <link rel="stylesheet" href="../css/view_orders.css">
    <link rel="stylesheet" href="../css/checkout.css">
    <script src="https://kit.fontawesome.com/7cab3097e7.js" crossorigin="anonymous"></script>
</head>

<body>
<?php include ('customer_navbar.php'); ?>
    <div class="container">
        <div class="livesearch">
            <input type="text" id="liveSearch" placeholder="Search for Order...">
            <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>
        </div>
        <h1>Ongoing Promos</h1>
    </div>

    <table id="ordersTable" class="ordersTable">
        <thead>
            <tr>
                <<th>Promo Code</th>
                <th>Promo Name</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <!-- Orders will be dynamically inserted here -->
        </tbody>

    </table>
    <div id="pagination" class="pagination"></div>


    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Promo Details</h2>
            <table id="orderDetailsTable" class="ordersTable">
                <thead>
                    
                </thead>
                <tbody>
                    <!-- Order details will be dynamically inserted here -->
                </tbody>

            </table>
        </div>
    </div>

    <script src="../scripts/view_promo.js"></script>
</body>

</html>