<!-- Updated dashboard.html with additional and modified styles -->

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Food Vending Dashboard</title>
  <link rel="stylesheet" href="css/dashboard.css"> <!-- Include your styles file -->
  <link rel="stylesheet" href="css/navbar.css">
  <style>
    nav{
      /* border: 2px solid red; */

    }
    nav ul{
      display: flex;
      
    }
    nav ul li{
      list-style-type: none;
    }
    nav ul li a{
      padding: 10px 15px;
    }
    nav ul li a:hover {
    background-color: #555;
    color: #fff;
    border-radius: 8px;
  }
  </style>
</head>
<body>
<?php include "navbar.php" ?>
  <header>
    <h1>Food Vending Dashboard</h1>
  </header>
  <nav>
    <ul>
        <li><a href="dashboard.php" id="orderFood">Manage Orders</a></li>
        <li><a href="pending_orders.php" id="viewOrders">View Pending Orders</a></li>
        <li><a href="add_funds.php" id="fundWallet">Manage Revenue</a></li>
        
    </ul>
    
</nav>

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
    // function fetchData(url, callback) {
    //   setTimeout(() => {
    //     // Simulated data
    //     const data = {
    //       totalOrders: 250,
    //       totalRevenue: '$10,000',
    //       totalCustomers: 150,
    //       recentOrders: ['Order 1', 'Order 2', 'Order 3'],
    //       topMenuItems: ['Item 1', 'Item 2', 'Item 3'],
    //     };
    //     callback(data);
    //   }, 1000); // Simulating a delay for fetching data
    // }

    function fetchData(url, callback) {
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    callback(data);
                })
                .catch(error => console.error('Error fetching data:', error));
        }

        document.addEventListener('DOMContentLoaded', () => {
            fetchData('backend/fetch_data.php', (data) => {
                console.log(data); // Use the data as needed
                document.getElementById('totalOrders').textContent = data.totalOrders;
                document.getElementById('totalCustomers').textContent = data.totalCustomers;
                document.getElementById('totalRevenue').textContent = data.totalInflow;

                const recentOrdersContainer = document.getElementById('orderList');
                data.recentOrders.forEach(order => {
                    let orderElement = document.createElement('li');
                    orderElement.textContent = `${order.order_date} -  ${order.total_amount}`;
                    recentOrdersContainer.appendChild(orderElement);
                });

                const topMenuItemsContainer = document.getElementById('topMenuList');
                data.topMenuItems.forEach(item => {
                    let itemElement = document.createElement('li');
                    itemElement.textContent = `${item.food_name}`;
                    topMenuItemsContainer.appendChild(itemElement);
                });
            });
        });

    // Function to update the dashboard with fetched data
    function updateDashboard(data) {
      document.getElementById('totalOrders').textContent = data.totalOrders;
      document.getElementById('totalRevenue').textContent = data.totalInflow;
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
