<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revenue Types</title>
    <link rel="stylesheet" href="customer/css/view_orders.css">
    <link rel="stylesheet" href="customer/css/checkout.css">
    <link rel="stylesheet" href="customer/css/cards.css">
    <link rel="stylesheet" href="css/view_driver.css">
</head>

<body>
    <?php include('navbar.php'); ?>
    <div class="container">
        <h1>Manage Revenue Types</h1>
        <!-- Separate row for "Add New Customer" button -->
        <div class="livesearch">
            <input type="text" id="liveSearch" placeholder="Search for Order...">
            <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>
        </div>


    </div>

    <table id="ordersTable" class="ordersTable">
        <thead>
            <tr>
                <th>Revenue ID</th>
                <th>Revenue Name</th>
                <th>Revenue Description</th>
                <th colspan ="2">Actions</th>
            </tr>
        </thead>
        <tbody>
            <!-- Revenue Information will be dynamically inserted here -->
        </tbody>

    </table>

    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Edit Revenue Information</h2>

            
            <table id="orderDetailsTable" class="ordersTable">
                <tbody>
                    <!--Revenue details will be automatically populated here -->
                </tbody>
            </table>
        </div>
    </div>

    <script src="scripts/revenue.js"></script>
    <!-- <script src="scripts/group.js"></script> -->
</body>

</html>