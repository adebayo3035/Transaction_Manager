document.addEventListener('DOMContentLoaded', () => {
    const limit = 10;
    let currentPage = 1;

    const ordersTableBody = document.querySelector('#ordersTable tbody');
    const orderDetailsTableBody = document.querySelector('#orderDetailsTable tbody');
    const paginationContainer = document.getElementById('pagination');
    const liveSearchInput = document.getElementById("liveSearch");
    const printButton = document.getElementById('receipt-btn');
    const orderModal = document.getElementById('orderModal');
    const packModal = document.getElementById('packModal');
    const orderDetailsTableBodyHeader = document.querySelector('#orderDetailsTable thead tr');
    orderDetailsTableBodyHeader.style.color = "#000";
    const reassignButton = document.getElementById('reassign-order');
    const reassignForm = document.getElementById('reassignForm');
    const submitReassign = document.getElementById('submitReassign');
    const driverSelect = document.getElementById('driver');
    const viewPackBtn = document.getElementById('view-pack');
    const cancelOrderBtn = document.getElementById('cancel-order');

    // Fetch orders with pagination
    function fetchOrders(page = 1) {
        const ordersTableBody = document.getElementById('ordersTableBody');

        // Inject spinner
        ordersTableBody.innerHTML = `
    <tr>
        <td colspan="6" style="text-align:center; padding: 20px;">
            <div class="spinner"
                style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 60%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: auto;">
            </div>
        </td>
    </tr>
    `;

        const minDelay = new Promise(resolve => setTimeout(resolve, 1000));

        // 🔹 Validate delivery_status input
        const deliveryStatusInput = document.getElementById('filterDeliveryStatus')?.value || "";
        const allowedStatuses = [
            "", "Pending", "Assigned", "In Transit", "Delivered", "Cancelled", "Declined", "Cancelled on Delivery"
        ];

        let deliveryStatus = "";
        if (allowedStatuses.includes(deliveryStatusInput)) {
            deliveryStatus = deliveryStatusInput;
        } else {
            console.warn("Invalid delivery status selected on frontend:", deliveryStatusInput);
            alert("Invalid delivery status filter. Please choose a valid option.");
            return; // 🚫 Stop fetch if invalid
        }

        // 🔹 Build query string
        const params = new URLSearchParams({ page, limit });
        if (deliveryStatus) {
            params.append('delivery_status', deliveryStatus);
        }

        const fetchData = fetch(`backend/fetch_order_summary.php?${params.toString()}`)
            .then(res => res.json());

        Promise.all([fetchData, minDelay])
            .then(([data]) => {
                if (data.success && data.orders.length > 0) {
                    updateTable(data.orders);
                    updatePagination(data.pagination.total, data.pagination.page, data.pagination.limit);
                } else {
                    ordersTableBody.innerHTML = `
                <tr><td colspan="6" style="text-align:center;">No Order at the moment</td></tr>
                `;
                    console.warn('No Order data:', data.message || "No records found");
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
    async function fetchOrderDetails(orderId) {
        fetch('backend/fetch_order_details.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId })
        })
            .then(response => response.json())
            .then(data => {
                console.log('API Response:', data); // Debug log
                if (data.success) {
                    const headerText = document.getElementById('orderID').textContent = orderId;

                    // Check if items array exists and is valid
                    if (!data.items || !Array.isArray(data.items)) {
                        console.error('Invalid items data:', data.items);
                        data.items = []; // Set to empty array to prevent errors
                    }

                    populateOrderDetails(data.order, data.items);

                    // Check if the order status is "Assigned"
                    const orderStatus = data.order.delivery_status;
                    viewPackBtn.dataset.orderId = data.order.order_id;
                    const reassignButton = document.getElementById('reassign-order');

                    if (orderStatus === "Assigned") {
                        reassignButton.style.display = 'block';
                        cancelOrderBtn.style.display = 'block';
                        reassignButton.disabled = false;
                        cancelOrderBtn.disabled = false;
                    } 
                    else if (orderStatus === "In Transit"){
                        cancelOrderBtn.style.display = 'block';
                        cancelOrderBtn.disabled = false;
                        reassignButton.style.display = 'none';
                        reassignButton.disabled = true;
                    }else {
                        reassignButton.style.display = 'none';
                        cancelOrderBtn.style.display = 'none';
                        reassignButton.disabled = true;
                        cancelOrderBtn.disabled = true;
                    }
                    orderModal.style.display = 'block';
                } else {
                    console.error('Failed to fetch order details:', data.message);
                }
            })
            .catch(error => console.error('Error fetching order details:', error));
    }

    // Populate order details table
    function populateOrderDetails(order, items) {
        orderDetailsTableBody.innerHTML = '';
        const fragment = document.createDocumentFragment();

        // Safely handle items array
        if (items && Array.isArray(items)) {
            items.forEach(item => {
                const row = document.createElement('tr');
                row.innerHTML = `
                <td>${item.food_id || ''}</td>
                <td>${item.food_name || ''}</td>
                <td>${item.quantity || ''}</td>
                <td>${item.price_per_unit || ''}</td>
                <td>${item.total_price || ''}</td>
            `;
                fragment.appendChild(row);
            });
        } else {
            console.warn('No items found or items is not an array');
            // Add a message row if no items
            const noItemsRow = document.createElement('tr');
            noItemsRow.innerHTML = `
            <td colspan="5" style="text-align: center; color: #999;">No food items found for this order</td>
        `;
            fragment.appendChild(noItemsRow);
        }

        // Add order metadata with safe property access
        fragment.appendChild(createRow('Delivery Status', order?.delivery_status || 'N/A'));
        fragment.appendChild(createRow('Date Last Modified', order?.updated_at || 'N/A'));
        fragment.appendChild(createRow('Total Order', order?.total_order || 'N/A'));
        fragment.appendChild(createRow('Service Fee', order?.service_fee || 'N/A'));
        fragment.appendChild(createRow('Delivery Fee', order?.delivery_fee || 'N/A'));
        fragment.appendChild(createRow('Number of Packs', order?.pack_count || 'N/A'));
        fragment.appendChild(createRow('Refunded Amount', order?.revenue?.refunded_amount || '0.00'));

        // Promotion details with safe access
        if (order?.promotion) {
            if (order.promotion.percentage_discount !== null && order.promotion.percentage_discount !== undefined) {
                fragment.appendChild(createRow('Percentage Discount (%)', order.promotion.percentage_discount));
            }
            if (order.promotion.discount_value !== null && order.promotion.discount_value !== undefined) {
                fragment.appendChild(createRow('Discount Value (N)', order.promotion.discount_value));
            }
            if (order.promotion.promo_code !== null && order.promotion.promo_code !== undefined) {
                fragment.appendChild(createRow('Promo Code', order.promotion.promo_code));
            }
        }

        fragment.appendChild(createRow('Balance', order?.revenue?.retained_amount || '0.00'));

        // Admin assignments with safe access
        if (order?.assigned_admin) {
            fragment.appendChild(createRow('Order Assigned To', `${order.assigned_admin.firstname || ''} ${order.assigned_admin.lastname || ''}`));
        }

        // Approval information with safe access
        if (order?.delivery_status === 'Cancelled' && !order?.approver) {
            fragment.appendChild(createRow('Order Approved By', 'Customer Cancelled Order'));
        }
        else if (order?.approver) {
            fragment.appendChild(createRow('Order Approved By', `${order.approver.firstname || ''} ${order.approver.lastname || ''}`));
        }

        // Customer information with safe access
        if (order?.customer) {
            fragment.appendChild(createRow("Customer's Name", `${order.customer.firstname || ''} ${order.customer.lastname || ''}`));
            fragment.appendChild(createRow("Customer's Mobile Number", order.customer.phone || 'N/A'));
            fragment.appendChild(createRow("Customer's Address", order.customer.address || 'N/A'));
        }

        // Driver information with safe access
        if (order?.driver) {
            fragment.appendChild(createRow("Driver's Name", `${order.driver.firstname || ''} ${order.driver.lastname || ''}`));
        }

        // Cancellation reason with safe access
        if (order?.delivery_status === "Cancelled") {
            fragment.appendChild(createRow("Reason for Cancellation", order.cancellation_reason || "No reason provided"));
        }

        orderDetailsTableBody.appendChild(fragment);

        // Show print button for completed/cancelled orders
        if (order?.delivery_status === "Delivered" || order?.delivery_status === "Cancelled") {
            printButton.style.display = "block";
        } else {
            printButton.style.display = "none";
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

    // function to Cancel Order In Assigned or In Transit Status
    cancelOrderBtn.addEventListener('click', () => {
         const orderId = document.getElementById('orderID').textContent;
        updateOrderStatus(orderId, 'Cancelled');
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

    // REASSIGN ORDER MODULE
    // Show the reassign form when the button is clicked
    reassignButton.addEventListener('click', () => {
        // fetchAvailableDrivers();
        reassignForm.style.display = 'block';
        fetchAvailableDrivers()
    });
    viewPackBtn.addEventListener('click', (event) => {
        const orderId = event.currentTarget.dataset.orderId;
        console.log("Order ID:", orderId);
        fetchPackDetails(orderId)
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
    document.querySelector('#packModal .close').addEventListener('click', () => {
        packModal.style.display = 'none'
    });
    // document.getElementById('applyOrderFilters').addEventListener('click', () => {
    //     fetchOrders(1); // Reset to page 1 when applying filters
    // });
    document.getElementById('filterDeliveryStatus').addEventListener('change', () => {
        fetchOrders(1); // Reset to page 1 when applying filters
    });

    window.addEventListener('click', (event) => {
        if (event.target === orderModal || event.target === reassignForm || event.target === packModal) {
            orderModal.style.display = 'none';
            reassignForm.style.display = 'none';
            packModal.style.display = "none";
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

    function fetchPackDetails(orderId) {
        fetch('customer/v2/fetch_pack_details.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId })
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('API Response:', data); // Debug log

                if (!data.success) {
                    console.error('API returned unsuccessful:', data.message);
                    return;
                }

                // ✅ Safely access nested data with optional chaining and null checks
                const packsData = data.data?.packs;
                const orderId = data.data?.order_id;

                if (!packsData || typeof packsData !== 'object') {
                    console.error('Invalid or missing packs data:', packsData);
                    return;
                }

                // ✅ Transform API response
                const transformedPackData = {
                    order_id: orderId,
                    packs: Object.keys(packsData).map(packId => {
                        const pack = packsData[packId];

                        // ✅ Additional safety checks
                        if (!pack || !pack.pack_info || !pack.items) {
                            console.warn('Invalid pack structure for packId:', packId, pack);
                            return null;
                        }

                        return {
                            pack_id: pack.pack_info.pack_id || packId,
                            total_cost: parseFloat(pack.pack_info.total_cost || 0),
                            created_at: pack.pack_info.created_at || new Date().toISOString(),
                            items: (pack.items || []).map(item => ({
                                food_id: item.food_id || 0,
                                food_name: item.food_name || 'Unknown Item',
                                quantity: parseInt(item.quantity || 0, 10),
                                price_per_unit: parseFloat(item.price_per_unit || 0),
                                total_price: parseFloat(item.total_price || 0)
                            })).filter(item => item.food_id !== 0) // Filter out invalid items
                        };
                    }).filter(pack => pack !== null) // Filter out invalid packs
                };

                console.log('Transformed Data:', transformedPackData); // Debug log

                // ✅ Render in UI
                if (transformedPackData.packs.length > 0) {
                    displayPackDetails(transformedPackData);
                    packModal.style.display = 'block';
                    orderModal.style.display = 'none';
                } else {
                    console.log('No valid packs found to display');
                }
            })
            .catch(error => {
                console.error('Error fetching pack order details:', error);
                // You might want to show a user-friendly error message here
            });
    }

    const displayPackDetails = (order) => {
        const container = document.getElementById("packDetails");
        container.innerHTML = "";

        // Add main header
        const mainHeader = document.createElement("div");
        mainHeader.className = "main-header";
        mainHeader.innerHTML = `
        <h1>📦 Order Packs Details</h1>
        <p class="order-id">Order #${order.order_id}</p>
    `;
        container.appendChild(mainHeader);

        order.packs.forEach((pack, index) => {
            // Pack card container
            const packCard = document.createElement("div");
            packCard.className = "pack-card";

            // Pack header with elegant design
            const packHeader = document.createElement("div");
            packHeader.className = "pack-card-header";
            packHeader.innerHTML = `
            <div class="pack-header-content">
                <div class="pack-icon">📦</div>
                <div class="pack-info">
                    <h3>Pack: ${pack.pack_id}</h3>
                    <p class="pack-date">Created: ${formatDate(pack.created_at)}</p>
                </div>
                <div class="pack-total">
                    <span class="total-label">Total</span>
                    <span class="total-amount">${formatCurrency(pack.total_cost)}</span>
                </div>
            </div>
        `;
            packCard.appendChild(packHeader);

            // Items table with modern design
            const tableContainer = document.createElement("div");
            tableContainer.className = "table-container";
            tableContainer.innerHTML = `
            <table class="pack-items-table">
                <thead>
                    <tr>
                        <th>Food Item</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${pack.items.map(item => `
                        <tr>
                            <td class="food-item">
                                <span class="food-name">${item.food_name}</span>
                            </td>
                            <td class="quantity">${item.quantity}</td>
                            <td class="unit-price">${formatCurrency(item.price_per_unit)}</td>
                            <td class="item-total">${formatCurrency(item.total_price)}</td>
                        </tr>
                    `).join("")}
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="pack-subtotal-label">Pack Subtotal</td>
                        <td class="pack-subtotal">${formatCurrency(pack.total_cost)}</td>
                    </tr>
                </tfoot>
            </table>
        `;
            packCard.appendChild(tableContainer);

            container.appendChild(packCard);
        });

        // Enhanced order summary
        const totalAmount = order.packs.reduce((sum, p) => sum + parseFloat(p.total_cost || 0), 0);

        const summary = document.createElement("div");
        summary.className = "order-summary-card";
        summary.innerHTML = `
        <div class="summary-header">
            <h3>💰 Order Summary</h3>
        </div>
        <div class="summary-content">
            <div class="summary-row">
                <span class="label">Total Packs:</span>
                <span class="value">${order.packs.length}</span>
            </div>
            <div class="summary-row">
                <span class="label">Total Items:</span>
                <span class="value">${order.packs.reduce((sum, p) => sum + p.items.length, 0)}</span>
            </div>
            <div class="summary-divider"></div>
            <div class="summary-row total-row">
                <span class="label">Grand Total:</span>
                <span class="value">${formatCurrency(totalAmount)}</span>
            </div>
        </div>
    `;
        container.appendChild(summary);
    };

    if (printButton) {
        printButton.addEventListener('click', printReceipt);
    }

    const formatDate = (dateString) => {
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(dateString).toLocaleDateString(undefined, options);
    }
    const formatCurrency = (amount) => {
        return parseFloat(amount).toFixed(2);
    }

    // Initial fetch
    fetchOrders(currentPage);
});
