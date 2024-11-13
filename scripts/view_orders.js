document.addEventListener('DOMContentLoaded', () => {
    const limit = 6;
    let currentPage = 1;

    const ordersTableBody = document.querySelector('#ordersTable tbody');
    const orderDetailsTableBody = document.querySelector('#orderDetailsTable tbody');
    const paginationContainer = document.getElementById('pagination');
    const liveSearchInput = document.getElementById("liveSearch");
    const printButton = document.getElementById('receipt-btn');
    const orderModal = document.getElementById('orderModal');
    const orderDetailsTableBodyHeader = document.querySelector('#orderDetailsTable thead tr');
    orderDetailsTableBodyHeader.style.color = "#000";
    const reassignButton = document.getElementById('reassign-order');
    const reassignForm = document.getElementById('reassignForm');
    const submitReassign = document.getElementById('submitReassign');
    const driverSelect = document.getElementById('driver');

    // Fetch orders with pagination
    function fetchOrders(page = 1) {
        fetch(`backend/fetch_order_summary.php?page=${page}&limit=${limit}`)
            .then(response => response.json())
            .then(data => {
                // console.log(data);  // Check the returned data
                if (data.success) {
                    updateTable(data.orders);
                    updatePagination(data.total, data.page, data.limit);
                } else {
                    console.error('Failed to fetch orders:', data.message);
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
                <td>${order.customer_id}</td>
                <td>${order.total_amount}</td>
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
        fetch('backend/fetch_order_details.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const headerText = document.getElementById('orderID').textContent = orderId;
                    populateOrderDetails(data.order_details);
                    // Check if the order status is "Assigned"
                    const orderStatus = data.order_details[0].delivery_status; // Assuming it's in the first detail
                    const reassignButton = document.getElementById('reassign-order');

                    if (orderStatus === "Assigned") {
                        // Enable the "Reassign Order" button if the status is "Assigned"
                        reassignButton.style.display = 'block';
                        reassignButton.disabled = false; // Ensure it's not disabled
                    } else {
                        // Disable or hide the button for other statuses
                        reassignButton.style.display = 'none';
                        reassignButton.disabled = true;
                    }
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
                <td>${detail.food_id}</td>
                <td>${detail.food_name}</td>
                <td>${detail.quantity}</td>
                <td>${detail.price_per_unit}</td>
                <td>${detail.total_price}</td>
            `;
            fragment.appendChild(row);
        });

        const firstDetail = details[0];
        

        fragment.appendChild(createRow('Date Last Modified', firstDetail.updated_at));
        fragment.appendChild(createRow('Total Order', firstDetail.total_order));
        fragment.appendChild(createRow('Service Fee', firstDetail.service_fee));
        fragment.appendChild(createRow('Delivery Fee', firstDetail.delivery_fee));
       
        if(firstDetail.percentage_discount !== null){
            fragment.appendChild(createRow('Percentage Discount (%)', firstDetail.percentage_discount));
        }
        if(firstDetail.discount_value !== null){
            fragment.appendChild(createRow('Discount Value (N)', firstDetail.discount_value));
        }
        fragment.appendChild(createRow('Total Amount', firstDetail.total_amount));
        if(firstDetail.promo_code !== null){
            fragment.appendChild(createRow('Promo Code', firstDetail.promo_code));
        }
        if(firstDetail.assigned_admin_firstname !== null && firstDetail.assigned_admin_lastname !== null){
            fragment.appendChild(createRow('Order Assigned To', `${firstDetail.assigned_admin_firstname} ${firstDetail.assigned_admin_lastname}`));
        }
        
        if(firstDetail.delivery_status == 'Cancelled' && firstDetail.approver_firstname == null && firstDetail.approver_lastname == null){
            fragment.appendChild(createRow('Order Approved By', `Customer Cancelled Order`));
        }
        else if(firstDetail.approver_firstname !== null && firstDetail.approver_lastname !== null){
            fragment.appendChild(createRow('Order Approved By', `${firstDetail.approver_firstname} ${firstDetail.approver_lastname}`));
        }
        fragment.appendChild(createRow("Customer's Name", `${firstDetail.customer_firstname} ${firstDetail.customer_lastname}`));
        fragment.appendChild(createRow("Customer's Mobile Number", firstDetail.customer_phone_number));
        fragment.appendChild(createRow('Delivery Status', firstDetail.delivery_status));

        if (firstDetail.driver_firstname && firstDetail.driver_lastname) {
            fragment.appendChild(createRow("Driver's Name", `${firstDetail.driver_firstname} ${firstDetail.driver_lastname}`));
        }
        if (firstDetail.delivery_status === "Cancelled") {
            fragment.appendChild(createRow("Reason for Cancellation", `${firstDetail.cancellation_reason}`));
        }

        orderDetailsTableBody.appendChild(fragment);

        if (firstDetail.delivery_status === "Delivered" || firstDetail.delivery_status === "Cancelled") {
            printButton.style.display = "block";
        }
    }

    // Create a row for the details table
    function createRow(label, value) {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td colspan="4"><strong>${label}</strong></td>
            <td>${value}</td>
        `;
        return row;
    }

    // REASSIGN ORDER MODULE
    // Show the reassign form when the button is clicked
    reassignButton.addEventListener('click', () => {
        // fetchAvailableDrivers();
        reassignForm.style.display = 'block';
        fetchAvailableDrivers()
    });

    // Fetch available drivers and populate the dropdown
    function fetchAvailableDrivers() {
        fetch('backend/fetch_available_drivers.php')
            .then(response => response.json())
            .then(data => {
                driverSelect.innerHTML = ''; // Clear existing options
                if (data.success) {
                    data.drivers.forEach(driver => {
                        const option = document.createElement('option');
                        option.value = driver.driver_id;
                        option.textContent = `${driver.driver_name} (" - "ID: ${driver.driver_id})`;
                        driverSelect.appendChild(option);
                    });
                    
                } else {
                    console.error('Failed to fetch drivers:', data.message);
                }
            })
            .catch(error => console.error('Error fetching drivers:', error));
    }

    // Handle form submission
    submitReassign.addEventListener('click', () => {
        const selectedDriver = driverSelect.value;
        const orderId = document.getElementById('orderID').textContent;

        if (selectedDriver === "") {
            alert("Please Select a Valid Driver for Order " + orderId)
            return
        }

        fetch('backend/reassign_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId, driver_id: selectedDriver })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Order reassigned successfully');
                    reassignForm.style.display = 'none'; // Hide form after submission
                } else {
                    alert(data.message)
                    console.error('Failed to reassign order:', data.message);
                }
            })
            .catch(error => console.error('Error reassigning order:', error));
    });


    // END OF REASSIGN MODULE

    // Update pagination
    function updatePagination(totalItems, currentPage, itemsPerPage) {
        console.log("Total Items:", totalItems, "Current Page:", currentPage, "Items Per Page:", itemsPerPage);  // Debugging pagination data
        paginationContainer.innerHTML = '';
        const totalPages = Math.ceil(totalItems / itemsPerPage);
        const fragment = document.createDocumentFragment();

        for (let page = 1; page <= totalPages; page++) {
            const pageButton = document.createElement('button');
            pageButton.textContent = page;
            pageButton.classList.add('page-btn');

            // Only add 'active' class if the page is the current page
            if (page === currentPage) {
                pageButton.classList.add('active');
            }

            pageButton.addEventListener('click', () => fetchOrders(page));
            fragment.appendChild(pageButton);
        }

        paginationContainer.appendChild(fragment);
    }

    // Debounced search filtering
    let searchTimeout;
    liveSearchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(filterTable, 300);
    });

    function filterTable() {
        const input = liveSearchInput.value.toLowerCase();
        const rows = ordersTableBody.getElementsByTagName("tr");

        Array.from(rows).forEach(row => {
            const cells = row.getElementsByTagName("td");
            const found = Array.from(cells).some(cell => cell.textContent.toLowerCase().includes(input));
            row.style.display = found ? "" : "none";
        });
    }

    // Close modal event
    document.querySelector('.modal .close').addEventListener('click', () => {
        orderModal.style.display = 'none'
        reassignForm.style.display = 'none';
    });
    window.addEventListener('click', (event) => {
        if (event.target === orderModal || event.target === reassignForm) {
            orderModal.style.display = 'none';
            reassignForm.style.display = 'none';
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
