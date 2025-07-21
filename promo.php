<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promo Offers</title>
    <link rel="stylesheet" href="customer/css/view_orders.css">
    <link rel="stylesheet" href="customer/css/checkout.css">
    <link rel="stylesheet" href="customer/css/cards.css">
    <link rel="stylesheet" href="css/view_driver.css">
    <!-- <link rel="stylesheet" href="css/promo.css"> -->
</head>

<body>
    <?php include('navbar.php'); ?>
     <?php include('dashboard_navbar.php'); ?>
    <div class="container">
        <h1>Promo Archives</h1>
        
        <!-- Separate row for "Add New Customer" button -->
        <div id="customer-form">
            <button onclick="toggleModal('addNewPromoModal')"><i class="fa fa-plus" aria-hidden="true"></i> Add New
                Promo</button>
        </div>
        
        <div class="livesearch">
            <input type="text" id="liveSearch" placeholder="Search for Order...">
            <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>
        </div>


    </div>

    <table id="ordersTable" class="ordersTable">
        <thead>
            <tr>

                <th>Promo Code</th>
                <th>Promo Name</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Status</th>
                <th>Delete Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id = "ordersTableBody">
            <!-- Promo Information will be dynamically inserted here -->
        </tbody>

    </table>
    <div id="pagination" class="pagination"></div>


    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Promo Information</h2>

            <table id="orderDetailsTable" class="ordersTable">
                <tbody>
                    <!-- Promo details will be automatically populated here -->
                </tbody>
            </table>
        </div>
    </div>


    <!-- Modal to add new Promo Offer -->
    <div id="addNewPromoModal" class="modal">
        <div class="modal-content" id="card-form">
            <span class="close">&times;</span>
            <h2>Create New Promo Offer</h2>
            <form id="addPromoForm">
                <div id="promoForm">

                    <div class="form-input">
                        <label for="promo_code">Promo Code:</label>
                        <input type="text" id="promo_code" name="promo_code" required readonly>
                    </div>

                    <div class="form-input">
                        <label for="promo_name">Promo Name:</label>
                        <input type="text" id="promo_name" name="promo_name" required>
                    </div>

                    <div class="form-input">
                        <label for="promo_description">Promo Description:</label>
                        <textarea id="promoDescription" name="promo_description" placeholder="Describe the promo details"
                            required maxlength="150"></textarea>
                            <div id="charCount" class="char-counter">150 characters remaining</div>
                    </div>

                    <div class="form-input">
                        <label for="start_date">Start Date:</label>
                        <input type="datetime-local" id="start_date" name="start_date" required>
                    </div>

                    <div class="form-input">
                        <label for="end_date">End Date:</label>
                        <input type="datetime-local" id="end_date" name="end_date" required>
                    </div>

                    <div class="form-input">
                        <label for="discount_type">Discount Type:</label>
                        <select id="discount_type" name="discount_type" required>
                            <option value="" disabled selected>Select Type of Discount</option>
                            <option value="percentage">Percentage (%)</option>
                            <option value="flat">Flat Rate</option>
                        </select>
                    </div>

                    <div class="form-input">
                        <label for="discount_value">Discount Percentage (%):</label>
                        <input type="number" id="discount" name="discount_value" min="1" max="100" required>
                    </div>

                    <div class="form-input">
                        <label for="eligibility_criteria">Eligibility Criteria:</label>
                        <select id="eligibility_criteria" name="eligibility_criteria" required>
                            <option value="" disabled selected>Select eligibility criteria</option>
                            <option value="All Customers">All Customers</option>
                            <option value="New Customers">New Customers</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>

                    <div class="form-input">
                        <label for="min_order_value">Minimum Order Value (#):</label>
                        <input type="number" id="min_order_value" name="min_order_value" required>
                    </div>
                    <div class="form-input">
                        <label for="max_discount">Maximum Discount Obtainable (#):</label>
                        <input type="number" id="max_discount" name="max_discount" required>
                    </div>

                    <div class="form-input">
                        <button type="submit" id="createPromoButton">Create Promo</button>
                    </div>
                </div>


            </form>

            <div id="addPromoMessage"></div>

        </div>
    </div>

    <script src="scripts/promo.js"></script>


</body>

</html>