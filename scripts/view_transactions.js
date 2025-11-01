document.addEventListener('DOMContentLoaded', () => {
    const limit = 10;
    let currentPage = 1;

    const transactionsTableBody = document.querySelector('.transactionsTable tbody');
    const transactionDetailsTableBody = document.querySelector('#transactionDetailsTable tbody');
    const paginationContainer = document.getElementById('pagination');
    const liveSearchInput = document.getElementById("liveSearch");
    const printButton = document.getElementById('receipt-btn');
    const transactionModal = document.getElementById('transactionModal');

    // Fetch transactions with pagination
    // Modified fetchStaffs function
   function fetchTransactions(page = 1) {
    const ordersTableBody = document.getElementById('ordersTableBody');

    // Inject spinner
    ordersTableBody.innerHTML = `
    <tr>
        <td colspan="7" style="text-align:center; padding: 20px;">
            <div class="spinner"
                style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: auto;">
            </div>
        </td>
    </tr>
    `;

    // Read filter dropdowns
    const typeFilter = document.getElementById('transactionTypeFilter')?.value || '';
    const statusFilter = document.getElementById('transactionStatusFilter')?.value || '';

    // Allowed values
    const allowedTypes = ["Credit", "Debit", "Others"];
    const allowedStatuses = ["Pending", "Completed", "Failed", "Declined"];

    // Build query params
    let queryParams = `page=${page}&limit=${limit}`;

    if (typeFilter && allowedTypes.includes(typeFilter)) {
        queryParams += `&transaction_type=${encodeURIComponent(typeFilter)}`;
    }

    if (statusFilter && allowedStatuses.includes(statusFilter)) {
        queryParams += `&status=${encodeURIComponent(statusFilter)}`;
    }

    const minDelay = new Promise(resolve => setTimeout(resolve, 1000)); // Spinner visible at least 1s
    const fetchData = fetch(`backend/fetch_transactions.php?${queryParams}`)
        .then(res => res.json());

    Promise.all([fetchData, minDelay])
        .then(([data]) => {
            if (data.success && data.transactions.length > 0) {
                updateTable(data.transactions);
                updatePagination(data.pagination.total, data.pagination.page, data.pagination.limit);
            } else {
                ordersTableBody.innerHTML = `
                <tr><td colspan="7" style="text-align:center;">No Transaction Details at the moment</td></tr>
                `;
                console.warn('No Transaction data:', data.message || 'Empty result');
            }
        })
        .catch(error => {
            console.error('Error fetching data:', error);
            ordersTableBody.innerHTML = `
            <tr><td colspan="7" style="text-align:center; color:red;">Error loading Transaction data</td></tr>
            `;
        });
}



    // Update transactions table
    function updateTable(transactions) {
        transactionsTableBody.innerHTML = '';
        const fragment = document.createDocumentFragment();

        transactions.forEach(transaction => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${transaction.transaction_ref}</td>
                <td>${transaction.transaction_type}</td>
                <td>${transaction.amount}</td>
                <td>${transaction.payment_method}</td>
                <td>${transaction.status}</td>
                <td>${transaction.transaction_date}</td>
                <td><button class="view-details-btn" data-transaction-ref="${transaction.transaction_ref}">View Details</button></td>
            `;
            fragment.appendChild(row);
        });

        transactionsTableBody.appendChild(fragment);
    }

    // Delegate event listener for view details buttons
    transactionsTableBody.addEventListener('click', (event) => {
        if (event.target.classList.contains('view-details-btn')) {
            const transactionRef = event.target.getAttribute('data-transaction-ref');
            fetchTransactionDetails(transactionRef);
        }
    });

    // Fetch transaction details for a specific transaction
    function fetchTransactionDetails(transactionRef) {
        fetch('backend/fetch_transaction_details.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ transaction_ref: transactionRef })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('transactionReference').textContent = transactionRef;
                    populateTransactionDetails(data.transaction_details);
                    transactionModal.style.display = 'block';
                } else {
                    console.error('Failed to fetch transaction details:', data.message);
                }
            })
            .catch(error => console.error('Error fetching transaction details:', error));
    }

    // Populate transaction details table
    function populateTransactionDetails(details) {
        transactionDetailsTableBody.innerHTML = '';
        const fragment = document.createDocumentFragment();

        for (const [key, value] of Object.entries(details)) {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><strong>${formatFieldName(key)}</strong></td>
                <td>${value}</td>
            `;
            fragment.appendChild(row);
        }

        transactionDetailsTableBody.appendChild(fragment);

        printButton.style.display = "block";
    }

    // Format field names to be more readable
    function formatFieldName(fieldName) {
        return fieldName.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

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
    // Debounced search filtering
    let searchTimeout;
    liveSearchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(filterTable, 300);
    });

    function filterTable() {
        const input = liveSearchInput.value.toLowerCase();
        const rows = transactionsTableBody.getElementsByTagName("tr");

        Array.from(rows).forEach(row => {
            const cells = row.getElementsByTagName("td");
            const found = Array.from(cells).some(cell => cell.textContent.toLowerCase().includes(input));
            row.style.display = found ? "" : "none";
        });
    }
    document.getElementById('applyTransactionFilters').addEventListener('click', () => {
      fetchTransactions(1); // Reset to page 1 when applying filters
    });
    // Close modal event
    document.querySelector('.modal .close').addEventListener('click', () => {
        transactionModal.style.display = 'none';
    });
    window.addEventListener('click', (event) => {
        if (event.target === transactionModal) {
            transactionModal.style.display = 'none';
        }
    });

    // Handle printing receipt
    function printReceipt() {
        const transactionDetails = document.querySelector('#transactionDetailsTable').outerHTML;
        const now = new Date();
        const dateTime = now.toLocaleString();
        const receiptWindow = window.open('', '', 'width=800,height=700');

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
            <h3>Transaction Details</h3>
            ${transactionDetails}
            <br>Thank you for your business.<br/>Date and Time: ${dateTime}
            </body></html>
        `);

        receiptWindow.document.close();
        receiptWindow.print();
    }

    if (printButton) {
        printButton.addEventListener('click', printReceipt);
    }

    // Initial fetch
    fetchTransactions(currentPage);
});
