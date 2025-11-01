<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Credits History</title>
    <link rel="stylesheet" href="customer/css/view_orders.css">
    <link rel="stylesheet" href="customer/css/checkout.css">
    <link rel="stylesheet" href="css/credit_history.css">
</head>

<body>
    <?php include('navbar.php'); ?>
    <div class="container">
        <?php include('dashboard_navbar.php'); ?>
        <div class="livesearch">
            <input type="text" id="liveSearch" placeholder="Search for Order...">
            <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>
        </div>
        <h1>Customer Credit History</h1>
        <div class="filters">
            <div class="applyFilters">
                <label>Repayment Status:</label>
                <select id="repaymentStatus">
                    <option value="">All</option>
                    <option value="Paid">Paid</option>
                    <option value="Pending">Pending</option>
                    <option value="Void">Void</option>
                </select>

                <label>Due Status:</label>
                <select id="dueStatus">
                    <option value="">All</option>
                    <option value="Due">Due</option>
                    <option value="Overdue">Overdue</option>
                </select>

                <button id="applyFilters" class="btn btn-primary">Apply</button>
            </div>
        </div>


    </div>

    <table id="ordersTable" class="ordersTable">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Credit ID</th>
                <th>Customer ID</th>
                <th> Total Amount (N)</th>
                <th>Bal (N)</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="ordersTableBody">
            <!-- Staffs Information will be dynamically inserted here -->
        </tbody>

    </table>
    <div id="pagination" class="pagination"></div>


    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Credit Details for Credit Order ID: <span id="credit_order_id"></span></h2>

            <!-- Credit Details Table -->
            <table id="orderDetailsTable" class="ordersTable">
                <thead>
                    <tr>
                        <th>Total Amount</th>
                        <th>Amount Repaid</th>
                        <th>Remaining Balance</th>
                        <th>Repayment Status</th>
                        <th>Due Date</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Order details will be dynamically inserted here -->
                </tbody>
            </table>

            <!-- Repayment History Section -->
            <h3>Repayment History</h3>
            <table id="repaymentHistoryTable" class="ordersTable">
                <thead>
                    <tr>
                        <th>Payment Date</th>
                        <th>Amount Paid</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Repayment history details will be dynamically inserted here -->
                </tbody>
            </table>
            <div id="repaymentPagination" class="pagination"></div>
        </div>
    </div>


    <script src="scripts/credit_history.js"></script>
</body>

</html>