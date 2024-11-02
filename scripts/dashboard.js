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
    if (!data || typeof data !== 'object') {
      console.error('Invalid data format', data);
      return;
    }

    document.getElementById('totalOrders').textContent = data.totalOrders || 0;
    document.getElementById('totalCustomers').textContent = data.totalCustomers || 0;
    document.getElementById('totalRevenue').textContent = data.totalInflow || 0;
    document.getElementById('pendingOrders').textContent = data.pendingOrders || 0;
    document.getElementById('totalDrivers').textContent = data.totalDrivers || 0;

    const ordersTableBody = document.querySelector('#customer-table tbody');
    ordersTableBody.innerHTML = '';

    if (Array.isArray(data.recentOrders)) {
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
                  <td>${order.order_id}</td>
                  <td>${order.order_date || 'N/A'}</td>
                  <td>${order.total_amount || 'N/A'}</td>
                  <td style="color: ${statusColor}; padding: ${statusPadding}; font-weight: 900;">${order.status || 'Unknown'}</td>
                  <td>${order.delivery_status || 'Unknown'}</td>
                  <td>${order.admin_firstname} ${order.admin_lastname} - ${order.assigned_to}</td>
                  <td>${order.approver_firstname} ${order.approver_lastname} - ${order.approved_by}</td>
                  <td>${order.driver_firstname && order.driver_lastname ? `${order.driver_firstname} ${order.driver_lastname}` : 'No Driver Assigned'}</td>
              `;

        ordersTableBody.appendChild(row);
      });
    } else {
      console.warn('No recent orders found');
    }

    // Top Menu Items
    const topMenuItemsContainer = document.getElementById('topMenuList');
    topMenuItemsContainer.innerHTML = '';  // Clear previous items
    if (Array.isArray(data.topMenuItems)) {
      data.topMenuItems.forEach(item => {
        let itemElement = document.createElement('li');
        itemElement.textContent = `${item.food_name}`;
        topMenuItemsContainer.appendChild(itemElement);
      });
    }

    // Active Customers
    const activeCustomersContainer = document.getElementById('activeCustomers');
    activeCustomersContainer.innerHTML = '';  // Clear previous customers
    if (Array.isArray(data.activeCustomers) && data.activeCustomers.length > 0) {
      let header = document.createElement('h2');
      header.textContent = "Active Customers";
      activeCustomersContainer.appendChild(header);

      data.activeCustomers.forEach(item => {
        let itemElement = document.createElement('li');
        itemElement.textContent = `${item.firstname} ${item.lastname} - Online`;
        activeCustomersContainer.appendChild(itemElement);
      });
    } else {
      let header = document.createElement('h2');
      header.textContent = "Active Customers";
      let noCustomersElement = document.createElement('li');
      noCustomersElement.textContent = 'No Active Customers';
      activeCustomersContainer.appendChild(header);
      activeCustomersContainer.appendChild(noCustomersElement);
    }

  }, (error) => {
    console.error('Error fetching data:', error);
  });
});


// Function to update the dashboard with fetched data
function updateDashboard(data) {
  document.getElementById('totalOrders').textContent = data.totalOrders;
  document.getElementById('totalRevenue').textContent = data.totalInflow;
  document.getElementById('totalCustomers').textContent = data.totalCustomers;
  document.getElementById('pendingOrders').textContent = data.pendingOrders;
  document.getElementById('totalDrivers').textContent = data.totalDrivers;

  // const orderList = document.getElementById('orderList');
  // orderList.innerHTML = data.recentOrders.map(order => `<li>${order}</li>`).join('');

  // const topMenuList = document.getElementById('topMenuList');
  // topMenuList.innerHTML = data.topMenuItems.map(item => `<li>${item}</li>`).join('');
}

// Fetch and update data when the page loads
window.addEventListener('load', () => {
  fetchData('backend/fetch_data.php', updateDashboard);
});

// Refresh the page every 60 seconds
setInterval(() => {
  location.reload();
}, 120000); // 120000 milliseconds = 120 seconds
