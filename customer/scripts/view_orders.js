document.addEventListener('DOMContentLoaded', () => {
    // Configuration
    const CONFIG = {
        itemsPerPage: 10,
        minSpinnerTime: 1000,
        apiEndpoints: {
            fetchOrders: '../v2/fetch_order_summary.php',
            fetchOrderDetails: '../v2/fetch_order_details.php',
            cancelOrder: '../v2/cancel_order.php'
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
                body: document.querySelector('#orderDetailsTable tbody')
            }
        },
        pagination: document.getElementById('pagination'),
        search: document.getElementById('liveSearch'),
        modal: document.getElementById('orderModal'),
        buttons: {
            closeModal: document.querySelector('.modal .close'),
            decline: document.getElementById('decline-btn'),
            receipt: document.querySelector('#receipt-btn'),
            driverRating: document.querySelector('#driverRating-btn')
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
            container.innerHTML = `
                <tr>
                    <td colspan="6" class="error-message">${message}</td>
                </tr>
            `;
        },
        
        toggleModal: (show = true) => {
            elements.modal.style.display = show ? 'block' : 'none';
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
        
        fetchOrderDetails: (orderId) => {
            return fetch(CONFIG.apiEndpoints.fetchOrderDetails, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId })
            })
            .then(response => response.json());
        },
        
        cancelOrder: (orderId) => {
            return fetch(CONFIG.apiEndpoints.cancelOrder, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId, status: 'Cancelled' })
            })
            .then(response => response.json());
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
        
        displayOrderDetails: (orderDetails) => {
            const firstDetail = orderDetails[0];
            const { body } = elements.tables.orderDetails;
            body.innerHTML = '';
            
            // Order items
            orderDetails.forEach(detail => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${utils.formatDate(detail.order_date)}</td>
                    <td>${detail.order_id}</td>
                    <td>${detail.food_name}</td>
                    <td>${detail.quantity}</td>
                    <td>${utils.formatCurrency(detail.price_per_unit)}</td>
                    <td>${detail.status}</td>
                    <td>${utils.formatCurrency(detail.total_price)}</td>
                `;
                body.appendChild(row);
            });
            
            // Order summary
            const summaryRows = [
                { label: 'Total Order', value: utils.formatCurrency(firstDetail.total_order) },
                { label: 'Service Fee', value: utils.formatCurrency(firstDetail.service_fee) },
                { label: 'Delivery Fee', value: utils.formatCurrency(firstDetail.delivery_fee) },
                { label: 'Discount', value: utils.formatCurrency(firstDetail.discount) },
                { label: 'Total Amount', value: utils.formatCurrency(firstDetail.total_amount) },
                { label: 'Delivery Status', value: firstDetail.delivery_status },
                { label: 'Delivery Pin', value: firstDetail.delivery_pin || 'N/A' },
                { 
                    label: 'Driver\'s Name', 
                    value: firstDetail.driver_firstname && firstDetail.driver_lastname 
                        ? `${firstDetail.driver_firstname} ${firstDetail.driver_lastname}` 
                        : 'N/A'
                },
                { 
                    label: 'Driver\'s Phone No.', 
                    value: firstDetail.driver_phoneNumber || 'N/A' 
                },
                { 
                    label: 'Is Credit', 
                    value: firstDetail.is_credit == 1 ? 'Yes' : 'No' 
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
            
            // Show/hide action buttons based on status
            const showReceiptBtn = ['Delivered', 'Cancelled', 'Declined'].includes(firstDetail.delivery_status);
            const showRatingBtn = ['Delivered', 'Cancelled on Delivered'].includes(firstDetail.delivery_status);
            
            elements.buttons.receipt.style.display = showReceiptBtn ? 'block' : 'none';
            elements.buttons.driverRating.style.display = showRatingBtn ? 'block' : 'none';
            elements.buttons.decline.style.display = firstDetail.status === 'Pending' ? 'block' : 'none';
            
            if (elements.buttons.decline.style.display === 'block') {
                elements.buttons.decline.dataset.orderId = firstDetail.order_id;
            }
            
            utils.toggleModal();
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
                    if (data.success) {
                        ui.displayOrderDetails(data.order_details);
                    } else {
                        console.error('Failed to fetch order details:', data.message);
                    }
                })
                .catch(error => console.error('Error fetching order details:', error));
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
        elements.buttons.closeModal.addEventListener('click', () => {
            utils.toggleModal(false);
            location.reload();
        });
        
        // Outside click to close modal
        window.addEventListener('click', (event) => {
            if (event.target === elements.modal) {
                utils.toggleModal(false);
                location.reload();
            }
        });
        
        // Decline order button
        elements.buttons.decline.addEventListener('click', () => {
            order.cancelOrder(elements.buttons.decline.dataset.orderId);
        });
        
        // Print receipt button
        elements.buttons.receipt.addEventListener('click', ui.printReceipt);
        
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