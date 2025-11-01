<!DOCTYPE html>
<html lang="en">

<head>
    <style>
      
    </style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="customer/css/view_orders.css">
    <link rel="stylesheet" href="customer/css/checkout.css">
    <link rel="stylesheet" href="css/revenue.css">
    <title>View Inflow and Outflow</title>
    <!-- Include your CSS files here -->
</head>

<body>
    <!-- Include your navbar and dashboard navbar here -->
    <?php include('navbar.php'); ?>
    <div class="container">
        <!-- Add your dashboard navigation if applicable -->
        <?php include('dashboard_navbar.php'); ?>
        <div class="livesearch">
            <input type="text" id="liveSearch" placeholder="Search for Transaction...">
            <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>
        </div>
        <div style="margin-bottom: 15px;" class="filter-section">
            <label for="statusFilter">Filter by Status: </label>
            <select id="statusFilter">
                <option value="">All</option>
                <option value="Completed">Completed</option>
                <option value="Cancelled">Cancelled</option>
            </select>
        </div>

        <h1>All Inflows</h1>
    </div>

    <table id="revenueTable" class="transactionsTable ordersTable">
        <thead>
            <tr>
                <th>Revenue ID</th>
                <th>Order ID</th>
                <th>Customer ID</th>
                <th>Amount (N)</th>
                <th>Status</th>
                <th>Date Initiated</th>
                <th>Date Last Updated</th>
                <th>View Details</th>
            </tr>
        </thead>
        <tbody id="ordersTableBody">
            <!-- Revenue Information will be dynamically inserted here -->
        </tbody>
    </table>
    <div id="pagination" class="pagination"></div>

    <div id="revenueModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="receipt-header"> Details for Revenue: <span id="revenueID">Revenue</span></h2>
            <table id="revenueDetailsTable" class="transactionsTable ">
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Revenue Information will be dynamically inserted here -->
                </tbody>
            </table>
            <div class="actionBtn">
                <button type="button" id="receipt-btn">Print Receipt</button>
            </div>
        </div>
    </div>

    <!-- Include your JavaScript files here -->
    <script src="scripts/view_revenue.js"></script>
</body>

</html>