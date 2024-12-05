
document.addEventListener('DOMContentLoaded', () => {
    // Fetch and display orders summary
    fetch('backend/fetch_pending_order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const ordersTableBody = document.querySelector('#customer-table tbody');
                ordersTableBody.innerHTML = '';
                data.orders.forEach(order => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                            <td>${order.order_id}</td>
                            <td>${order.order_date}</td>
                            <td>${order.total_amount}</td>
                            <td>${order.status}</td>
                            <td>${order.assigned_admin_firstname} ${order.assigned_admin_lastname}</td>
                            <td><button class="view-details-btn" data-order-id="${order.order_id}">View Details</button></td>
                        `;
                    ordersTableBody.appendChild(row);
                });

                // Attach event listeners to the view details buttons
                document.querySelectorAll('.view-details-btn').forEach(button => {
                    button.addEventListener('click', (event) => {
                        const orderId = event.target.getAttribute('data-order-id');
                        fetchOrderDetails(orderId);
                    });
                });
            } else {
                console.error('Failed to fetch orders:', data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching orders:', error);
        });
});

function fetchOrderDetails(orderId) {
    fetch('backend/fetch_pending_order_details.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ order_id: orderId })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const orderDetailsTableBody = document.querySelector('#orderDetailsTable tbody');
                orderDetailsTableBody.innerHTML = '';
                data.order_details.forEach(detail => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                            <td>${detail.food_name}</td>
                            <td>${detail.quantity}</td>
                            <td>${detail.price_per_unit}</td>
                            <td>${detail.total_price}</td>
                        `;
                    orderDetailsTableBody.appendChild(row);
                });

                // Store the current order ID for later use
                document.getElementById('approveButton').dataset.orderId = orderId;
                document.getElementById('declineButton').dataset.orderId = orderId;

                // Display the modal
                document.getElementById('orderModal').style.display = 'block';
            } else {
                console.error('Failed to fetch order details:', data.message);
                alert('Failed to fetch Order Details', data.message)
            }
        })
        .catch(error => {
            console.error('Error fetching order details:', error);
        });
}

// Approve order
document.getElementById('approveButton').addEventListener('click', () => {
    const orderId = document.getElementById('approveButton').dataset.orderId;
    updateOrderStatus(orderId, 'Approved');
});

// Decline order
document.getElementById('declineButton').addEventListener('click', () => {
    const orderId = document.getElementById('declineButton').dataset.orderId;
    updateOrderStatus(orderId, 'Declined');
});

function updateOrderStatus(orderId, status) {
    fetch('backend/update_order_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ order_id: orderId, status: status })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                    alert('Order has been successfully ' + status + "");
                location.reload(); // Refresh the page to reflect changes
            } else {
                console.error('Failed to update order status:', data.message);
                alert("Failed to Update Customer Order: " + data.message);
            }
        })
        .catch(error => {
            console.error('Error updating order status:', error);
        });
}

// Close the modal when the close button is clicked
document.querySelector('.modal .close').addEventListener('click', () => {
    document.getElementById('orderModal').style.display = 'none';
});

// Close the modal when clicking outside of the modal content
window.addEventListener('click', (event) => {
    if (event.target === document.getElementById('orderModal')) {
        document.getElementById('orderModal').style.display = 'none';
    }
});
