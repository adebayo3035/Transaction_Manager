<!-- Updated dashboard.html with additional and modified styles -->

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Food Vending Dashboard</title>
  <link rel="stylesheet" href="styles.css"> <!-- Include your styles file -->
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      margin: 0;
      padding: 0;
      background-color: #f8f8f8;
    }

    header {
      background-color: #333;
      color: white;
      padding: 10px;
      text-align: center;
    }

    nav {
      background-color: #eee;
      padding: 10px;
      text-align: center;
    }

    nav a {
      margin: 0 10px;
      text-decoration: none;
      color: #333;
      font-weight: bold;
    }

    main {
      padding: 20px;
    }

    section {
      background-color: #fff;
      padding: 20px;
      margin-bottom: 20px;
      border-radius: 8px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    h2 {
      color: #333;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      grid-gap: 20px;
    }

    .card {
      background-color: #fff;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease;
      cursor: pointer;
    }

    .card:hover {
      transform: scale(1.05);
    }

    .card h3 {
      color: #555;
    }

    #orderList,
    #topMenuList {
      list-style: none;
      padding: 0;
    }

    #orderList li,
    #topMenuList li {
      margin-bottom: 10px;
    }

    footer {
      text-align: center;
      padding: 10px;
      background-color: #333;
      color: white;
    }
  </style>
</head>
<body>
<?php include "navbar.php" ?>

  <!-- ... (rest of the HTML content remains unchanged) ... -->
  
  <header>
    <h1>Food Vending Dashboard</h1>
  </header>

  <!-- <nav>
    <a href="dashboard.html">Dashboard</a>
    <a href="groups.html">Manage Groups</a>
    <a href="units.html">Manage Units</a>
    <a href="teams.html">Manage Teams</a>
    <a href="customers.html">Manage Customers</a>
    <a href="admin_onboarding.html">Manage Admins</a>
    <a href="transactions.html">Manage Transactions</a>
  </nav> -->

  <main>

    <section id="overview">
      <h2>Overview</h2>
      <div class="grid">
        <div class="card">
          <h3>Total Orders</h3>
          <p id="totalOrders">Loading...</p>
        </div>
        <div class="card">
          <h3>Revenue</h3>
          <p id="totalRevenue">Loading...</p>
        </div>
        <div class="card">
          <h3>Customers</h3>
          <p id="totalCustomers">Loading...</p>
        </div>
      </div>
    </section>

    <section id="recentOrders">
      <h2>Recent Orders</h2>
      <ul id="orderList">
        <!-- Dynamically populate this list with recent orders using JavaScript -->
      </ul>
    </section>

    <section id="topMenuItems">
      <h2>Top Menu Items</h2>
      <ul id="topMenuList">
        <!-- Dynamically populate this list with top menu items using JavaScript -->
      </ul>
    </section>

  </main>

  <footer>
    <p>&copy; 2023 Food Vending Dashboard. All rights reserved.</p>
  </footer>

  <script>
    // Simulate asynchronous data fetching
    function fetchData(url, callback) {
      setTimeout(() => {
        // Simulated data
        const data = {
          totalOrders: 250,
          totalRevenue: '$10,000',
          totalCustomers: 150,
          recentOrders: ['Order 1', 'Order 2', 'Order 3'],
          topMenuItems: ['Item 1', 'Item 2', 'Item 3'],
        };
        callback(data);
      }, 1000); // Simulating a delay for fetching data
    }

    // Function to update the dashboard with fetched data
    function updateDashboard(data) {
      document.getElementById('totalOrders').textContent = data.totalOrders;
      document.getElementById('totalRevenue').textContent = data.totalRevenue;
      document.getElementById('totalCustomers').textContent = data.totalCustomers;

      const orderList = document.getElementById('orderList');
      orderList.innerHTML = data.recentOrders.map(order => `<li>${order}</li>`).join('');

      const topMenuList = document.getElementById('topMenuList');
      topMenuList.innerHTML = data.topMenuItems.map(item => `<li>${item}</li>`).join('');
    }

    // Fetch and update data when the page loads
    window.addEventListener('load', () => {
      fetchData('example-api-endpoint', updateDashboard);
    });
  </script>

</body>
</html>
