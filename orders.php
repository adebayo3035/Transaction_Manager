<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Orders</title>
    <link rel="stylesheet" href="css/orders.css">
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
        <h1>All Orders</h1>
    </div>

    <table id="ordersTable" class="ordersTable">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Order Date</th>
                <th>Customer ID</th>
                <th>Initial Price (N)</th>
                <th>Delivery Status</th>
                <th>View Details</th>
            </tr>
        </thead>
        <tbody id = "ordersTableBody">
            <!-- Staffs Information will be dynamically inserted here -->
        </tbody>

    </table>
    <div id="pagination" class="pagination"></div>


    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Order Details for Order: <span id="orderID"> Order ID</span></h2>
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