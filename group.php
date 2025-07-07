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
        <h1>Groups Manager</h1>
        <!-- Separate row for "Add New Customer" button -->
        <div id="customer-form">
            <button onclick="toggleModal('addNewGroupModal')"><i class="fa fa-plus" aria-hidden="true"></i> Add New
                Group</button>
        </div>
        <div class="livesearch">
            <input type="text" id="liveSearch" placeholder="Search for Order...">
            <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>
        </div>


    </div>

    <table id="ordersTable" class="ordersTable">
        <thead>
            <tr>
                <th>Group ID</th>
                <th>Group Name</th>
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
            <h2>Edit Group Information</h2>

            
            <table id="orderDetailsTable" class="ordersTable">
                <tbody>
                    <!-- Driver details will be automatically populated here -->
                </tbody>
            </table>
        </div>
    </div>


    <!-- Modal to add new Group -->
    <div id="addNewGroupModal" class="modal">
        <div class="modal-content" id="card-form">
            <span class="close2 close">&times;</span>
            <h2>Add New Group</h2>
            <form id="addGroupForm">
                <div class="form-input">
                    <label for="add_group_name">Group Name:</label>
                    <input type="text" id="add_group_name" name="add_group_name" required>
                </div>
                
                <button type="submit" id="submitBtnAdd">Add Group</button>
            </form>

            <div id="addGroupMessage"></div>

        </div>
    </div>

    <script src="scripts/group.js"></script>
    <!-- <script src="scripts/group.js"></script> -->
</body>

</html>