<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KaraKata Staffs</title>
    <link rel="stylesheet" href="customer/css/view_orders.css">
    <link rel="stylesheet" href="customer/css/checkout.css">
    <link rel="stylesheet" href="customer/css/cards.css">
    <link rel="stylesheet" href="css/view_driver.css">
     <link rel="stylesheet" href="css/staff.css">
</head>

<body>
    <?php include('navbar.php'); ?>
    <div class="container">
        <h1>Staff Account Reactivation Requests</h1>
        <div class="livesearch">
            <input type="text" id="liveSearch" placeholder="Search for Order...">
            <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>
        </div>


    </div>
    <div class="spinner" id="spinner">
        <div class="rect1"></div>
        <div class="rect2"></div>
        <div class="rect3"></div>
        <div class="rect4"></div>
        <div class="rect5"></div>
    </div>

    <table id="ordersTable" class="ordersTable">
        <thead>
            <tr>
                <th>Staff ID</th>
                <th>FirstName</th>
                <th>LastName</th>
                <th>Reactivation Request Status</th>
                <th>Date Deactivated</th>
                <th>Deactivated By</th>
                <th style="text-align: center;" colspan="2">Actions</th>
            </tr>
        </thead>
        <tbody>
            <!-- Staffs Information will be dynamically inserted here -->
        </tbody>

    </table>
    <div id="pagination" class="pagination"></div>

    <!-- Modal to Delete Staff Details -->
    <div id="staffModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Staff Reactivation Information</h2>
            <table id="staffDetailsTable" class="ordersTable">
                <tbody>
                    <!-- Staff details will be automatically populated here -->

                </tbody>
            </table>
        </div>
    </div>



    <script src="scripts/staff_reactivation_request.js"></script>

</body>

</html>