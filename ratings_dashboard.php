<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revenue Dashboard</title>
    <link rel="stylesheet" href="css/revenue_dashboard.css">
    <link rel="stylesheet" href="css/ratings.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body>
    <header class="nav-header">
        <?php include('navbar.php'); ?>
    </header>
<div id="customer-form">
      <button onclick="openRatings()"><i class="fa fa-eye" aria-hidden="true"></i> View Ratings</button>
    </div>

    <main>
         <!-- Separate row for "Add New Customer" button -->
    
        <h1>Order Ratings Dashboard</h1>

        <div class="dashboard-charts">
            <canvas id="ratingPieChart"></canvas>
            <canvas id="ratingLineChart"></canvas>
            <canvas id="ratingBarChart"></canvas>
            <canvas id="ratingDoughnutChart"></canvas>
        </div>

        <!-- Existing table and stats -->


        <div class="dashboard-stats">
            <!-- Summary Cards -->
            <div class="stat-card summary-card">
                <h2>Total Ratings</h2>
                <p id="totalRating">0</p>
            </div>
            <div class="stat-card summary-card">
                <h2>Top Ratings</h2>
                <p id="topRating">0</p>
            </div>
            <div class="stat-card summary-card">
                <h2>Average Rating</h2>
                <p id="averageRating">0</p>
            </div>
            <div class="stat-card summary-card">
                <h2>Lowest Rating</h2>
                <p id="lowestRating">0</p>
            </div>

            <!-- Average Rating Cards -->
            <!-- <div class="section-divider">Average Ratings</div> -->
            <div class="stat-card average-card">
                <h2>Average Driver Rating</h2>
                <p id="averageDriverRating">0</p>
            </div>
            <div class="stat-card average-card">
                <h2>Average Food Rating</h2>
                <p id="averageFoodRating">0</p>
            </div>
            <div class="stat-card average-card">
                <h2>Average Package Rating</h2>
                <p id="averagePackageRating">0</p>
            </div>
            <div class="stat-card average-card">
                <h2>Average Delivery Time Rating</h2>
                <p id="averagedeliveryTimeRating">0</p>
            </div>

            <!-- Top Rating Cards -->
            <!-- <div class="section-divider">Top Ratings</div> -->
            <div class="stat-card top-card">
                <h2>Top Driver Rating</h2>
                <p id="topDriverRating">0</p>
            </div>
            <div class="stat-card top-card">
                <h2>Top Food Rating</h2>
                <p id="topFoodRating">0</p>
            </div>
            <div class="stat-card top-card">
                <h2>Top Package Rating</h2>
                <p id="topPackageRating">0</p>
            </div>
            <div class="stat-card top-card">
                <h2>Top Delivery Time Rating</h2>
                <p id="topDeliveryTimeRating">0</p>
            </div>

            <!-- Lowest Rating Cards -->
            <!-- <div class="section-divider">Low Ratings</div> -->
            <div class="stat-card low-card">
                <h2>Lowest Driver Rating</h2>
                <p id="lowestDriverRating">0</p>
            </div>
            <div class="stat-card low-card">
                <h2>Lowest Food Rating</h2>
                <p id="lowestFoodRating">0</p>
            </div>
            <div class="stat-card low-card">
                <h2>Lowest Package Rating</h2>
                <p id="lowestPackageRating">0</p>
            </div>
            <div class="stat-card low-card">
                <h2>Lowest Delivery Time Rating</h2>
                <p id="lowestDeliveryTimeRating">0</p>
            </div>
        </div>

        <h2>Recent Ratings</h2>
        <table id="ratingTable">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Driver</th>
                    <th>Customer</th>
                    <th>Driver Rating</th>
                    <th>Food Rating</th>
                    <th>Packaging Rating</th>
                    <th>Delivery Time Rating</th>
                    <th>Date Rated</th>
                </tr>
            </thead>
            <tbody>
                <!-- Data will be inserted here dynamically -->
            </tbody>
        </table>
    </main>

    <script src="scripts/ratings_dashboard.js"></script>
</body>

</html>