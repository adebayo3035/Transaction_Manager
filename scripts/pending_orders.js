function toggleModal(modalId) {
    let modal = document.getElementById(modalId);
    modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";
}
document.addEventListener('DOMContentLoaded', () => {
    const limit = 10; // Number of items per page
    let currentPage = 1;

    // Modified fetchStaffs function
    function fetchPendingOrders(page = 1) {
        const ordersTableBody = document.getElementById('ordersTableBody');

        // Inject spinner
        ordersTableBody.innerHTML = `
        <tr>
            <td colspan="8" style="text-align:center; padding: 20px;">
                <div class="spinner"
                    style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: auto;">
                </div>
            </td>
        </tr>
        `;

        const minDelay = new Promise(resolve => setTimeout(resolve, 1000)); // Spinner shows at least 500ms
        const fetchData = fetch(`backend/fetch_pending_order.php?page=${page}&limit=${limit}`)
            .then(res => res.json());

        Promise.all([fetchData, minDelay])
            .then(([data]) => {
                if (data.success && data.orders.length > 0) {
                    updateTable(data.orders);
                    updatePagination(data.total, data.page, data.limit);
                } else {
                    ordersTableBody.innerHTML = `
                    <tr><td colspan="8" style="text-align:center;">No Pending Order at the moment</td></tr>
                `;
                    console.error('No Pending Order data:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching Pending Orders:', error);
                ordersTableBody.innerHTML = `
                <tr><td colspan="8" style="text-align:center; color:red;">Error loading Pending Orders</td></tr>
            `;
            });
    }
    // On initial page load
    document.addEventListener('DOMContentLoaded', () => {
        // You might need to fetch the user role first or pass it from server
        fetchPendingOrders(1);
    });

    function updateTable(orders) {
        const ordersTableBody = document.querySelector('#ordersTable tbody');
        const bulkApproveBtn = document.getElementById('bulk-approve-btn');
        const bulkDeclineBtn = document.getElementById('bulk-decline-btn');
        const selectAllContainer = document.getElementById('selectAllContainer');
        ordersTableBody.innerHTML = '';

        orders.forEach(order => {
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
    }

    // Function to update pagination
    function updatePagination(totalItems, currentPage, itemsPerPage) {
        const paginationContainer = document.getElementById('pagination');
        paginationContainer.innerHTML = '';

        const totalPages = Math.ceil(totalItems / itemsPerPage);
        paginationButtons = [];

        const createButton = (label, page, disabled = false) => {
            const btn = document.createElement('button');
            btn.textContent = label;
            if (disabled) btn.disabled = true;
            btn.addEventListener('click', () => fetchPendingOrders(page));
            paginationContainer.appendChild(btn);
        };

        // Show: First, Prev
        createButton('« First', 1, currentPage === 1);
        createButton('‹ Prev', currentPage - 1, currentPage === 1);

        // Show range around current page (e.g. ±2)
        const maxVisible = 2;
        const start = Math.max(1, currentPage - maxVisible);
        const end = Math.min(totalPages, currentPage + maxVisible);

        for (let i = start; i <= end; i++) {
            const btn = document.createElement('button');
            btn.textContent = i;
            if (i === currentPage) btn.classList.add('active');
            btn.addEventListener('click', () => fetchPendingOrders(i));
            paginationButtons.push(btn);
            paginationContainer.appendChild(btn);
        }

        // Show: Next, Last
        createButton('Next ›', currentPage + 1, currentPage === totalPages);
        createButton('Last »', totalPages, currentPage === totalPages);
    }
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

    document.getElementById("liveSearch").addEventListener("input", filterTable);

    function filterTable() {
        const searchTerm = document.getElementById("liveSearch").value.toLowerCase();
        const rows = document.querySelectorAll("#ordersTable tbody tr");

        rows.forEach(row => {
            const cells = row.getElementsByTagName("td");
            let matchFound = false;

            for (let i = 0; i < cells.length; i++) {
                const cellText = cells[i].textContent.toLowerCase();
                if (cellText.includes(searchTerm)) {
                    matchFound = true;
                    break;
                }
            }

            row.style.display = matchFound ? "" : "none";
        });
    }

    // Fetch initial drivers data
    fetchPendingOrders(currentPage);
    
});
