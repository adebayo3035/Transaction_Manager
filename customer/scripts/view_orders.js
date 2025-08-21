document.addEventListener('DOMContentLoaded', () => {
    // Configuration
    const CONFIG = {
        itemsPerPage: 10,
        minSpinnerTime: 1000,
        apiEndpoints: {
            fetchOrders: '../v2/fetch_order_summary.php',
            fetchOrderDetails: '../v2/fetch_order_details.php',
            cancelOrder: '../v2/cancel_order.php',
            fetchPackDetails: '../v2/fetch_pack_details.php'
        }
    };

    // State management
    const state = {
        currentPage: 1,
        currentOrderId: null
    };

    // DOM Elements
    const elements = {
        tables: {
            orders: {
                container: document.getElementById('ordersTableBody'),
                body: document.querySelector('#ordersTable tbody')
            },
            orderDetails: {
                container: document.getElementById('orderDetailsTable'),
                body: document.querySelector('#orderDetailsTable tbody')
            },
            packDetails: {
                container: document.getElementById('packDetails'),
                body: document.querySelector('#packDetails tbody')
            }
        },
        pagination: document.getElementById('pagination'),
        search: document.getElementById('liveSearch'),
        modal: document.getElementById('orderModal'),
        packModal: document.getElementById('packModal'),
        buttons: {
            closeModal: document.querySelector('.modal .close'),
            closePackModal: document.querySelector('.modal .closePackModal'),
            decline: document.getElementById('decline-btn'),
            getPackDetails: document.getElementById('pack-details'),
            receipt: document.querySelector('#receipt-btn'),
            driverRating: document.querySelector('#driverRating-btn')
        },
        messages: {
            alreadyRated: document.getElementById('alreadyRatedMessage')
        }
    };

    // Utility functions
    const utils = {
        showSpinner: (container) => {
            container.innerHTML = `
                <tr>
                    <td colspan="6" class="spinner-container">
                        <div class="spinner"></div>
                    </td>
                </tr>
            `;
        },

        showError: (container, message = 'Error loading data') => {
            container.innerHTML += `
                <tr>
                    <td colspan="6" class="error-message">${message}</td>
                </tr>
            `;
        },

        toggleModal: (modal, show = true) => {
            // Hide all modals first
            elements.modal.style.display = 'none';
            elements.packModal.style.display = 'none';

            // Show only the requested one
            if (show) {
                if (modal === 'order') elements.modal.style.display = 'block';
                if (modal === 'pack') elements.packModal.style.display = 'block';
            }
        },


        createButton: (label, page, disabled = false, active = false) => {
            const btn = document.createElement('button');
            btn.textContent = label;
            btn.disabled = disabled;
            if (active) btn.classList.add('active');
            return btn;
        },

        formatDate: (dateString) => {
            const options = { year: 'numeric', month: 'short', day: 'numeric' };
            return new Date(dateString).toLocaleDateString(undefined, options);
        },

        formatCurrency: (amount) => {
            return parseFloat(amount).toFixed(2);
        }
    };

    // API Functions
    const api = {
        fetchOrders: (page = 1) => {
            utils.showSpinner(elements.tables.orders.container);

            const minDelay = new Promise(resolve => setTimeout(resolve, CONFIG.minSpinnerTime));
            const fetchData = fetch(`${CONFIG.apiEndpoints.fetchOrders}?page=${page}&limit=${CONFIG.itemsPerPage}`)
                .then(res => res.json());

            Promise.all([fetchData, minDelay])
                .then(([data]) => {
                    if (data.success && data.orders.length > 0) {
                        ui.updateOrdersTable(data.orders);
                        ui.updatePagination(data.total, data.page, data.limit);
                    } else {
                        utils.showError(elements.tables.orders.container, 'No Order History at the moment');
                    }
                })
                .catch(() => {
                    utils.showError(elements.tables.orders.container, 'Error loading Order data');
                });
        },

        fetchOrderDetails: async (orderId) => {
            const response = await fetch(CONFIG.apiEndpoints.fetchOrderDetails, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId })
            });
            return await response.json();
        },

        fetchPackDetails: async (orderId) => {
            const response = await fetch(CONFIG.apiEndpoints.fetchPackDetails, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId })
            });
            return await response.json();
        },


        cancelOrder: async (orderId) => {
            const response = await fetch(CONFIG.apiEndpoints.cancelOrder, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId, status: 'Cancelled' })
            });
            return await response.json();
        }
    };

    // UI Functions
    const ui = {
        updateOrdersTable: (orders) => {
            elements.tables.orders.body.innerHTML = '';

            orders.forEach(order => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${order.order_id}</td>
                    <td>${utils.formatDate(order.order_date)}</td>
                    <td>${utils.formatCurrency(order.total_amount)}</td>
                    <td>${utils.formatCurrency(order.discount)}</td>
                    <td>${order.delivery_status}</td>
                    <td>
                        <button class="view-details-btn" data-order-id="${order.order_id}">
                            View Details
                        </button>
                    </td>
                `;
                elements.tables.orders.body.appendChild(row);
            });

            // Attach event listeners to view details buttons
            document.querySelectorAll('.view-details-btn').forEach(button => {
                button.addEventListener('click', (event) => {
                    state.currentOrderId = event.target.getAttribute('data-order-id');
                    order.loadOrderDetails(state.currentOrderId);
                });
            });
        },

        updatePagination: (totalItems, currentPage, itemsPerPage) => {
            elements.pagination.innerHTML = '';
            const totalPages = Math.ceil(totalItems / itemsPerPage);

            // First and Previous buttons
            elements.pagination.appendChild(utils.createButton('« First', 1, currentPage === 1));
            elements.pagination.appendChild(utils.createButton('‹ Prev', currentPage - 1, currentPage === 1));

            // Page numbers
            const maxVisible = 2;
            const start = Math.max(1, currentPage - maxVisible);
            const end = Math.min(totalPages, currentPage + maxVisible);

            for (let i = start; i <= end; i++) {
                const btn = utils.createButton(i, i, false, i === currentPage);
                btn.addEventListener('click', () => {
                    state.currentPage = i;
                    api.fetchOrders(i);
                });
                elements.pagination.appendChild(btn);
            }

            // Next and Last buttons
            const nextBtn = utils.createButton('Next ›', currentPage + 1, currentPage === totalPages);
            nextBtn.addEventListener('click', () => {
                state.currentPage = currentPage + 1;
                api.fetchOrders(currentPage + 1);
            });
            elements.pagination.appendChild(nextBtn);

            const lastBtn = utils.createButton('Last »', totalPages, currentPage === totalPages);
            lastBtn.addEventListener('click', () => {
                state.currentPage = totalPages;
                api.fetchOrders(totalPages);
            });
            elements.pagination.appendChild(lastBtn);
        },

        displayOrderDetails: (order) => {
            const { body } = elements.tables.orderDetails;
            body.innerHTML = '';
            // Order items
            order.order_details.forEach(detail => {
                const row = document.createElement('tr');
                row.innerHTML = `
            <td>${utils.formatDate(order.order_date)}</td>
            <td>${order.order_id}</td>
            <td>${detail.food_name}</td>
            <td>${detail.quantity}</td>
            <td>${utils.formatCurrency(detail.price_per_unit)}</td>
            <td>${order.status}</td>
            <td>${utils.formatCurrency(detail.total_price)}</td>
        `;
                body.appendChild(row);
            });

            // Assume these values exist at the top level in the response (you can adjust if nested):
            const summaryRows = [
                { label: 'Number of Packs Ordered', value: order.pack_count || '1' },
                { label: 'Total Order', value: utils.formatCurrency(order.total_order || 0) },
                { label: 'Service Fee', value: utils.formatCurrency(order.service_fee || 0) },
                { label: 'Delivery Fee', value: utils.formatCurrency(order.delivery_fee || 0) },
                { label: 'Discount', value: utils.formatCurrency(order.discount || 0) },
                { label: 'Total Amount', value: utils.formatCurrency(order.total_amount || 0) },
                { label: 'Delivery Status', value: order.delivery_status || 'N/A' },
                { label: 'Delivery Pin', value: order.delivery_pin || 'N/A' },

                {
                    label: 'Driver\'s Name',
                    value: order.driver_firstname && order.driver_lastname
                        ? `${order.driver_firstname} ${order.driver_lastname}`
                        : 'N/A'
                },
                {
                    label: 'Driver\'s Phone No.',
                    value: order.driver_phoneNumber || 'N/A'
                },
                {
                    label: 'Is Credit',
                    value: order.is_credit == 1 ? 'Yes' : 'No'
                }
            ];

            summaryRows.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
            <td colspan="6"><strong>${row.label}</strong></td>
            <td>${row.value}</td>
        `;
                body.appendChild(tr);
            });

            // Show/hide buttons and messages
            const showReceiptBtn = ['Delivered', 'Cancelled', 'Declined'].includes(order.delivery_status);
            const showRatingBtn = ['Delivered', 'Cancelled on Delivery'].includes(order.delivery_status) && !order.is_rated;
            const showAlreadyRated = ['Delivered', 'Cancelled on Delivery'].includes(order.delivery_status) && order.is_rated;

            elements.buttons.receipt.style.display = showReceiptBtn ? 'block' : 'none';
            elements.buttons.driverRating.style.display = showRatingBtn ? 'block' : 'none';
            elements.buttons.decline.style.display = order.status === 'Pending' ? 'block' : 'none';

            // Add this if you have an element to show "already rated" message
            if (elements.messages.alreadyRated) {
                elements.messages.alreadyRated.style.display = showAlreadyRated ? 'flex' : 'none';
            }

            if (elements.buttons.decline.style.display === 'block') {
                elements.buttons.decline.dataset.orderId = order.order_id;
            }

            if (elements.buttons.driverRating.style.display === 'block') {
                elements.buttons.driverRating.dataset.orderId = order.order_id;
            }
            elements.buttons.getPackDetails.dataset.orderId = order.order_id;

            utils.toggleModal('order', true);
        },

        displayPackDetails: (order) => {
            const container = document.getElementById("packDetails");
            container.innerHTML = '';

            // Create main container with beautiful styling
            container.className = 'pack-details-container';

            // Header section
            const header = document.createElement('div');
            header.className = 'pack-details-header';
            header.innerHTML = `
        <div class="header-content">
            <h1>🍽️ Your Meal Packs</h1>
            <p class="order-reference">Order #${order.order_id}</p>
            <div class="header-decoration">
                <div class="decoration-circle"></div>
                <div class="decoration-circle"></div>
                <div class="decoration-circle"></div>
            </div>
        </div>
    `;
            container.appendChild(header);

            // Packs grid
            const packsGrid = document.createElement('div');
            packsGrid.className = 'packs-grid';

            order.packs.forEach((pack, index) => {
                const packCard = document.createElement('div');
                packCard.className = 'pack-card';
                packCard.innerHTML = `
            <div class="pack-card-header">
                <div class="pack-number">Pack ${index + 1}</div>
                <div class="pack-id">#${pack.pack_id}</div>
            </div>
            
            <div class="pack-card-body">
                <div class="pack-info">
                    <div class="info-item">
                        <span class="info-label">🕐 Created</span>
                        <span class="info-value">${utils.formatDate(pack.created_at)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">💰 Total</span>
                        <span class="info-value total-amount">${utils.formatCurrency(pack.total_cost)}</span>
                    </div>
                </div>
                
                <div class="items-list">
                    <h4>📋 Items in this Pack</h4>
                    ${pack.items.map(item => `
                        <div class="food-item">
                            <span class="food-name">${item.food_name}</span>
                            <div class="food-details">
                                <span class="quantity">${item.quantity}x</span>
                                <span class="price">${utils.formatCurrency(item.price_per_unit)}</span>
                                <span class="total">${utils.formatCurrency(item.total_price)}</span>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
            
            <div class="pack-card-footer">
                <div class="pack-total">
                    <span>Pack Total:</span>
                    <strong>${utils.formatCurrency(pack.total_cost)}</strong>
                </div>
            </div>
        `;
                packsGrid.appendChild(packCard);
            });

            container.appendChild(packsGrid);

            // Order summary card
            const totalAmount = order.packs.reduce((sum, p) => sum + parseFloat(p.total_cost || 0), 0);
            const totalItems = order.packs.reduce((sum, p) => sum + p.items.length, 0);

            const summaryCard = document.createElement('div');
            summaryCard.className = 'summary-card';
            summaryCard.innerHTML = `
        <div class="summary-header">
            <h3>📊 Order Summary</h3>
            <div class="summary-icon">💰</div>
        </div>
        
        <div class="summary-content">
            <div class="summary-row">
                <div class="summary-label">
                    <span class="icon">📦</span>
                    Total Packs
                </div>
                <div class="summary-value">${order.packs.length}</div>
            </div>
            
            <div class="summary-row">
                <div class="summary-label">
                    <span class="icon">🍽️</span>
                    Total Items
                </div>
                <div class="summary-value">${totalItems}</div>
            </div>
            
            <div class="summary-divider"></div>
            
            <div class="summary-row grand-total">
                <div class="summary-label">
                    <span class="icon">🎯</span>
                    Grand Total
                </div>
                <div class="summary-value">${utils.formatCurrency(totalAmount)}</div>
            </div>
        </div>
        
        <div class="summary-footer">
            <p>Thank you for choosing KaraKata! 🎉</p>
        </div>
    `;
            container.appendChild(summaryCard);

            // Action buttons
    //         const actionBar = document.createElement('div');
    //         actionBar.className = 'action-bar';
    //         actionBar.innerHTML = `
    //     <button class="btn-primary" onclick="window.print()">
    //         🖨️ Print Receipt
    //     </button>
    //     <button class="btn-secondary" onclick="utils.toggleModal('pack', false)">
    //         ← Back to Orders
    //     </button>
    // `;
    //         container.appendChild(actionBar);
            utils.toggleModal("pack", true);
        },

        printReceipt: () => {
            const orderDetails = document.querySelector('#orderDetailsTable').outerHTML;
            const now = new Date();
            const receiptWindow = window.open('', '', 'width=800,height=600');

            receiptWindow.document.write(`
                <html>
                    <head>
                        <title>Receipt</title>
                        <style>
                            body {
                                font-family: Arial, sans-serif;
                                margin: 0;
                                padding: 20px;
                                color: #333;
                            }
                            h2, h3 {
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
                                body { padding: 10px; }
                                table { font-size: 12px; }
                            }
                        </style>
                    </head>
                    <body>
                        <h2>KaraKata Foods</h2>
                        <h3>Order Details</h3>
                        ${orderDetails}
                        <br>Thank you for your Patronage <br/>
                        <p>Date and Time: ${now.toLocaleString()}</p>
                    </body>
                </html>
            `);

            receiptWindow.document.close();
            receiptWindow.print();
        },

        filterTable: () => {
            const searchTerm = elements.search.value.toLowerCase();
            const rows = document.querySelectorAll("#ordersTable tbody tr");

            rows.forEach(row => {
                let matchFound = false;
                const cells = row.querySelectorAll("td");

                cells.forEach(cell => {
                    if (cell.textContent.toLowerCase().includes(searchTerm)) {
                        matchFound = true;
                    }
                });

                row.style.display = matchFound ? "" : "none";
            });
        }
    };

    // Order Management Functions
    const order = {
        loadOrderDetails: (orderId) => {
            api.fetchOrderDetails(orderId)
                .then(data => {
                    if (data.error) {
                        console.error('Failed to fetch order details:', data.error);
                        // Show error to user
                        ui.showError(data.error);
                        return;
                    }

                    // Transform backend response to match frontend expectations
                    const transformedData = {
                        order_id: data.order_id,
                        order_date: data.order_date,
                        status: data.status,
                        delivery_status: data.delivery_status,
                        service_fee: data.service_fee,
                        discount: data.discount,
                        total_order: data.total_order,
                        total_amount: data.total_amount,
                        delivery_pin: data.delivery_pin,
                        is_credit: data.is_credit,
                        driver_firstname: data.driver_firstname,
                        driver_lastname: data.driver_lastname,
                        driver_phoneNumber: data.driver_phoneNumber,
                        driverID: data.driverID,
                        driver_photo: data.driver_photo,
                        // Map all order info properties
                        ...data,
                        // Transform items array to match old structure
                        order_details: data.items.map(item => ({
                            food_name: item.food_name,
                            quantity: item.quantity,
                            price_per_unit: item.price_per_unit,
                            total_price: item.total_price,
                            food_id: item.food_id,
                            // Include other item properties if needed
                            ...item
                        }))
                    };

                    ui.displayOrderDetails(transformedData);
                })
                .catch(error => {
                    console.error('Error fetching order details:', error);
                    ui.showError('Failed to load order details. Please try again.');
                });
        },

        loadPackDetails: (orderId) => {
            api.fetchPackDetails(orderId)
                .then(response => {
                    if (!response.success) {
                        console.error('Failed to fetch Order food pack details:', response.message);
                        // ui.showError(response.message || 'Unknown error');
                        utils.showError(elements.tables.orderDetails.container, response.message);
                        return;
                    }

                    const data = response.data;

                    // Transform backend response to match frontend expectations
                    const transformedPackData = {
                        order_id: data.order_id,
                        packs: Object.keys(data.packs).map(packId => {
                            const pack = data.packs[packId];
                            return {
                                pack_id: pack.pack_info.pack_id,
                                total_cost: pack.pack_info.total_cost,
                                created_at: pack.pack_info.created_at,
                                items: pack.items.map(item => ({
                                    food_id: item.food_id,
                                    food_name: item.food_name,
                                    quantity: item.quantity,
                                    price_per_unit: item.price_per_unit,
                                    total_price: item.total_price

                                }))
                            };
                        })
                    };

                    // ✅ Pass the transformed structure to the UI renderer
                    ui.displayPackDetails(transformedPackData);
                })
                .catch(error => {
                    console.error('Error fetching order details:', error);
                    // ui.showError('Failed to load order details. Please try again.');
                    utils.showError(elements.tables.packDetails.container, 'Failed to load order details. Please try again.');
                });
        },


        cancelOrder: (orderId) => {
            const userConfirmed = confirm("Are you sure you want to cancel this order? This action cannot be undone.");

            if (userConfirmed) {
                api.cancelOrder(orderId)
                    .then(data => {
                        if (data.success) {
                            alert('Your Order has been Successfully Cancelled');
                            location.reload();
                        } else {
                            alert("Failed to Cancel Order: " + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error cancelling order:', error);
                    });
            }
        }
    };

    // Event Listeners
    const setupEventListeners = () => {
        // Modal close
        // elements.buttons.closeModal.addEventListener('click', () => {
        //     utils.toggleModal(false);
        //     location.reload();
        // });

        // // Outside click to close modal
        // window.addEventListener('click', (event) => {
        //     if (event.target === elements.modal) {
        //         utils.toggleModal(false);
        //         location.reload();
        //     }
        // });

        // Close buttons
        elements.buttons.closeModal.addEventListener('click', () => {
            utils.toggleModal('order', false);
            location.reload();
        });

        elements.buttons.closePackModal.addEventListener('click', () => {
            utils.toggleModal('pack', false);
            location.reload();
        });

        // Outside click to close modal
        window.addEventListener('click', (event) => {
            if (event.target === elements.modal) {
                utils.toggleModal('order', false);
                location.reload();
            }
            if (event.target === elements.packModal) {
                utils.toggleModal('pack', false);
                location.reload();
            }
        });


        // Decline order button
        elements.buttons.decline.addEventListener('click', () => {
            order.cancelOrder(elements.buttons.decline.dataset.orderId);
        });
        elements.buttons.getPackDetails.addEventListener('click', () => {
            order.loadPackDetails(elements.buttons.getPackDetails.dataset.orderId);
        })

        // Print receipt button
        elements.buttons.receipt.addEventListener('click', ui.printReceipt);

        // Order Ratings Button
        elements.buttons.driverRating.addEventListener('click', async () => {
            const orderId = elements.buttons.driverRating.dataset.orderId;

            try {
                // Fetch order details using the POST endpoint
                const response = await fetch(CONFIG.apiEndpoints.fetchOrderDetails, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        // 'Authorization': `Bearer ${getAuthToken()}` // If using authentication
                    },
                    body: JSON.stringify({
                        order_id: orderId
                    })
                });

                const data = await response.json();

                if (response.ok) {
                    // Transform the data to match what your rating page expects
                    const orderData = {
                        order_id: data.order_id,
                        driverID: data.driverID,
                        driver_firstname: data.driver_firstname,
                        driver_lastname: data.driver_lastname,
                        driver_photo: data.driver_photo,
                        // Include any other fields needed by the rating page
                        ...data
                    };

                    // Store in sessionStorage
                    sessionStorage.setItem('orderDetails', JSON.stringify(orderData));
                    window.location.href = '../v1/order_rating.php';
                } else {
                    console.error('Failed to fetch order details:', data.error);
                    alert(data.error || 'Failed to fetch order details');
                }
            } catch (err) {
                console.error('Fetch error:', err);
                alert('Failed to connect to server. Please try again.');
            }
        });


        // Live search
        elements.search.addEventListener('input', ui.filterTable);
    };

    // Initialize
    const init = () => {
        setupEventListeners();
        api.fetchOrders(state.currentPage);
    };

    init();
});