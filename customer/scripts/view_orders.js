document.addEventListener('DOMContentLoaded', () => {
    const limit = 6; // Number of items per page
    let currentPage = 1;

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

    function updateTable(orders) {
        const ordersTableBody = document.querySelector('#ordersTable tbody');
        ordersTableBody.innerHTML = '';
        orders.forEach(order => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${order.order_date}</td>
                <td>${order.total_amount}</td>
                <td>${order.status}</td>
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
                const orderDetailsTableBody = document.querySelector('#orderDetailsTable tbody');
                orderDetailsTableBody.innerHTML = '';
                data.order_details.forEach(detail => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${detail.food_name}</td>
                        <td>${detail.quantity}</td>
                        <td>${detail.price_per_unit}</td>
                        <td>${detail.total_price}</td>
                    `;
                    orderDetailsTableBody.appendChild(row);
                });
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
});
