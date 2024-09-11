document.addEventListener('DOMContentLoaded', () => {
    const limit = 6; // Number of items per page
    let currentPage = 1;

    function fetchOrders(page = 1) {
        fetch(`backend/fetch_order_summary.php?page=${page}&limit=${limit}`, {
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

    function updateTable(orders) {
        const ordersTableBody = document.querySelector('#ordersTable tbody');
        ordersTableBody.innerHTML = '';
        orders.forEach(order => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${order.order_id}</td>
                <td>${order.order_date}</td>
                <td>${order.customer_id}</td>
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

    function fetchOrderDetails(orderId) {
        fetch('backend/fetch_order_details.php', {
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
                    const orderID = document.getElementById('orderID');
                    orderDetailsTableBody.innerHTML = '';

                    data.order_details.forEach(detail => {
                        orderID.innerHTML = detail.order_id;
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${detail.food_id}</td>
                            <td>${detail.food_name}</td>
                            <td>${detail.quantity}</td>
                            <td>${detail.price_per_unit}</td>
                            <td>${detail.total_price}</td>
                            
                        `;
                        orderDetailsTableBody.appendChild(row);
                    });

                    // Assuming service_fee, delivery_fee, and total_order are the same for the entire order
                    const firstDetail = data.order_details[0];

                    // Add Total Order row
                    const totalOrderRow = document.createElement('tr');
                    totalOrderRow.innerHTML = `
                        <td colspan="4"><strong>Total Order</strong></td>
                        <td>${firstDetail.total_order}</td>
                    `;
                    orderDetailsTableBody.appendChild(totalOrderRow);

                    // Add Service Fee row
                    const serviceFeeRow = document.createElement('tr');
                    serviceFeeRow.innerHTML = `
                        <td colspan="4"><strong>Service Fee</strong></td>
                        <td>${firstDetail.service_fee}</td>
                    `;
                    orderDetailsTableBody.appendChild(serviceFeeRow);

                    // Add Delivery Fee row
                    const deliveryFeeRow = document.createElement('tr');
                    deliveryFeeRow.innerHTML = `
                        <td colspan="4"><strong>Delivery Fee</strong></td>
                        <td>${firstDetail.delivery_fee}</td>
                    `;
                    orderDetailsTableBody.appendChild(deliveryFeeRow);

                    // Add Delivery Fee row
                    const TotalAmountRow = document.createElement('tr');
                    TotalAmountRow.innerHTML = `
                        <td colspan="4"><strong>Total Amount</strong></td>
                        <td><h3 style="color: red;"># ${firstDetail.total_amount}<h3></td>
                     `;
                    orderDetailsTableBody.appendChild(TotalAmountRow);

                    //  Display Delivery Pin
                    const customerRow = document.createElement('tr');
                    customerRow.innerHTML = `
                        <td colspan="4"><strong>Customer ID</strong></td>
                        <td><h4>${firstDetail.customer_id}</h4></td>
                    `;
                    orderDetailsTableBody.appendChild(customerRow);

                    //  Display Date Order was Placed
                    const orderDateRow = document.createElement('tr');
                    orderDateRow.innerHTML = `
                        <td colspan="4"><strong>Order Date</strong></td>
                        <td><h4>${firstDetail.order_date}</h4></td>
                    `;
                    orderDetailsTableBody.appendChild(orderDateRow);

                    //  Display Date Order was Last Updated
                    const lastUpdatedRow = document.createElement('tr');
                    lastUpdatedRow.innerHTML = `
                        <td colspan="4"><strong>Date Last Updated</strong></td>
                        <td><h4>${firstDetail.updated_at}</h4></td>
                    `;
                    orderDetailsTableBody.appendChild(lastUpdatedRow);


                    //  Display Delivery Status
                    const StatusRow = document.createElement('tr');
                    StatusRow.innerHTML = `
                        <td colspan="4"><strong>Order Status</strong></td>
                        <td><h4>${firstDetail.status}</h4></td>
                    `;
                    orderDetailsTableBody.appendChild(StatusRow);

                    //  Display Delivery Status
                    const deliveryStatusRow = document.createElement('tr');
                    deliveryStatusRow.innerHTML = `
                        <td colspan="4"><strong>Delivery Status</strong></td>
                        <td><h4>${firstDetail.delivery_status}</h4></td>
                    `;
                    orderDetailsTableBody.appendChild(deliveryStatusRow);
                    // Display print button if delivery status is Delivered or Canceled
                    // const printButton = document.getElementById('receipt-btn');
                    // if(firstDetail.delivery_status === "Delivered" || firstDetail.delivery_status === "Canceled"){
                    //     printButton.style.display = "block";
                    // }

                    //  Display Delivery Pin
                    const driverDetailsRow = document.createElement('tr');
                    driverDetailsRow.innerHTML = `
                        <td colspan="4"><strong>Driver's Name</strong></td>
                        <td>${firstDetail.driver_firstname}  ${firstDetail.driver_lastname}</td>
                    `;
                    // if driver firstname and lastname is not null, append it to the table on the frontend
                    if(firstDetail.driver_firstname !== null && firstDetail.driver_lastname !== null){
                        orderDetailsTableBody.appendChild(driverDetailsRow);
                    }
                    
                    document.getElementById('orderModal').style.display = 'block';
                } else {
                    console.error('Failed to fetch order details:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching order details:', error);
            });


    }

    document.querySelector('.modal .close').addEventListener('click', () => {
        document.getElementById('orderModal').style.display = 'none';
    });

    window.addEventListener('click', (event) => {
        if (event.target === document.getElementById('orderModal')) {
            document.getElementById('orderModal').style.display = 'none';
        }
    });

    document.getElementById("liveSearch").addEventListener("input", filterTable);

    function filterTable() {
        var input = document.getElementById("liveSearch").value.toLowerCase();
        var rows = document.getElementById("ordersTable").getElementsByTagName("tr");

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

    // Fetch initial orders
    fetchOrders(currentPage);

    // Function to handle printing the receipt
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
            th, td, td h2 {
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
        receiptWindow.document.write('<br>')
        receiptWindow.document.write('Thank you for your Patronage <br/>');
        receiptWindow.document.write('Date and Time: ' + dateTime)
        receiptWindow.document.write('</body></html>');

        receiptWindow.document.close();
        receiptWindow.print();
    }


    // Assuming you have a button with ID 'printReceiptButton'
    const printButton = document.getElementById('receipt-btn');
    if (printButton) {
        printButton.addEventListener('click', printReceipt);
    }
});
