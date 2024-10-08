<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revenue Dashboard</title>
    <link rel="stylesheet" href="css/revenue_dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body>
    <header class="nav-header">
        <?php include('navbar.php'); ?>
    </header>


    <main>
        <?php include('dashboard_navbar.php'); ?>
        <h1>Revenue Dashboard</h1>

        <!-- Separate row for "Add New Customer" button -->
        <div id="customer-form">
            <button onclick="openModal()"><i class="fa fa-plus" aria-hidden="true" id="createRevenueBtn"></i> Create New
                Revenue Type</button>
            <button onclick="openModal2('revenue_type.php')" id="manageRevenueBtn"><i class="fa fa-eye" aria-hidden="true"
                    id="createRevenueBtn"></i> Manage Revenue Type</button>
        </div>

        <!-- Modal for creating new revenue type -->
        <div id="revenueTypeModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal()">&times;</span>
                <h2>Create New Revenue Type</h2>
                <form id="newRevenueTypeForm">
                    <label for="revenue_type_name">Revenue Type Name:</label>
                    <input type="text" id="revenue_type_name" name="revenue_type_name" required>

                    <label for="revenue_description">Revenue Description:</label>
                    <textarea id="revenue_description" name="revenue_description" required></textarea>

                    <button type="submit">Create Revenue Type</button>
                </form>
            </div>
        </div>

        <!-- Modal for creating new revenue type -->
        <div id="manageRevenueModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal()">&times;</span>
                <h2>Manage Revenue Type</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Revenue ID</th>
                            <th> Revenue Name</th>
                            <th> Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Revenue Types will be populated Here -->
                    </tbody>
                </table>
            </div>
        </div>

        <div class="dashboard-charts">
            <canvas id="revenuePieChart"></canvas>
            <canvas id="revenueLineChart"></canvas>
            <canvas id="revenueBarChart"></canvas>
            <canvas id="revenueDoughnutChart"></canvas>
        </div>

        <!-- Existing table and stats -->


        <div class="dashboard-stats">
            <div class="stat-card">
                <h2>Total Revenue</h2>
                <p id="totalRevenue">0</p>
            </div>
            <div class="stat-card">
                <h2>Pending Orders</h2>
                <p id="pendingOrders">0</p>
            </div>
            <div class="stat-card">
                <h2>Approved Orders</h2>
                <p id="approvedOrders">0</p>
            </div>
            <div class="stat-card">
                <h2>Declined Orders</h2>
                <p id="declinedOrders">0</p>
            </div>
            <div class="stat-card">
                <h2>Assigned Orders</h2>
                <p id="assignedOrders">0</p>
            </div>
            <div class="stat-card">
                <h2>Orders In Transit</h2>
                <p id="transitOrders">0</p>
            </div>
            <div class="stat-card">
                <h2>Orders Delivered</h2>
                <p id="deliveredOrders">0</p>
            </div>
            <div class="stat-card">
                <h2>Orders Cancelled by Customer</h2>
                <p id="cancelledOrders">0</p>
            </div>
            <div class="stat-card">
                <h2>Orders Cancelled on Delivery</h2>
                <p id="cancelledOnDelivery">0</p>
            </div>
        </div>

        <h2>Recent Transactions</h2>
        <table id="revenueTable">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer ID</th>
                    <th>Total Amount</th>
                    <th>Transaction Date</th>
                    <th>Status</th>
                    <th>Delivery Status</th>
                    <th>Driver Name</th>
                    <th>Updated At</th>
                </tr>
            </thead>
            <tbody>
                <!-- Data will be inserted here dynamically -->
            </tbody>
        </table>
    </main>

    <script src="scripts/revenue_dashboard.js"></script>
</body>

</html>