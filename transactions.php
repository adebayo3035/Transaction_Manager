<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/transaction.css">
    <link rel="stylesheet" href="customer/css/view_orders.css">
    <link rel="stylesheet" href="customer/css/checkout.css">
    <title>View Transactions</title>
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
        <div style="margin-bottom: 10px;" class="filter-section">
            <label>Transaction Type:</label>
            <select id="transactionTypeFilter">
                <option value="">All</option>
                <option value="Credit">Credit</option>
                <option value="Debit">Debit</option>
                <option value="Others">Others</option>
            </select>

            <label>Status:</label>
            <select id="transactionStatusFilter">
                <option value="">All</option>
                <option value="Pending">Pending</option>
                <option value="Completed">Completed</option>
                <option value="Failed">Failed</option>
                <option value="Declined">Declined</option>
            </select>
            <button id="applyTransactionFilters" class="filter-btn">Apply Filters</button>
        </div>

        <h1>All Transactions</h1>
    </div>

    <table id="ordersTable" class="transactionsTable">
        <thead>
            <tr>
                <th>Trans Ref</th>
                <th>Trans Type</th>
                <th>Amount (N)</th>
                <th>Payment Method</th>
                <th>Status</th>
                <th>Trans Date</th>
                <th>View Details</th>
            </tr>
        </thead>
        <tbody id="ordersTableBody">
            <!-- Staffs Information will be dynamically inserted here -->
        </tbody>
    </table>
    <div id="pagination" class="pagination"></div>

    <div id="transactionModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="receipt-header">Transaction Details for Reference: <span id="transactionReference">Reference</span>
            </h2>
            <table id="transactionDetailsTable" class="transactionsTable">
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Transaction details will be dynamically inserted here via a modal -->
                </tbody>
            </table>
            <div class="actionBtn">
                <button type="button" id="receipt-btn">Print Receipt</button>
            </div>
        </div>
    </div>

    <!-- Include your JavaScript files here -->
    <script src="scripts/view_transactions.js"></script>
</body>

</html>