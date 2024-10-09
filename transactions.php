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
        .actionBtn{
            display: flex;
            justify-content: space-around;
        }
        .actionBtn button:hover{
            background-color: #000; 
        }
    </style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Revenue</title>
    <link rel="stylesheet" href="customer/css/view_orders.css">
    <link rel="stylesheet" href="customer/css/checkout.css">
</head>

<body>
    <?php include('navbar.php'); ?>
    <div class="container">
        <?php include('dashboard_navbar.php'); ?>
        <div class="livesearch">
            <input type="text" id="liveSearch" placeholder="Search for Order...">
            <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>
        </div>
        <h1>All Transactions</h1>
    </div>

    <table id="ordersTable" class="ordersTable">
        <thead>
            <tr>
            <th>Order ID</th>
                    <th>Customer ID</th>
                    <th>Total Amount</th>
                    <th>Transaction Date</th>
                    <th>Status</th>
                    <th>Delivery Status</th>
                    <th>Driver Name</th>
                    <th>Updated At</th>
            </tr>
        </thead>
        <tbody>
            <!-- Transactions will be dynamically inserted here -->
        </tbody>

    </table>
    <div id="pagination" class="pagination"></div>


    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Transaction Details</h2>
            <table id="orderDetailsTable" class="ordersTable">
                <thead>
                    <tr>
                        <th>Food ID</th>
                        <th>Food Name</th>
                        <th>Number of Portions</th>
                        <th>Price per Portion (N)</th>
                        <th>Total Price (N)</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Order details will be dynamically inserted here via a modal -->
                </tbody>

            </table>
            <div class="actionBtn">
            <button type="button" id="receipt-btn">Print Receipt</button>
            <button type="button" id="reassign-order">Reassign Order</button>

            </div>
            

            <!-- <button type="button" id="receipt-btn" style="display: none">Print Receipt</button> -->
            <!-- Reassign Order Form -->
            <div class="reassign-form" id="reassignForm" style="display:none;">
                <h3>Reassign Order</h3>
                <div class="form-input">
                    <label for="driver">Select Driver:</label>
                    <select id="driver" name="driver">
                        <!-- Options will be dynamically populated -->
                         <option value="">-- Select Driver --</option>
                    </select>
                </div>
                <button type="button" id="submitReassign">Submit</button>
            </div>
        </div>
    </div>

    <script src="scripts/view_orders.js"></script>
</body>

</html>