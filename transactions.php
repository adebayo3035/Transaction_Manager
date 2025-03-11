<!DOCTYPE html>
<html lang="en">

<head>
    <style>
        .transactionsTable th,
        .transactionsTable td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .transactionsTable th{
            text-align: center;
        }
        .transactionsTable td{
            text-align: left;
        }

        .modal .details-form {
            margin-top: 20px;
            padding: 20px;
            border: 1px solid #ccc;
            background-color: #f9f9f9;
            display: flex;
        }

        .details-form h3 {
            margin-bottom: 10px;
            text-align: center;
        }

        .details-form label,
        .details-form select,
        .details-form button {
            display: block;
            margin-bottom: 10px;
        }

        .details-form select {
            width: 50%;
            padding: 10px;
            margin: 5px 0 10px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            outline: none;
        }

        .details-form button {
            font-size: 14px;
            padding: 10px;
            background-color: #0275d8;
            cursor: pointer;
        }

        .actionBtn {
            display: flex;
            justify-content: space-around;
        }

        .actionBtn button:hover {
            background-color: #000;
        }
        #receipt-header{
            text-align: center;
            font-weight: lighter;
            font-size: 15px;
        }
        #receipt-header span{
            color:#0275d8;
            font-weight: bold;
        }
    </style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        <h1>All Transactions</h1>
    </div>

    <table id="transactionsTable" class="transactionsTable">
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
        <tbody>
            <!-- Transactions will be dynamically inserted here -->
        </tbody>
    </table>
    <div id="pagination" class="pagination"></div>

    <div id="transactionModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="receipt-header">Transaction Details for Reference: <span id="transactionReference">Reference</span></h2>
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
