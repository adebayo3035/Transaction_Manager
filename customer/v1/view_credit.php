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

        .modal .reassign-form {
            margin-top: 20px;
            padding: 20px;
            border: 1px solid #ccc;
            background-color: #f9f9f9;
            display: flex;

        }

        .reassign-form h3 {
            margin-bottom: 10px;
            text-align: center;
        }

        .reassign-form label,
        .reassign-form select,
        .reassign-form button {
            display: block;
            margin-bottom: 10px;
        }

        .reassign-form select {
            width: 50%;
            padding: 10px;
            margin: 5px 0 10px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            outline: none;
        }

        .reassign-form button {
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
        #repayAmountLabel, #repayAmountInput, #submitRepayment{
            display: none;
        }

    </style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Credits History</title>
    <link rel="stylesheet" href="../css/view_orders.css">
    <link rel="stylesheet" href="../css/checkout.css">
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
                    <!-- Order details will be dynamically inserted here -->
                </tbody>

            </table>

            <button type="button" id="repay-btn" style="display: none">Repay Loan</button>

            <!-- <button type="button" id="receipt-btn" style="display: none">Print Receipt</button> -->
            <!-- Reassign Order Form -->
            <div class="reassign-form" id="reassignForm" style="display:none;">
                <span class="close closeRepaymentForm">&times;</span>
                <h3>Repay Outstanding Debt</h3>
                <div class="form-input">
                    <label for="amount">Credit ID:</label>
                    <input type="text" id="creditID" disabled>
                    <label for=" repayment_method"> Select Repayment Method</label>
                    <select name="repayment_method" id="repayment_method">
                        <option value="">--Select an Option--</option>
                        <option value="Full Repayment">Full Repayment</option>
                        <option value="Partial Repayment">Partial Repayment</option>
                    </select>
                    <label for="amount"  id="repayAmountLabel">Input Amount:</label>
                    <input type="text" id="repayAmountInput" placeholder="0000">
                </div>
                <button type="button" id="submitRepayment">Submit</button>
            </div>

        </div>
    </div>

    <script src="../scripts/view_credit.js"></script>
</body>

</html>