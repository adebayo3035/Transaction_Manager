document.addEventListener('DOMContentLoaded', () => {
    const limit = 10; // Number of items per page
    let currentPage = 1;

    function fetchTransactions(page = 1) {
        fetch(`../v2/fetch_transaction_summary.php?page=${page}&limit=${limit}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.transactions.length > 0) {
                updateTable(data.transactions);
                updatePagination(data.total, data.page, data.limit);
            } else {
                const ordersTableBody = document.querySelector('#ordersTable tbody');
                ordersTableBody.innerHTML = '';
                const noOrderRow = document.createElement('tr');
                noOrderRow.innerHTML = `<td colspan="7" style="text-align:center;">No Transactions at the moment</td>`;
                ordersTableBody.appendChild(noOrderRow);
                console.error('Failed to fetch orders:', data.message);
            }
        })
        .catch(error => console.error('Error fetching data:', error));
    }

    function updateTable(transactions) {
        const ordersTableBody = document.querySelector('#ordersTable tbody');
        ordersTableBody.innerHTML = '';
        transactions.forEach(transaction => {
            const row = document.createElement('tr');
            row.setAttribute("row-transaction-id", transaction.id );
            row.add
            row.innerHTML = `
                <td>${transaction.id}</td>
                <td>${transaction.transaction_ref}</td>
                <td>${transaction.date_created}</td>
                <td>${transaction.amount}</td>
                <td>${transaction.transaction_type}</td>
                <td><button class="view-details-btn" data-transaction-id="${transaction.id}">View Details</button></td>
            `;
            ordersTableBody.appendChild(row);
        });

        // Attach event listeners to the view details buttons
        document.querySelectorAll('.view-details-btn').forEach(button => {
            button.addEventListener('click', (event) => {
                const transactionId = event.target.getAttribute('data-transaction-id');
                fetchTransactionDetails(transactionId);
            });
        });

        document.querySelectorAll('#ordersTable tbody tr').forEach(row => {
            row.addEventListener('click', (event) => {
                const transactionId = event.target.getAttribute('row-transaction-id');
                fetchTransactionDetails(transactionId);
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
                fetchTransactions(page);
            });
            paginationContainer.appendChild(pageButton);
        }
    }

    function fetchTransactionDetails(transactionId) {
        fetch('../v2/fetch_transactions_details.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ transaction_id: transactionId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const orderDetailsTableBody = document.querySelector('#orderDetailsTable tbody');
                orderDetailsTableBody.innerHTML = '';
                data.transaction_details.forEach(detail => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${detail.amount}</td>
                        <td>${detail.date_created}</td>
                        <td>${detail.transaction_type}</td>
                        <td>${detail.payment_method}</td>
                        <td colspan = "2">${detail.description}</td>
                    `;
                    orderDetailsTableBody.appendChild(row);
                });
                document.getElementById('orderModal').style.display = 'block';
            } else {
                console.error('Failed to fetch Transaction details:', data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching order details:', error);
        });
    }

    document.querySelector('.modal .close').addEventListener('click', () => {
        document.getElementById('orderModal').style.display = 'none';
        location.reload();
    });

    window.addEventListener('click', (event) => {
        if (event.target === document.getElementById('orderModal')) {
            document.getElementById('orderModal').style.display = 'none';
            location.reload();
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
    fetchTransactions(currentPage);
});
