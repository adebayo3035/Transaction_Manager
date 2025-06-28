document.addEventListener('DOMContentLoaded', () => {
    const limit = 10; // Number of items per page
    let currentPage = 1;

    function fetchTransactions(page = 1) {
        const ordersTableBody = document.querySelector('#ordersTable tbody');
        const loaderOverlay = document.getElementById('globalLoader');

        // Show global loader
        loaderOverlay.style.display = 'flex';

        // Disable pagination buttons
        paginationButtons.forEach(btn => btn.disabled = true);

        // Add spinner to table immediately
        ordersTableBody.innerHTML = `
        <tr>
            <td colspan="6" style="text-align:center; padding: 20px;">
                <div class="spinner"></div>
            </td>
        </tr>
    `;

        const minDelay = new Promise(resolve => setTimeout(resolve, 1000));
        const fetchData = fetch(`../v2/fetch_transaction_summary.php?page=${page}&limit=${limit}`, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
        }).then(res => res.json());

        Promise.all([fetchData, minDelay])
            .then(([data]) => {
                if (data.success && data.transactions.length > 0) {
                    updateTable(data.transactions);
                    updatePagination(data.total, data.page, data.limit);
                } else {
                    ordersTableBody.innerHTML = `
                    <tr><td colspan="6" style="text-align:center;">No Transactions at the moment</td></tr>
                `;
                }
            })
            .catch(error => {
                console.error('Error fetching data:', error);
                ordersTableBody.innerHTML = `
                <tr><td colspan="6" style="text-align:center; color:red;">Error loading transactions</td></tr>
            `;
            })
            .finally(() => {
                // Hide loader and re-enable buttons
                loaderOverlay.style.display = 'none';
                paginationButtons.forEach(btn => btn.disabled = false);
            });
    }


    function updateTable(transactions) {
        const ordersTableBody = document.querySelector('#ordersTable tbody');
        ordersTableBody.innerHTML = '';
        transactions.forEach(transaction => {
            const row = document.createElement('tr');
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
    }

    let paginationButtons = [];

    function updatePagination(totalItems, currentPage, itemsPerPage) {
    const paginationContainer = document.getElementById('pagination');
    paginationContainer.innerHTML = '';

    const totalPages = Math.ceil(totalItems / itemsPerPage);
    paginationButtons = [];

    const createButton = (label, page, disabled = false) => {
        const btn = document.createElement('button');
        btn.textContent = label;
        if (disabled) btn.disabled = true;
        btn.addEventListener('click', () => fetchTransactions(page));
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
        btn.addEventListener('click', () => fetchTransactions(i));
        paginationButtons.push(btn);
        paginationContainer.appendChild(btn);
    }

    // Show: Next, Last
    createButton('Next ›', currentPage + 1, currentPage === totalPages);
    createButton('Last »', totalPages, currentPage === totalPages);
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
