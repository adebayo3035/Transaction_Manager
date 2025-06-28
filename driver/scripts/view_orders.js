document.addEventListener('DOMContentLoaded', () => {
    const limit = 10;
    let currentPage = 1;

    const ordersTableBody = document.querySelector('#orderSummaryTable tbody');
    const orderDetailsTableBody = document.querySelector('#orderDetailsTable tbody');
    const paginationContainer = document.getElementById('pagination');
    const liveSearchInput = document.getElementById("liveSearch");
    const printButton = document.getElementById('receipt-btn');
    const orderModal = document.getElementById('orderModal');
    const orderDetailsTableBodyHeader = document.querySelector('#orderDetailsTable thead tr');
    orderDetailsTableBodyHeader.style.color = "#000";

    // Fetch orders with pagination
     function fetchOrders(page = 1) {
        const ordersTableBody = document.getElementById('ordersTableBody');

        // Inject spinner
        ordersTableBody.innerHTML = `
        <tr>
            <td colspan="6" style="text-align:center; padding: 20px;">
                <div class="spinner"
                    style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: auto;">
                </div>
            </td>
        </tr>
        `;

        const minDelay = new Promise(resolve => setTimeout(resolve, 1000)); // Spinner shows at least 500ms
        const fetchData = fetch(`../v2/fetch_order_summary.php?page=${page}&limit=${limit}`)
            .then(res => res.json());

        Promise.all([fetchData, minDelay])
            .then(([data]) => {
                if (data.success && data.orders.length > 0) {
                    updateTable(data.orders);
                    updatePagination(data.total, data.page, data.limit);
                } else {
                    ordersTableBody.innerHTML = `
                    <tr><td colspan="6" style="text-align:center;">No Order History at the moment</td></tr>
                `;
                    console.error('No credit data:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching data:', error);
                ordersTableBody.innerHTML = `
                <tr><td colspan="6" style="text-align:center; color:red;">Error loading Order data</td></tr>
            `;
            });
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
        fetch('../v2/fetch_order_details.php', {
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
            <td colspan="3"><strong>${label}</strong></td>
            <td>${value}</td>
        `;
        return row;
    }

    // Update pagination
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
            btn.addEventListener('click', () => fetchOrders(page));
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
            btn.addEventListener('click', () => fetchOrders(i));
            paginationButtons.push(btn);
            paginationContainer.appendChild(btn);
        }

        // Show: Next, Last
        createButton('Next ›', currentPage + 1, currentPage === totalPages);
        createButton('Last »', totalPages, currentPage === totalPages);
    }

    document.getElementById("liveSearch").addEventListener("input", filterTable);

    function filterTable() {
        var input = document.getElementById("liveSearch").value.toLowerCase();
        var rows = document.getElementById("orderSummaryTable").getElementsByTagName("tr");

        for (var i = 1; i < rows.length; i++) {
            var cells = rows[i].getElementsByTagName("td");
            var found = false;
            for (var j = 0; j < cells.length; j++) {
                if (cells[j]) {
                    var cellText = cells[j].textContent.toLowerCase();
                    if (cellText.indexOf(input) > -1) {
                        found = true;
                        break;
                    }
                }
            }
            rows[i].style.display = found ? "" : "none";
        }
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
        const orderDetails = document.querySelector('#orderDetailsTable').outerHTML;
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
