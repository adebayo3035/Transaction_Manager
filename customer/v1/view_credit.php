<!DOCTYPE html>
<html lang="en">

<head>
    <style>


    </style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Credits History</title>
    <link rel="stylesheet" href="../css/view_orders.css">
    <link rel="stylesheet" href="../css/checkout.css">
    <link rel="stylesheet" href="../../css/credit_history.css">
</head>

<body>
    <?php include('customer_navbar.php'); ?>
    <div class="container">
        <div class="livesearch">
            <input type="text" id="liveSearch" placeholder="Search for Order...">
            <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>
        </div>
        <h1>My Credits History</h1>
    </div>

    <table id="ordersTable" class="ordersTable">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Credit ID</th>
                <th>Order Date</th>
                <th>Remaining Balance (N)</th>
                <th>Status</th>
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
            <h2>Credit Details</h2>
            <table id="orderDetailsTable" class="ordersTable">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th> Total Amount</th>
                        <th> Amount Repaid</th>
                        <th> Remaining Balance</th>
                        <th>Repayment Status</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Credit details will be dynamically inserted here -->
                </tbody>

            </table>
            <div class="extraButtons">
                <button type="button" id="repay-btn" style="display: none">Repay Loan</button>
                <button type="button" id="repayment-history">Repayment History</button>
            </div>



            <!-- Modal to Repay Outstanding Debit -->
            <div class="reassign-form" id="reassignForm" style="display:none;">
                <span class="close closeRepaymentForm">&times;</span>
                <h3>Repay Outstanding Debt</h3>
                <div class="form-input">
                    <label for="amount">Credit ID:</label>
                    <input type="text" id="creditID" disabled>
                    <br />
                    <label for=" repayment_method"> Select Repayment Method</label>
                    <select name="repayment_method" id="repayment_method">
                        <option value="">--Select an Option--</option>
                        <option value="Full Repayment">Full Repayment</option>
                        <option value="Partial Repayment" id="partial">Partial Repayment</option>
                    </select>
                    <label for="amount" id="repayAmountLabel">Input Amount:</label>
                    <input type="text" id="repayAmountInput" placeholder="0000">
                </div>
                <button type="button" id="submitRepayment">Submit</button>
            </div>

            <!-- Modal to Display Repayment History -->
            <!-- Add this modal structure right after the repayment form in your HTML -->
            <div id="repaymentHistoryModal" class="modal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h2>Repayment History</h2>
                    <table id="repaymentHistoryTable" class="ordersTable">
                        <thead>
                            <tr>
                                <th>Repayment ID</th>
                                <th>Date</th>
                                <th>Amount Paid</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Repayment history will be dynamically inserted here -->
                        </tbody>
                    </table>
                    <!-- Add pagination controls -->
                    <div class="pagination-controls">
                        <button id="prevPageBtn" disabled>Previous</button>
                        <span id="pageInfo">Page 1 of 1</span>
                        <button id="nextPageBtn" disabled>Next</button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="../scripts/view_credit.js"></script>
</body>

</html>