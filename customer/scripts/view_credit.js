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
    const repayBtn = document.getElementById('repay-btn');
    const repaymentForm = document.getElementById('reassignForm');
    const submitRepayment = document.getElementById('submitRepayment');
    const repayAmountInput = document.getElementById('repayAmountInput');
    const repaymentMethodSelect = document.getElementById('repayment_method');
    const repayAmountLabel = document.getElementById('repayAmountLabel');

    // Fetch orders with pagination
    function fetchCredits(page = 1) {
        fetch(`../v2/fetch_credit_summary.php?page=${page}&limit=${limit}`)
            .then(response => response.json())
            .then(data => {
                // console.log(data);  // Check the returned data
                if (data.success) {
                    updateTable(data.credits);
                    updatePagination(data.total, data.page, data.limit);
                } else {
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
                <td>${credit.created_at}</td>
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

    // Fetch order details for a specific order
    function fetchCreditDetails(creditId) {
        fetch('../v2/fetch_credit_details.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ credit_id: creditId })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    populateCreditDetails(data.credit_details);
                    // Check if the order status is "Assigned"
                    const creditStatus = data.credit_details[0].repayment_status; // Assuming it's in the first detail
                    const repaymentButton = document.getElementById('repay-btn');
                    

                    if (creditStatus === "Pending" || creditStatus === "Partially Paid" ) {
                        // Enable the "Reassign Order" button if the status is "Assigned"
                        repaymentButton.style.display = 'block';
                        repaymentButton.disabled = false; // Ensure it's not disabled
                    } else {
                        // Disable or hide the button for other statuses
                        repaymentButton.style.display = 'none';
                        repaymentButton.disabled = true;
                    }
                    orderModal.style.display = 'block';
                } else {
                    console.error('Failed to fetch Credit details:', data.message);
                }
            })
            .catch(error => console.error('Error fetching credit details:', error));
    }
    let currentOrderId = null; // Variable to store the current order_id

    // Populate order details table
    function populateCreditDetails(details) {
        const orderDetailsTableBody = document.querySelector('#orderDetailsTable tbody');
        orderDetailsTableBody.innerHTML = '';
       details.forEach(detail => {
        currentOrderId = detail.order_id; // Store the order_id for later use
        document.getElementById('creditID').value = detail.credit_order_id;
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${detail.order_id}</td>
                 <td>${detail.total_credit_amount}</td>
                <td>${detail.amount_paid}</td>
                <td>${detail.remaining_balance}</td>
                <td>${detail.repayment_status}</td>
                
            `;
            orderDetailsTableBody.appendChild(row);
       })
            
        const fragment = document.createDocumentFragment();

        const firstDetail = details[0];
        const detailsArray = [
            { label: 'Credit ID', value: firstDetail.credit_order_id },
            { label: 'Order Date', value: firstDetail.created_at },
            { label: 'Due Date', value: firstDetail.due_date },
            { label: 'Credit Repayment Status', value: firstDetail.repayment_status },
           { label: 'Credit Due for Payment', value: firstDetail.is_due }
        ];

        detailsArray.forEach(detail => {
            const row = createRow(detail.label, detail.value);
            fragment.appendChild(row);
        });

        orderDetailsTableBody.appendChild(fragment);
    }

    // Create a row for the details table
    function createRow(label, value) {
        const row = document.createElement('tr');
        row.innerHTML = `<td colspan="4"><strong>${label}</strong></td><td>${value}</td>`;
        return row;
    }

    // REPAY CREDIT MODULE
    // Show the Repayment form when the button is clicked
    repayBtn.addEventListener('click', () => {
        // fetchAvailableDrivers();
        repaymentForm.style.display = 'block';
    });

    // Event listener for repayment method change
    repaymentMethodSelect.addEventListener('change', () => {
        const selectedMethod = repaymentMethodSelect.value;
        if (selectedMethod === "Full Repayment") {
            repayAmountInput.style.display = 'none';
            repayAmountLabel.style.display = 'none';
            submitRepayment.style.display = "flex";
        } else if (selectedMethod === "Partial Repayment") {
            repayAmountInput.style.display = 'block';
            repayAmountLabel.style.display = 'block';
            submitRepayment.style.display = "flex";
        }
        else{
            repayAmountInput.style.display = 'none';
            repayAmountLabel.style.display = 'none';
            submitRepayment.style.display = "none";
        }
    });
    // Handle form submission
    submitRepayment.addEventListener('click', () => {
        const selectedMethod = repaymentMethodSelect.value;
        const repayAmount = document.getElementById('repayAmountInput').value;
        const creditId = document.getElementById('creditID').value;
        
        // Show confirmation dialog
        const confirmMessage = selectedMethod === "Full Repayment" ? 
            "Are you sure you want to make a full repayment?" :
            `Are you sure you want to make a partial repayment of ${repayAmount}?`;

        if (confirm(confirmMessage)) {
            fetch('../v2/credit_repayment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ credit_order_id: creditId, repay_amount: selectedMethod === "Full Repayment" ? "Full" : repayAmount, repaymentMethod: selectedMethod, order_id: currentOrderId })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Your repayment has been successfully submitted');
                        // Hide form after successful submission
                        document.querySelector('.reassign-form').style.display = 'none';
                    } else {
                        alert(data.message);
                        console.error('Failed to repay credit order:', data.message);
                    }
                })
                .catch(error => console.error('Error repaying credit order:', error));
        }
    });


    // END OF REPAYMENT MODULE

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
        repaymentForm.style.display = 'none';
        location.reload();
    });
    document.querySelector('.closeRepaymentForm').addEventListener('click', ()=>{
        repaymentForm.style.display = 'none';
        location.reload();
    })
    window.addEventListener('click', (event) => {
        if (event.target === orderModal || event.target === repaymentForm) {
            orderModal.style.display = 'none';
            repaymentForm.style.display = 'none';
            location.reload();
        }
    });

    // Initial fetch
    fetchCredits(currentPage);
});
