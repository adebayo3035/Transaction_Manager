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
        document.getElementById('pendingOrders').textContent = data.pendingOrders;

        const ordersTableBody = document.querySelector('#customer-table tbody');
        ordersTableBody.innerHTML = '';
        data.recentOrders.forEach(order => {
          const row = document.createElement('tr');
          let statusColor = '';
          let statusPadding = '5px';

          // Determine the color based on the status
          if (order.status === 'Pending') {
            statusColor = 'orange';
          } else if (order.status === 'Declined') {
            statusColor = 'red';
          } else if (order.status === 'Approved') {
            statusColor = 'green';
          }

          row.innerHTML = `
                <td>${order.order_date}</td>
                <td>${order.total_amount}</td>
                <td style="color: ${statusColor}; padding: ${statusPadding}; font-weight: 900;">${order.status}</td>
            `;

          ordersTableBody.appendChild(row);
        });

        const topMenuItemsContainer = document.getElementById('topMenuList');
        data.topMenuItems.forEach(item => {
          let itemElement = document.createElement('li');
          itemElement.textContent = `${item.food_name}`;
          topMenuItemsContainer.appendChild(itemElement);
        });

        const activeCustomersContainer = document.getElementById('activeCustomers');
        data.activeCustomers.forEach(item => {
          let itemElement = document.createElement('li');
          itemElement.textContent = `${item.firstname +" "+  item.lastname} - Online`;
          activeCustomersContainer.appendChild(itemElement);
        });
      });
    });

    // Function to update the dashboard with fetched data
    function updateDashboard(data) {
      document.getElementById('totalOrders').textContent = data.totalOrders;
      document.getElementById('totalRevenue').textContent = data.totalInflow;
      document.getElementById('totalCustomers').textContent = data.totalCustomers;
      document.getElementById('pendingOrders').textContent = data.pendingOrders;

      // const orderList = document.getElementById('orderList');
      // orderList.innerHTML = data.recentOrders.map(order => `<li>${order}</li>`).join('');

      // const topMenuList = document.getElementById('topMenuList');
      // topMenuList.innerHTML = data.topMenuItems.map(item => `<li>${item}</li>`).join('');
    }

    // Fetch and update data when the page loads
    window.addEventListener('load', () => {
      fetchData('backend/fetch_data.php', updateDashboard);
    });

    // function toggleNav() {
    //   var nav = document.querySelector("nav ul");
    //   if (nav.className === "nav-links") {
    //     nav.className += " responsive";
    //   } else {
    //     nav.className = "nav-links";
    //   }
    // }

