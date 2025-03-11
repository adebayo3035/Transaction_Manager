document.addEventListener('DOMContentLoaded', () => {
    const limit = 10;
    let currentPage = 1;

    const ordersTableBody = document.querySelector('#orderSummaryTable tbody');
    const orderDetailsTableBody = document.querySelector('#orderDetailsTable tbody');
    const paginationContainer = document.getElementById('pagination');
    // const liveSearchInput = document.getElementById("liveSearch");
    const printButton = document.getElementById('receipt-btn');
    const orderModal = document.getElementById('orderModal');
    const orderDetailsTableBodyHeader = document.querySelector('#orderDetailsTable thead tr');
    orderDetailsTableBodyHeader.style.color = "#000";

    // Fetch Pending orders 
    fetch('../v2/fetch_pending_order.php')
        .then(response => response.json())
        .then(data => {
            // Assuming the structure has `pending_orders` inside the response
            const pending_orders = data.pending_orders;
            //Populate select input to select order for Update
            if (Array.isArray(pending_orders)) {
                const orderSelect = document.getElementById('order-id');

                pending_orders.forEach(item => {
                    let option = document.createElement('option');
                    option.value = item.order_id;
                    option.setAttribute('data-status', item.delivery_status);
                    option.text = `Order: ${item.order_id} - ${item.order_date} - ${item.delivery_status} - ${item.customer_id}`;
                    orderSelect.appendChild(option);
                });
            } else {
                console.error('Expected pending_orders to be an array.');
            }
        })
        .catch(error => console.error('Error fetching food items:', error));


    // Fetch orders with pagination
    function fetchOrders(page = 1) {
        fetch(`../v2/fetch_pending_order.php?page=${page}&limit=${limit}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.orders.length > 0) {
                    updateTable(data.orders);
                    updatePagination(data.total, data.page, data.limit);
                } else {
                    const ordersTableBody = document.querySelector('#orderSummaryTable tbody');
                    ordersTableBody.innerHTML = '';
                    const noOrderRow = document.createElement('tr');
                    noOrderRow.innerHTML = `<td colspan="7" style="text-align:center;">No Pending Orders at the moment</td>`;
                    ordersTableBody.appendChild(noOrderRow);
                    console.error('Failed to fetch orders here and there:', data.message);
                }
            })
            .catch(error => console.error('Error fetching data:', error));
    }

    // Update orders table
    function updateTable(orders) {
        ordersTableBody.innerHTML = '';
        const fragment = document.createDocumentFragment();

        orders.forEach(order => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${order.order_id}</td>
                <td>${order.order_date}</td>
                <td>${order.delivery_fee}</td>
                <td>${order.delivery_status}</td>
                <td><button class="view-details-btn" data-order-id="${order.order_id}">View Details</button></td>
            `;
            fragment.appendChild(row);
        });

        ordersTableBody.appendChild(fragment);
    }

    // Delegate event listener for view details buttons
    ordersTableBody.addEventListener('click', (event) => {
        if (event.target.classList.contains('view-details-btn')) {
            const orderId = event.target.getAttribute('data-order-id');
            fetchOrderDetails(orderId);
        }
    });

    // Fetch order details for a specific order
    function fetchOrderDetails(orderId) {
        fetch('../v2/fetch_pending_details.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    populateOrderDetails(data.order_details);
                    orderModal.style.display = 'block';
                } else {
                    console.error('Failed to fetch order details:', data.message);
                }
            })
            .catch(error => console.error('Error fetching order details:', error));
    }

    // Populate order details table
    function populateOrderDetails(details) {
        orderDetailsTableBody.innerHTML = '';
        const fragment = document.createDocumentFragment();

        details.forEach(detail => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${detail.order_date}</td>
                <td>${detail.food_name}</td>
                <td>${detail.quantity}</td>
                <td>${detail.status}</td>
            `;
            fragment.appendChild(row);
        });

        const firstDetail = details[0];

        fragment.appendChild(createRow('Date Last Modified', firstDetail.updated_at));
        fragment.appendChild(createRow('Delivery Fee', firstDetail.delivery_fee));
        fragment.appendChild(createRow("Customer's Name", `${firstDetail.customer_firstname} ${firstDetail.customer_lastname}`));
        fragment.appendChild(createRow("Customer's Mobile Number", firstDetail.customer_phone_number));
        fragment.appendChild(createRow('Delivery Status', firstDetail.delivery_status));

        if (firstDetail.driver_firstname && firstDetail.driver_lastname) {
            fragment.appendChild(createRow("Driver's Name", `${firstDetail.driver_firstname} ${firstDetail.driver_lastname}`));
        }

        orderDetailsTableBody.appendChild(fragment);

        if (firstDetail.delivery_status === "Delivered" || firstDetail.delivery_status === "Canceled") {
            printButton.style.display = "block";
        }
    }

    // Create a row for the details table
    function createRow(label, value) {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td colspan="3" style="text-align: left"><strong>${label}</strong></td>
            <td>${value}</td>
        `;
        return row;
    }

    // Update pagination
    function updatePagination(totalItems, currentPage, itemsPerPage) {
        paginationContainer.innerHTML = '';
        const totalPages = Math.ceil(totalItems / itemsPerPage);
        const fragment = document.createDocumentFragment();

        for (let page = 1; page <= totalPages; page++) {
            const pageButton = document.createElement('button');
            pageButton.textContent = page;
            pageButton.classList.add('page-btn', page === currentPage ? 'active' : '');
            pageButton.addEventListener('click', () => fetchOrders(page));
            fragment.appendChild(pageButton);
        }

        paginationContainer.appendChild(fragment);
    }

    // Close modal event
    document.querySelector('.modal .close').addEventListener('click', () => orderModal.style.display = 'none');
    window.addEventListener('click', (event) => {
        if (event.target === orderModal) {
            orderModal.style.display = 'none';
        }
    });

    // Handle printing receipt
    function printReceipt() {
        const orderDetails = orderDetailsTableBody.outerHTML;
        const now = new Date();
        const dateTime = now.toLocaleString();
        const receiptWindow = window.open('', '', 'width=800,height=600');

        receiptWindow.document.write(`
            <html><head><title>Receipt</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #333; }
                h2 { text-align: center; margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                @media print { body { padding: 10px; } table { font-size: 12px; } }
            </style></head><body>
            <h2>KaraKata Foods</h2>
            <h3>Order Details</h3>
            ${orderDetails}
            <br>Thank you for your Patronage <br/>Date and Time: ${dateTime}
            </body></html>
        `);

        receiptWindow.document.close();
        receiptWindow.print();
    }

    if (printButton) {
        printButton.addEventListener('click', printReceipt);
    }

    // Initial fetch
    fetchOrders(currentPage);
});
