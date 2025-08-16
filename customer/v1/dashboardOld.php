<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard</title>
    <link rel="stylesheet" href="../../css/customer.css">
    <link rel="stylesheet" href="../css/order_form.css">
</head>
<body>
    <div class="dashboard">
        <!-- Include customer navigation -->
        <?php include ('customer_navbar.php'); ?>

        <div class="order-form-container">
            <h2>Place an Order</h2>
            <form id="orderForm" class="add_unit add_group">
                <input type="text" id="customer_id" name="customer_id" hidden>
                <div class="form-input">
                    <label for="food-name">Select Food Item:</label>
                    <select name="food-name" id="food-name" class="group_name">
                        <option value="">--Select Food Item--</option>
                        <!-- Options will be populated dynamically via JS -->
                    </select>
                </div>
                <div class="form-input">
                    <label for="number-of-portion">Enter Number of Portions:</label>
                    <input type="number" id="number-of-portion" name="number-of-portion" min="1" placeholder="Enter Number of Portions">
                </div>
                <button type="button" id="addFoodButton"><i class="fa-solid fa-plus"></i> Add to Cart </button>
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
                <tbody></tbody>
            </table>
            <div class="total-amount">
                <h3>Total Amount: <span id="totalAmount">0.00</span></h3>
            </div>
            <button type="button" id="submitOrderButton">Checkout</button>
        </div>
        <div id="message"></div>

        <script src="../scripts/order_form.js"></script>
    </div>
</body>
</html>
