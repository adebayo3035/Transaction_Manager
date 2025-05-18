<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KaraKata Staffs Deactivation and Reactivation History</title>
    <link rel="stylesheet" href="customer/css/view_orders.css">
    <link rel="stylesheet" href="customer/css/checkout.css">
    <link rel="stylesheet" href="customer/css/cards.css">
    <link rel="stylesheet" href="css/view_driver.css">
</head>

<body>
    <?php include('navbar.php'); ?>
    <div class="container">
        <h1>Staff Deactivation/Reactivation History</h1>
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

    <div class="table-container">
    <table id="ordersTable" class="orders-table">
        <thead>
            <tr>
                <th>Deactivation ID</th>
                <th>Staff ID</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>E-mail Address</th>
                <th>Deactivated By</th>
                <th>Date Deactivated</th>
                <th>Reactivation Status</th>
                <th>Reactivated By</th>
                <th>Date Last Updated</th>
                 
            </tr>
        </thead>
        <tbody>
            <!-- Staff Information will be dynamically inserted here -->
        </tbody>
    </table>
</div>
    <div id="pagination" class="pagination"></div>

    <script src="scripts/staff_deactivation_reactivation_history.js"></script>

</body>

</html>