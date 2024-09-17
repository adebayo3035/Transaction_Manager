<!-- Updated dashboard.html with additional and modified styles -->

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Food Vending Dashboard</title>
  
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/view_orders.css">

</head>

<body>
  <?php include "navbar.php" ?>
  <header>
    <h1>Food Vending Dashboard</h1>
    <?php include('dashboard_navbar.php'); ?>
  </header>

  <main>

    <section id="overview">
      <h2>Overview</h2>
      <div class="grid">
        <div class="card">
          <h3>Total Orders Today</h3>
          <p id="totalOrders">Loading...</p>
        </div>
        <div class="card">
          <h3>Total Inflow for Today</h3>
          <p id="totalRevenue">Loading...</p>
        </div>
        <div class="card">
          <h3>Customers</h3>
          <p id="totalCustomers">Loading...</p>
        </div>
        <div class="card">
          <h3>Drivers</h3>
          <p id="totalDrivers">Loading...</p>
        </div>
        <div class="card">
          <h3>Pending Orders</h3>
          <p id="pendingOrders">Loading...</p>
        </div>
      </div>
    </section>

    <section id="recentOrders">
      <h2>Recent Orders</h2>
      <!-- <ul id="orderList"> -->
      <!-- Dynamically populate this list with recent orders using JavaScript -->
      <!-- </ul> -->

      <table id="customer-table">
        <thead>
          <tr>
            <th>Order ID</th>
            <th>Order Date</th>
            <th>Total Amount (N)</th>
            <th>Status</th>
            <th>Delivery Status</th>
            <th>Assigned to</th>
            <th>Approved By</th>
            <th>Driver Name</th>
          </tr>
        </thead>
        <tbody>
          <!-- Orders will be dynamically inserted here -->
        </tbody>
      </table>

    </section>

    <section id="topMenuItems">

      <ul id="topMenuList">
        <h2>Top Menu Items</h2>
        <!-- Dynamically populate this list with top menu items using JavaScript -->
      </ul>
      
      <ul id="activeCustomers">
      <h2> Active Customers</h2>
        <!-- Dynamically populate this list with Active Customers who are Online using JavaScript -->
      </ul>
    </section>

  </main>

  <footer>
    <p>&copy; 2023 Food Vending Dashboard. All rights reserved.</p>
  </footer>

  <script src="scripts/dashboard.js"></script>


</body>

</html>