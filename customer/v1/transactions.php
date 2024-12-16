<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Transactions</title>
    <link rel="stylesheet" href="../css/view_orders.css">
</head>

<body>
<?php include ('customer_navbar.php'); ?>
    <div class="container">
        <div class="livesearch">
            <input type="text" id="liveSearch" placeholder="Search for Order...">
            <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>
        </div>
        <h1>My Transactions</h1>
    </div>

    <table id="ordersTable">
        <thead>
            <tr>
                <th>Trans ID</th>
                <th>Transaction Reference</th>
                <th>Transaction Date</th>
                <th>Total Amount (N)</th>
                <th>Transaction Type</th>
                <th>Actions</th>
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
            <h2>Transaction Details</h2>
            <table id="orderDetailsTable">
                <thead>
                    <tr>
                    <th>Amount</th>
                        <th>Transaction Date</th>
                        <th>Transaction Type</th>
                        <th> Payment Method</th>
                        <th colspan ="2">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Order details will be dynamically inserted here -->
                </tbody>
            </table>
        </div>
    </div>

    <script src="../scripts/view_transactions.js"></script>
</body>

</html>
