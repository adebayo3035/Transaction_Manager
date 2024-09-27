document.addEventListener('DOMContentLoaded', () => {
    const limit = 6; // Number of items per page
    let currentPage = 1;

    // Fetch orders on page load
    fetchOrders(currentPage);

    // Function to fetch orders
    function fetchOrders(page = 1) {
        fetch(`../v2/fetch_order_summary.php?page=${page}&limit=${limit}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateTable(data.orders);
                updatePagination(data.total, data.page, data.limit);
            } else {
                console.error('Failed to fetch orders:', data.message);
            }
        })
        .catch(error => console.error('Error fetching data:', error));
    }

    // Function to update orders table
    function updateTable(orders) {
        const ordersTableBody = document.querySelector('#ordersTable tbody');
        ordersTableBody.innerHTML = '';
        orders.forEach(order => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${order.order_id}</td>
                <td>${order.order_date}</td>
                <td>${order.total_amount}</td>
                <td>${order.delivery_status}</td>
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
    }

    // Function to update pagination
    function updatePagination(totalItems, currentPage, itemsPerPage) {
        const paginationContainer = document.getElementById('pagination');
        paginationContainer.innerHTML = '';

        const totalPages = Math.ceil(totalItems / itemsPerPage);

        for (let page = 1; page <= totalPages; page++) {
            const pageButton = document.createElement('button');
            pageButton.textContent = page;
            pageButton.classList.add('page-btn');
            if (page === currentPage) {
                pageButton.classList.add('active');
            }
            pageButton.addEventListener('click', () => {
                fetchOrders(page);
            });
            paginationContainer.appendChild(pageButton);
        }
    }

    // Function to fetch and display order details
    function fetchOrderDetails(orderId) {
        fetch('../v2/fetch_order_details.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ order_id: orderId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayOrderDetails(data.order_details);
            } else {
                console.error('Failed to fetch order details:', data.message);
            }
        })
        .catch(error => console.error('Error fetching order details:', error));
    }

    // Function to display order details
    function displayOrderDetails(orderDetails) {
        const orderDetailsTableBody = document.querySelector('#orderDetailsTable tbody');
        orderDetailsTableBody.innerHTML = '';

        orderDetails.forEach(detail => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${detail.order_date}</td>
                 <td>${detail.order_id}</td>
                <td>${detail.food_name}</td>
                <td>${detail.quantity}</td>
                <td>${detail.price_per_unit}</td>
                <td>${detail.status}</td>
                <td>${detail.total_price}</td>
            `;
            orderDetailsTableBody.appendChild(row);
        });

        const firstDetail = orderDetails[0];

        // Append additional rows like Total, Service Fee, etc.
        appendAdditionalOrderDetails(firstDetail);

        // Display the modal
        document.getElementById('orderModal').style.display = 'block';

        // Update decline button with order ID
        const declineButton = document.getElementById('decline-btn');
        if (firstDetail.status === "Pending") {
            declineButton.style.display = "block";
            declineButton.dataset.orderId = firstDetail.order_id;
        } else {
            declineButton.style.display = "none";
        }

        // Ensure the Decline Order button is functional
        if (declineButton) {
            declineButton.addEventListener('click', () => {
                const orderId = declineButton.dataset.orderId;
                updateOrderStatus(orderId, 'Cancelled');
            });
        }
    }

    // Function to append additional details rows
    function appendAdditionalOrderDetails(firstDetail) {
        const orderDetailsTableBody = document.querySelector('#orderDetailsTable tbody');

        // Add Total Order row
        const totalOrderRow = document.createElement('tr');
        totalOrderRow.innerHTML = `
            <td colspan="6"><strong>Total Order</strong></td>
            <td>${firstDetail.total_order}</td>
        `;
        orderDetailsTableBody.appendChild(totalOrderRow);

        // Add Service Fee row
        const serviceFeeRow = document.createElement('tr');
        serviceFeeRow.innerHTML = `
            <td colspan="6"><strong>Service Fee</strong></td>
            <td>${firstDetail.service_fee}</td>
        `;
        orderDetailsTableBody.appendChild(serviceFeeRow);

        // Add Delivery Fee row
        const deliveryFeeRow = document.createElement('tr');
        deliveryFeeRow.innerHTML = `
            <td colspan="6"><strong>Delivery Fee</strong></td>
            <td>${firstDetail.delivery_fee}</td>
        `;
        orderDetailsTableBody.appendChild(deliveryFeeRow);

        // Add Total Amount row
        const totalAmountRow = document.createElement('tr');
        totalAmountRow.innerHTML = `
            <td colspan="6"><strong>Total Amount</strong></td>
            <td>${firstDetail.total_amount}</td>
        `;
        orderDetailsTableBody.appendChild(totalAmountRow);

        // Add Delivery Status row
        const deliveryStatusRow = document.createElement('tr');
        deliveryStatusRow.innerHTML = `
            <td colspan="6"><strong>Delivery Status</strong></td>
            <td>${firstDetail.delivery_status}</td>
        `;
        orderDetailsTableBody.appendChild(deliveryStatusRow);

        // Add Delivery Pin row
        const deliveryPinRow = document.createElement('tr');
        deliveryPinRow.innerHTML = `
            <td colspan="6"><strong>Delivery Pin</strong></td>
            <td>${firstDetail.delivery_pin}</td>
        `;
        orderDetailsTableBody.appendChild(deliveryPinRow);

        // Add Driver's Name row
        const driverNameRow = document.createElement('tr');
        if (firstDetail.driver_firstname == null || firstDetail.driver_lastname == null) {
            driverNameRow.innerHTML = `
                <td colspan="6"><strong>Driver's Name</strong></td>
                <td> N/A </td>
            `;
        } else {
            driverNameRow.innerHTML = `
                <td colspan="6"><strong>Driver's Name</strong></td>
                <td>${firstDetail.driver_firstname} ${firstDetail.driver_lastname}</td>
            `;
        }
        
        
        orderDetailsTableBody.appendChild(driverNameRow);

        // Add Driver's Name row
        const driverPhoneRow = document.createElement('tr');
        if (firstDetail.driver_phoneNumber == null) {
            driverPhoneRow.innerHTML = `
                <td colspan="6"><strong>Driver's Phone No.</strong></td>
                <td> N/A </td>
            `;
        } else {
            driverPhoneRow.innerHTML = `
                <td colspan="6"><strong>Driver's Phone No.</strong></td>
                <td>${firstDetail.driver_phoneNumber}</td>
            `;
        }
        
        orderDetailsTableBody.appendChild(driverPhoneRow);

        if(firstDetail.delivery_status == 'Delivered' || firstDetail.delivery_status == 'Cancelled' || firstDetail.delivery_status == 'Declined'){
            document.querySelector('#receipt-btn').style.display = "block";
        }


        
    }

    // Function to update order status
    function updateOrderStatus(orderId, status) {
        fetch('../v2/cancel_order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ order_id: orderId, status: status })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Your Order has been Successfully ' + status);
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

    // Handle modal close
    document.querySelector('.modal .close').addEventListener('click', () => {
        document.getElementById('orderModal').style.display = 'none';
    });

    window.addEventListener('click', (event) => {
        if (event.target === document.getElementById('orderModal')) {
            document.getElementById('orderModal').style.display = 'none';
        }
    });

    // Function to print the receipt
    function printReceipt() {
        const orderDetails = document.querySelector('#orderDetailsTable').outerHTML;
        const now = new Date();
        const dateTime = now.toLocaleString();
        const receiptWindow = window.open('', '', 'width=800,height=600');
        receiptWindow.document.write('<html><head><title>Receipt</title>');
        receiptWindow.document.write('<style>');
        receiptWindow.document.write(`
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 20px;
                color: #333;
            }
            h2 {
                text-align: center;
                margin-bottom: 20px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            th {
                background-color: #f2f2f2;
            }
            @media print {
                body {
                    padding: 10px;
                }
                table {
                    font-size: 12px;
                }
            }
        `);
        receiptWindow.document.write('</style></head><body>');
        receiptWindow.document.write('<h2>KaraKata Foods</h2>');
        receiptWindow.document.write('<h3>Order Details</h3>');
        receiptWindow.document.write(orderDetails);
        receiptWindow.document.write('<br>Thank you for your Patronage <br/>');
        receiptWindow.document.write('Date and Time: ' + dateTime);
        receiptWindow.document.write('</body></html>');

        receiptWindow.document.close();
        receiptWindow.print();
    }

    // Attach print button event listener
    document.querySelector('#receipt-btn').addEventListener('click', printReceipt);
});
