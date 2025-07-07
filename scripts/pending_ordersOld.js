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
            const ordersTableBody = document.querySelector('#customer-table tbody');
            const bulkApproveBtn = document.getElementById('bulk-approve-btn');
            const bulkDeclineBtn = document.getElementById('bulk-decline-btn');
            
            const selectAllContainer = document.getElementById('selectAllContainer');

            ordersTableBody.innerHTML = ''; // Clear table content

            if (data.success && data.orders.length > 0) {
                // Orders exist, populate table
                data.orders.forEach(order => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><input type="checkbox" class="order-select" name="order-select" value="${order.order_id}"></td>
                        <td>${order.order_id}</td>
                        <td>${order.customer_id}</td>
                        <td>${order.order_date}</td>
                        <td>${order.total_amount}</td>
                        <td>${order.status}</td>
                        <td>${order.assigned_admin_firstname} ${order.assigned_admin_lastname}</td>
                        <td><button class="view-details-btn" data-order-id="${order.order_id}">View Details</button></td>
                    `;
                    ordersTableBody.appendChild(row);
                });

                // Show bulk action buttons
                bulkApproveBtn.style.display = 'inline-block';
                bulkDeclineBtn.style.display = 'inline-block';
                selectAllContainer.style.display = 'flex';

                // Event listeners for bulk action buttons
                bulkApproveBtn.addEventListener('click', function () {
                    updateBulkOrderStatus('Approved');
                });

                bulkDeclineBtn.addEventListener('click', function () {
                    updateBulkOrderStatus('Declined');
                });

                // Attach event listeners to the view details buttons
                document.querySelectorAll('.view-details-btn').forEach(button => {
                    button.addEventListener('click', (event) => {
                        const orderId = event.target.getAttribute('data-order-id');
                        fetchOrderDetails(orderId);
                    });
                });

                // Add "Select All" logic after the table is populated
                const selectAllCheckbox = document.getElementById('select-all');
                const orderCheckboxes = document.querySelectorAll('.order-select');

                // Add event listener to the "Select All" checkbox
                selectAllCheckbox.addEventListener('change', function () {
                    
                    orderCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                        checkbox.disabled = this.checked; // Disable individual checkboxes if "Select All" is checked
                    });
                    console.log(this.checked ? "Select All checkboxes are checked and locked" : "Select All checkboxes are unchecked and unlocked");
                });

                // Add event listeners to individual checkboxes
                orderCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function () {
                        // If any checkbox is unchecked, uncheck the "Select All" checkbox
                        if (!this.checked) {
                            selectAllCheckbox.checked = false;
                            orderCheckboxes.forEach(checkbox => {
                                checkbox.disabled = false; // Re-enable all checkboxes
                            });
                            console.log("All checkboxes have been unchecked and unlocked");
                        } else {
                            // If all checkboxes are checked, check the "Select All" checkbox and disable individual checkboxes
                            const allChecked = Array.from(orderCheckboxes).every(checkbox => checkbox.checked);
                            if (allChecked) {
                                selectAllCheckbox.checked = true;
                                orderCheckboxes.forEach(checkbox => {
                                    checkbox.disabled = true; // Disable all checkboxes
                                });
                                console.log("All checkboxes are checked and locked");
                            }
                        }
                    });
                });

            } else {
                // No orders available, display message
                const noOrderRow = document.createElement('tr');
                noOrderRow.innerHTML = `<td colspan="7" style="text-align:center;">No Pending Orders at the moment</td>`;
                ordersTableBody.appendChild(noOrderRow);

                // Hide bulk action buttons
                bulkApproveBtn.style.display = 'none';
                bulkDeclineBtn.style.display = 'none';
                selectAllContainer.style.display = 'none';
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
                const orderDetailsLabel = document.getElementById('order_details');
                const orderDetailsTableBody = document.querySelector('#orderDetailsTable tbody');
                orderDetailsTableBody.innerHTML = '';
                data.order_details.forEach(detail => {
                    if (detail.is_credit) {
                        orderDetailsLabel.textContent = "Credit Order Details";
                    }
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
    if (confirm(`Are you sure you want to ${status} this order?`)) {
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
                alert('Order has been successfully ' + status);
                location.reload();
            } else {
                console.error('Failed to update order status:', data.message);
                alert("Failed to Update Customer Order: " + data.message);
            }
        })
        .catch(error => {
            console.error('Error updating order status:', error);
        });
    }
}

// Bulk order confirmation
function updateBulkOrderStatus(status) {
    const selectedOrders = [];
    document.querySelectorAll('input[name="order-select"]:checked').forEach((checkbox) => {
        selectedOrders.push({ order_id: checkbox.value, status: status });
    });

    if (selectedOrders.length === 0) {
        alert('Please select at least one order.');
        return;
    }

    if (confirm(`Are you sure you want to update ${selectedOrders.length} orders to "${status}"?`)) {
        fetch('backend/update_order_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ orders: selectedOrders })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Order statuses updated to "${status}" successfully`);
                location.reload();
            } else {
                alert('Failed to update orders: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error updating bulk orders:', error);
        });
    }
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
