document.addEventListener('DOMContentLoaded', () => {
    const limit = 10;
    let currentPage = 1;

    const ordersTableBody = document.querySelector('#ordersTable tbody');
    const orderDetailsTableBody = document.querySelector('#orderDetailsTable tbody');
    const paginationContainer = document.getElementById('pagination');
    const liveSearchInput = document.getElementById("liveSearch");
    const orderModal = document.getElementById('orderModal');
    const orderDetailsTableBodyHeader = document.querySelector('#orderDetailsTable thead tr');
    orderDetailsTableBodyHeader.style.color = "#000";


    // Fetch orders with pagination
    function fetchCredits(page = 1) {
        const ordersTableBody = document.getElementById('ordersTableBody');

        // Inject spinner
        ordersTableBody.innerHTML = `
        <tr>
            <td colspan="7" style="text-align:center; padding: 20px;">
                <div class="spinner"
                    style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db;
                           border-radius: 50%; width: 30px; height: 30px;
                           animation: spin 1s linear infinite; margin: auto;">
                </div>
            </td>
        </tr>
    `;

        const minDelay = new Promise(resolve => setTimeout(resolve, 1000)); // Ensures spinner visibility
        const fetchData = fetch(`backend/fetch_credit_summary.php?page=${page}&limit=${limit}`)
            .then(res => res.json());

        Promise.all([fetchData, minDelay])
            .then(([data]) => {
                if (data.success && data.data && data.data.credits.length > 0) {
                    updateTable(data.data.credits);
                    updatePagination(data.data.pagination.total, data.data.pagination.page, data.data.pagination.limit);
                } else {
                    ordersTableBody.innerHTML = `
                    <tr><td colspan="7" style="text-align:center;">No Credit History at the moment</td></tr>
                `;
                    console.warn('No Credit History:', data.message || 'Empty credit list');
                }
            })
            .catch(error => {
                console.error('Error fetching credit data:', error);
                ordersTableBody.innerHTML = `
                <tr><td colspan="7" style="text-align:center; color:red;">Error loading Credit History</td></tr>
            `;
            });
    }


    // Update orders table
    function updateTable(credits) {
        ordersTableBody.innerHTML = '';
        const fragment = document.createDocumentFragment();

        credits.forEach(credit => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${credit.order_id}</td>
                <td>${credit.credit_order_id}</td>
                <td>${credit.customer_id}</td>
                <td>${credit.total_credit_amount}</td>
                <td>${credit.remaining_balance}</td>

                <td>${credit.repayment_status}</td>
                <td><button class="view-details-btn" data-credit-id="${credit.credit_order_id}">View Details</button></td>
            `;
            fragment.appendChild(row);
        });

        ordersTableBody.appendChild(fragment);
    }

    // Delegate event listener for view details buttons
    ordersTableBody.addEventListener('click', (event) => {
        if (event.target.classList.contains('view-details-btn')) {
            const creditId = event.target.getAttribute('data-credit-id');
            fetchCreditDetails(creditId);
        }
    });

    // Fetch credit details and repayment history for a specific credit order
    function fetchCreditDetails(creditId, repPage = 1, repLimit = 5) {
        fetch('backend/fetch_credit_details.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                credit_id: creditId,
                page: repPage,
                limit: repLimit
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    populateCreditDetails(data.credit_details);
                    populateRepaymentHistory(data.repayment_history.records); // paginated data
                    setupRepaymentPagination(data.repayment_history.pagination, creditId); // new
                    document.getElementById('orderModal').style.display = 'block';
                } else {
                    console.error('Failed to fetch credit details:', data.message);
                }
            })
            .catch(error => console.error('Error fetching credit details:', error));
    }


    // Populate the credit details table
    function populateCreditDetails(details) {
        const orderDetailsTableBody = document.querySelector('#orderDetailsTable tbody');
        orderDetailsTableBody.innerHTML = '';
        const creditOrderId = document.getElementById('credit_order_id');
        creditOrderId.textContent = details.credit_order_id;

        const row = document.createElement('tr');
        row.innerHTML = `
        <td>${details.total_credit_amount}</td>
        <td>${details.amount_paid}</td>
        <td>${details.remaining_balance}</td>
        <td>${details.repayment_status}</td>
        <td>${details.due_date}</td>
    `;
        orderDetailsTableBody.appendChild(row);
    }

    // Populate the repayment history table
    function populateRepaymentHistory(historyData) {
        const tbody = document.querySelector('#repaymentHistoryTable tbody');
        tbody.innerHTML = '';

        if (!historyData || historyData.length === 0) {
            const row = document.createElement('tr');
            row.innerHTML = `<td colspan="4" style="text-align: center;">No repayment history available.</td>`;
            tbody.appendChild(row);
            return;
        }

        historyData.forEach(rep => {
            const row = document.createElement('tr');
            row.innerHTML = `
            <td>${rep.payment_date}</td>
            <td>${rep.amount_paid}</td>
        `;
            tbody.appendChild(row);
        });
    }

function setupRepaymentPagination(pagination, creditId) {
    const { total, page, limit } = pagination;
    const totalPages = Math.ceil(total / limit);
    const container = document.getElementById('repaymentPagination');
    container.innerHTML = '';

    const SPINNER_DELAY = 800; // milliseconds

    const createButton = (label, pageNum, isDisabled = false) => {
        const btn = document.createElement('button');
        btn.textContent = label;
        btn.disabled = isDisabled;
        btn.classList.toggle('disabled', isDisabled); // optional styling class
        btn.addEventListener('click', () => {
            if (!isDisabled) {
                showRepaymentSpinner();
                setTimeout(() => {
                    fetchCreditDetails(creditId, pageNum, limit);
                }, SPINNER_DELAY);
            }
        });
        container.appendChild(btn);
    };

    if (totalPages <= 1) return;

    const isFirstPage = page === 1;
    const isLastPage = page === totalPages;

    // First and Prev
    createButton('« First', 1, isFirstPage);
    createButton('‹ Prev', page - 1, isFirstPage);

    // Visible numbered pages (e.g., ±2 around current)
    const maxVisible = 2;
    const start = Math.max(1, page - maxVisible);
    const end = Math.min(totalPages, page + maxVisible);

    for (let i = start; i <= end; i++) {
        const btn = document.createElement('button');
        btn.textContent = i;
        if (i === page) btn.classList.add('active');
        btn.addEventListener('click', () => {
            showRepaymentSpinner();
            setTimeout(() => {
                fetchCreditDetails(creditId, i, limit);
            }, SPINNER_DELAY);
        });
        container.appendChild(btn);
    }

    // Next and Last
    createButton('Next ›', page + 1, isLastPage);
    createButton('Last »', totalPages, isLastPage);
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
            btn.addEventListener('click', () => fetchCredits(page));
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
            btn.addEventListener('click', () => fetchCredits(i));
            paginationButtons.push(btn);
            paginationContainer.appendChild(btn);
        }

        // Show: Next, Last
        createButton('Next ›', currentPage + 1, currentPage === totalPages);
        createButton('Last »', totalPages, currentPage === totalPages);
    }
    function showRepaymentSpinner() {
        const tbody = document.querySelector('#repaymentHistoryTable tbody');
        tbody.innerHTML = `
        <tr>
            <td colspan="2" style="text-align:center; padding: 20px;">
                <div class="spinner"
                    style="border: 3px solid rgba(0,0,0,0.1);
                           border-top: 3px solid #3498db;
                           border-radius: 50%;
                           width: 24px;
                           height: 24px;
                           animation: spin 0.8s linear infinite;
                           margin: 0 auto;">
                </div>
                <p style="margin-top: 8px; color: #666; font-size: 14px;">Loading repayment history...</p>
            </td>
        </tr>
    `;
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

        location.reload();
    });
    window.addEventListener('click', (event) => {
        if (event.target === orderModal) {
            orderModal.style.display = 'none';
            location.reload();
        }
    });

    // Initial fetch
    fetchCredits(currentPage);
});
