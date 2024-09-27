<!DOCTYPE html>
<html lang="en">

<head>
  
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard</title>
    <link rel="stylesheet" href="../../css/customer.css">
    <link rel="stylesheet" href="../../customer/css/view_orders.css">
    <style>
         <style>
        .ordersTable th,
        .ordersTable td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        #orderSummaryTable thead tr th{
            color: #000;
        }
        th{
            color: #000;
        }
    </style>
</head>

<body>
    <div class="dashboard">
        <?php
        include('../driverNavBar.php');

        ?>

        <div class="order-form-container">
            <h2>Update Order Status</h2>
            <form id="orderForm" class="add_unit add_group">
                <!-- Input type t retrieve customer ID and hide it -->
                <input type="text" id="driver_id" name="driver_id" value="<?php echo $_SESSION['driver_id']; ?>" hidden>
                <div class="form-input">
                    <label for="food-name">Select Order:</label>
                    <select name="order-id" id="order-id" class="group_name" placeholder="Select Order">
                        <?php
                        include '../../backend/config.php';
                        // Query to retrieve data from the orders table where delivery_status is 'Assigned' or 'In Transit'
                        $driver_id = $_SESSION['driver_id'];
                        $statuses = ["Assigned", "In Transit"];
                        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
                        $stmt = $conn->prepare("SELECT * FROM orders WHERE driver_id = ? AND delivery_status IN ($placeholders)");

                        // Bind parameters dynamically
                        $params = array_merge([$driver_id], $statuses);
                        $types = str_repeat('s', count($params));
                        $stmt->bind_param($types, ...$params);

                        $stmt->execute();

                        // Bind the result variables
                        $result = $stmt->get_result();

                        // Fetch the results
                        if ($result->num_rows > 0) {
                            echo '<option value="">Select Order</option>';
                            while ($row = $result->fetch_assoc()) {
                                // Store delivery_status as a data attribute in each option
                                echo '<option value="' . $row['order_id'] . '" data-status="' . $row['delivery_status'] . '">'
                                    . $row['order_id'] . " - " . $row['order_date'] . " - " . $row['delivery_status']
                                    . '</option>';
                            }
                        } else {
                            echo '<option value = "">No Orders Available</option>';

                        }

                        // Close the database connection
                        mysqli_close($conn);
                        ?>
                    </select>

                    <input type="text" name="current-status" id="current-status" hidden>

                </div>
                <div class="form-input" id="new-status">
                    <label for="number-of-portion">Select Delivery Status:</label>
                    <select name="order-status" id="order-status" placeholder="Select Delivery Status">
                        <option value="">-- Select Delivery Status --</option>
                        <option value="In Transit"> In Transit</option>
                        <option value="Delivered"> Delivered</option>
                        <option value="Cancelled"> Cancelled</option>
                    </select>
                </div>
                <div class="form-input" id="delivery_auth">
                    <label for="delivery_pin"> Enter Delivery Pin</label>
                    <input type="number" name="delivery_pin" id="delivery_pin" maxlength="4">
                </div>
                <!-- This textarea is hidden by default and will appear if the driver selects "Cancelled" -->
                <div class="form-input" id="cancelReasonContainer">
                    <label for="cancelReason">Reason for Cancellation:</label>
                    <textarea id="cancelReason" name="cancelReason" placeholder="Enter reason for cancellation"
                        maxlength="100"></textarea>
                </div>


                <button type="button" id="updateDeliveryStatus">Update Status</button>
            </form>
        </div>

        <div class="order-summary-container container">

            <h2>My Recent Orders</h2>

            <table id="orderSummaryTable">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Order Date</th>
                        <th>Delivery Fee</th>
                        <th>Delivery Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Food items will be dynamically added here -->
                </tbody>
            </table>
            <div id="pagination" class="pagination"></div>
            <div id="orderModal" class="modal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h2>Order Details</h2>
                    <table id="orderDetailsTable" class="ordersTable">
                        <thead>
                            <tr>
                                <th>Order Date</th>
                                <th>Food Name</th>
                                <th>Number of Portions</th>
                                <th>Status</th>
                                
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Order details will be dynamically inserted here -->
                        </tbody>

                    </table>

                    <button type="button" id="receipt-btn" style="display: none">Print Receipt</button>
                </div>
            </div>


            <script src="../scripts/update_order.js"></script>
            <script src="../scripts/view_pending_orders.js"></script>
</body>

</html>