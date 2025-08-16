<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="../../css/customer.css">
    <link rel="stylesheet" href="../css/order_form.css">
    <link rel="stylesheet" href="../css/dashboard.css">
</head>

<body>
    <div class="dashboard">
        <!-- Include customer navigation -->
        <?php include('customer_navbar.php'); ?>

        <div class="order-form-container">
            <div id="packControls">
                <label for="packQuantity">How Many Packs are you Ordering:</label>
                <input type="number" id="packQuantity" min="1" value="1">
                <button id="createPackButton"><i class="fa-solid fa-plus"></i> Create Packs</button>
            </div>
            <div class="form-input pack-container" id="pack-container">
                <label for="pack-selector">Select Pack:</label>
                <select name="pack-selector" id="pack-selector">
                    <option value="Pack-1">Pack 1</option>
                    <option value="Pack-1">Pack 2</option>
                    <option value="Pack-1">Pack 3</option>
                </select>
            </div>
            <div class="food-selector" id ="food-selector-container">
                <h2>Select Order for <span id="pack-number"></span></h2>

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
                        <input type="number" id="number-of-portion" name="number-of-portion" min="1"
                            placeholder="Enter Number of Portions">
                    </div>

                    <button type="button" id="addFoodButton"><i class="fa-solid fa-plus"></i> Add to Cart </button>
                </form>
                <!-- <button type="button" id="closePack"><i class="fa-solid fa-close"></i> Close Pack </button> -->
            </div>
        </div>

        <div class="order-summary-container container" id ="order-summary-container">
            <h2>Order Summary</h2>
            <table id="orderSummaryTable">
                <thead>
                    <tr>
                        <th>S/N</th>
                        <th>Pack No.</th>
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