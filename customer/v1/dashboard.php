<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard</title>
    <link rel="stylesheet" href="../../css/customer.css">
</head>

<body>
    <div class="dashboard">
        <?php
        include ('../customerNavBar.php');

        ?>

        <div class="order-form-container">
            <h2>Place an Order</h2>
            <form id="orderForm" class="add_unit add_group">
                <!-- Input type t retrieve customer ID and hide it -->
                <input type="text" id="customer_id" name="customer_id" value="<?php echo $_SESSION['customer_id']; ?>"
                    hidden>
                <div class="form-input">
                    <label for="food-name">Select Food Item:</label>
                    <select name="food-name" id="food-name" class="group_name" placeholder="Select Food Item">
                        <?php
                        include '../../backend/config.php';
                        // Query to retrieve data from the groups table
                        $sql = "SELECT * FROM food WHERE availability_status != 0 AND available_quantity != 0;";
                        $result = mysqli_query($conn, $sql);

                        // Check if any rows are returned
                        if (mysqli_num_rows($result) > 0) {
                            // Start the select input
                            echo '<option value="">Select a Food Item</option>';

                            // Fetch data and generate options
                            while ($row = mysqli_fetch_assoc($result)) {
                                echo '<option value="' . $row['food_id'] . '" data-name="' . $row['food_name'] . '" data-price="' . $row['food_price'] . '">' . $row['food_name'] . '</option>';
                            }

                            // Close the select input
                            echo '</select>';
                        } else {
                            echo '<option> No Food Available </option>.';
                        }

                        // Close the database connection
                        mysqli_close($conn);
                        ?>
                    </select>
                </div>
                <div class="form-input">
                    <label for="number-of-portion">Enter Number of Portions</label>
                    <input type="number" id="number-of-portion" name="number-of-portion" class="group_name" min="1"
                        placeholder="Enter Number of Portions">
                </div>
                <!-- <div class="form-input">
                <label for="total_amount_input">Total Amount</label>
                <input type="number" id="total_amount_input" name="total_amount_input" class="group_name">
            </div> -->

                <button type="button" id="addFoodButton">Add to Cart</button>
            </form>
        </div>

        <div class="order-summary-container container">

            <h2>Order Summary</h2>

            <table id="orderSummaryTable">
                <thead>
                    <tr>
                        <th>S/N</th>
                        <th>Food Name</th>
                        <th>Number of Portions</th>
                        <th>Price per Portion (N)</th>
                        <th>Total Price (N)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Food items will be dynamically added here -->
                </tbody>
            </table>
            <div class="total-amount">
                <h3>Total Amount: N<span id="totalAmount">0.00</span></h3>

            </div>
            <button type="button" id="submitOrderButton">Checkout</button>
        </div>
        <div id="message"></div>

        <script src="../scripts/order_form.js"></script>
</body>

</html>