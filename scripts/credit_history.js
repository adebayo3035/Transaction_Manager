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
        fetch(`backend/fetch_credit_summary.php?page=${page}&limit=${limit}`)
            .then(response => response.json())
            .then(data => {
                // console.log(data);  // Check the returned data
                if (data.success && data.credits.length > 0) {
                    updateTable(data.credits);
                    updatePagination(data.total, data.page, data.limit);
                } else {
                    orderDetailsTableBody.innerHTML = '';
                    const noOrderRow = document.createElement('tr');
                    noOrderRow.innerHTML = `<td colspan="7" style="text-align:center;">No Credit History at the moment</td>`;
                    orderDetailsTableBody.appendChild(noOrderRow);
                    console.error('Failed to fetch orders:', data.message);
                }
            })
            .catch(error => console.error('Error fetching data:', error));
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
function fetchCreditDetails(creditId) {
    fetch('backend/fetch_credit_details.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ credit_id: creditId })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Populate the credit details
                populateCreditDetails(data.credit_details);
                
                // Populate repayment history
                populateRepaymentHistory(data.repayment_history);

                // Show the modal
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
function populateRepaymentHistory(history) {
    const repaymentHistoryTableBody = document.querySelector('#repaymentHistoryTable tbody');
    repaymentHistoryTableBody.innerHTML = '';

    if (history.length === 0) {
        // Handle no repayment history
        const row = document.createElement('tr');
        row.innerHTML = `<td colspan="4" style="text-align: center;">No repayment history available.</td>`;
        repaymentHistoryTableBody.appendChild(row);
        return;
    }

    // Populate repayment history rows
    history.forEach(rep => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${rep.payment_date}</td>
            <td>${rep.amount_paid}</td>
        `;
        repaymentHistoryTableBody.appendChild(row);
    });
}
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
       
        location.reload();
    });
    window.addEventListener('click', (event) => {
        if (event.target === orderModal ) {
            orderModal.style.display = 'none';
            location.reload();
        }
    });

    // Initial fetch
    fetchCredits(currentPage);
});
