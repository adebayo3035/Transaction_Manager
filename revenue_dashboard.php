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
        <?php include ('navbar.php'); ?>
    </header>


    <main>
    <?php include('dashboard_navbar.php'); ?>
        <h1>Revenue Dashboard</h1>
        
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
                <h2>Cancelled Order</h2>
                <p id="cancelledOrders">0</p>
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