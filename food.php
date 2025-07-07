<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Groups</title>
    <link rel="stylesheet" href="customer/css/view_orders.css">
    <link rel="stylesheet" href="customer/css/checkout.css">
    <link rel="stylesheet" href="customer/css/cards.css">
    <link rel="stylesheet" href="css/view_driver.css">
</head>

<body>
    <?php include('navbar.php'); ?>
    <div class="container">
        <h1>Food Repository</h1>
        <!-- Separate row for "Add New Customer" button -->
        <div id="customer-form">
            <button onclick="toggleModal('addNewFoodModal')"><i class="fa fa-plus" aria-hidden="true"></i> Add New
                Food Item</button>
        </div>
        <div class="livesearch">
            <input type="text" id="liveSearch" placeholder="Search for Order...">
            <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>
        </div>


    </div>

    <table id="ordersTable" class="ordersTable">
        <thead>
            <tr>
                <th>Food ID</th>
                <th>Food Name</th>
                <th>Price Per Portion</th>
                <th>Quantity Available</th>
                <th>Availability Status</th>
                <th colspan ="2">Actions</th>
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
            <h2>Edit Food Item</h2>
            

            
            <table id="orderDetailsTable" class="ordersTable">
                <tbody>
                    <!-- Driver details will be automatically populated here -->
                </tbody>
            </table>
        </div>
    </div>


    <!-- Modal to add new Group -->
    <div id="addNewFoodModal" class="modal">
        <div class="modal-content" id="card-form">
            <span class="close2 close">&times;</span>
            <h2>Add New Food Item</h2>
            <form id="addFoodForm">
                <div class="form-input">
                    <label for="add_food_name">Food Name:</label>
                    <input type="text" id="add_food_name" name="add_food_name" required>
                </div>
                <div class="form-input">
                    <label for="add_food_description">Food Description:</label>
                    <input type="text" id="add_food_description" name="add_food_description" required>
                </div>
                <div class="form-input">
                    <label for="add_food_price">Food Price:</label>
                    <input type="number" id="add_food_price" name="add_food_price" required>
                </div>
                <div class="form-input">
                    <label for="add_food_quantity">Quantity Available:</label>
                    <input type="number" id="add_food_quantity" name="add_food_quantity" required>
                </div>
                <div class="form-group">
                <label for="available">Available:</label>
                <select id="available" name="available">
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>
                
                
                <button type="submit" id="submitBtnAdd">Add Food Item</button>
            </form>

            <div id="addFoodMessage"></div>

        </div>
    </div>

    <script src="scripts/food.js"></script>
</body>

</html>