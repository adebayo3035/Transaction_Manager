document.addEventListener('DOMContentLoaded', () => {
    const limit = 10;
    let currentPage = 1;

    const revenueTableBody = document.querySelector('#revenueTable tbody');
    const revenueDetailsTableBody = document.querySelector('#revenueDetailsTable tbody');
    const paginationContainer = document.getElementById('pagination');
    const liveSearchInput = document.getElementById("liveSearch");
    const printButton = document.getElementById('receipt-btn');
    const revenueModal = document.getElementById('revenueModal');

    // Fetch revenue with pagination
    function fetchInflowandOutflow(page = 1) {
         const ordersTableBody = document.getElementById('ordersTableBody');

        // Inject spinner
        ordersTableBody.innerHTML = `
        <tr>
            <td colspan="8" style="text-align:center; padding: 20px;">
                <div class="spinner"
                    style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: auto;">
                </div>
            </td>
        </tr>
        `;

        const minDelay = new Promise(resolve => setTimeout(resolve, 1000)); // Spinner shows at least 500ms
        const fetchData = fetch(`backend/fetch_revenue.php?page=${page}&limit=${limit}`)
            .then(res => res.json());

        Promise.all([fetchData, minDelay])
            .then(([data]) => {
                if (data.success && data.revenues.length > 0) {
                    updateTable(data.revenues);
                    
                    updatePagination(data.total, data.page, data.limit);
                } else {
                    ordersTableBody.innerHTML = `
                    <tr><td colspan="8" style="text-align:center;">No Revenue Details at the moment</td></tr>
                `;
                    console.error('No Revenue data:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching data:', error);
                ordersTableBody.innerHTML = `
                <tr><td colspan="8" style="text-align:center; color:red;">Error loading Revenue data</td></tr>
            `;
            });
    }

    // Update revenue table
    function updateTable(revenues) {
        revenueTableBody.innerHTML = '';
        const fragment = document.createDocumentFragment();

        revenues.forEach(revenue => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${revenue.revenue_id}</td>
                <td>${revenue.order_id}</td>
                <td>${revenue.customer_id}</td>
                <td>${revenue.retained_amount}</td>
                <td>${revenue.status}</td>
                <td>${revenue.transaction_date}</td>
                <td>${revenue.updated_at}</td>
                <td><button class="view-details-btn" data-order-id="${revenue.order_id}">View Details</button></td>
            `;
            fragment.appendChild(row);
        });

        revenueTableBody.appendChild(fragment);
    }

    // Delegate event listener for view details buttons
    revenueTableBody.addEventListener('click', (event) => {
        if (event.target.classList.contains('view-details-btn')) {
            const revenueID = event.target.getAttribute('data-order-id');
            fetchRevenueDetails(revenueID);
        }
    });

    // Fetch revenue details for a specific revenue
    function fetchRevenueDetails(revenueID) {
        fetch('backend/fetch_revenue_details.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ revenueID: revenueID })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('revenueID').textContent = revenueID;
                    populateRevenueDetails(data.revenue_details);
                    revenueModal.style.display = 'block';
                } else {
                    console.error('Failed to fetch revenue details:', data.message);
                }
            })
            .catch(error => console.error('Error fetching revenue details:', error));
    }

    // Populate revenue details table
    function populateRevenueDetails(details) {
        revenueDetailsTableBody.innerHTML = '';
        const fragment = document.createDocumentFragment();

        for (const [key, value] of Object.entries(details)) {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><strong>${formatFieldName(key)}</strong></td>
                <td>${value}</td>
            `;
            fragment.appendChild(row);
        }

        revenueDetailsTableBody.appendChild(fragment);

        printButton.style.display = "block";
    }

    // Format field names to be more readable
    function formatFieldName(fieldName) {
        return fieldName.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    // Function to update pagination
    function updatePagination(totalItems, currentPage, itemsPerPage) {
        const paginationContainer = document.getElementById('pagination');
        paginationContainer.innerHTML = '';

        const totalPages = Math.ceil(totalItems / itemsPerPage);
        paginationButtons = [];

        const createButton = (label, page, disabled = false) => {
            const btn = document.createElement('button');
            btn.textContent = label;
            if (disabled) btn.disabled = true;
            btn.addEventListener('click', () => fetchInflowandOutflow(page));
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
            btn.addEventListener('click', () => fetchInflowandOutflow(i));
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
        const rows = revenueTableBody.getElementsByTagName("tr");

        Array.from(rows).forEach(row => {
            const cells = row.getElementsByTagName("td");
            const found = Array.from(cells).some(cell => cell.textContent.toLowerCase().includes(input));
            row.style.display = found ? "" : "none";
        });
    }

    // Close modal event
    document.querySelector('.modal .close').addEventListener('click', () => {
        revenueModal.style.display = 'none';
    });
    window.addEventListener('click', (event) => {
        if (event.target === revenueModal) {
            revenueModal.style.display = 'none';
        }
    });

    // Handle printing receipt
    function printReceipt() {
        const revenueDetails = document.querySelector('#revenueDetailsTable').outerHTML;
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
            <h3>Transaction Details</h3>
            ${revenueDetails}
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
    fetchInflowandOutflow(currentPage);
});
