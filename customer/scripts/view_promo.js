document.addEventListener('DOMContentLoaded', () => {
    const limit = 10;
    let currentPage = 1;

    // Fetch promos on page load
    fetchPromos(currentPage);

    // Fetch promos with async/await
    async function fetchPromos(page = 1) {
        try {
            const response = await fetch(`../v2/fetch_promo_summary.php?page=${page}&limit=${limit}`);
            const data = await response.json();
            if (data.success && data.promos.length > 0) {
                updateTable(data.promos);
                updatePagination(data.total, data.page, data.limit);
            } else {
                const ordersTableBody = document.querySelector('#ordersTable tbody');
                ordersTableBody.innerHTML = '';
                const noOrderRow = document.createElement('tr');
                noOrderRow.innerHTML = `<td colspan="7" style="text-align:center;">No Promo at the moment</td>`;
                ordersTableBody.appendChild(noOrderRow);
                // console.error('Failed to fetch Promos:', data.message);
            }
        } catch (error) {
            console.error('Error fetching data:', error);
        }
    }

    // Update table content
    function updateTable(promos) {
        const ordersTableBody = document.querySelector('#ordersTable tbody');
        ordersTableBody.innerHTML = '';
        const fragment = document.createDocumentFragment();

        promos.forEach(promo => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${promo.promo_code}</td>
                <td>${promo.promo_name}</td>
                <td>${promo.start_date}</td>
                <td>${promo.end_date}</td>
                <td>${promo.status}</td>
                <td><button class="view-details-btn" data-promo-id="${promo.promo_id}">View Details</button></td>
            `;
            fragment.appendChild(row);
        });

        ordersTableBody.appendChild(fragment);

        // Attach a single event listener for all view details buttons using event delegation
        ordersTableBody.addEventListener('click', event => {
            if (event.target.classList.contains('view-details-btn')) {
                const promoId = event.target.getAttribute('data-promo-id');
                fetchPromoDetails(promoId);
            }
        });
    }

    // Update pagination dynamically
    function updatePagination(totalItems, currentPage, itemsPerPage) {
        const paginationContainer = document.getElementById('pagination');
        paginationContainer.innerHTML = '';
        const totalPages = Math.ceil(totalItems / itemsPerPage);

        for (let page = 1; page <= totalPages; page++) {
            const pageButton = document.createElement('button');
            pageButton.textContent = page;
            pageButton.classList.add('page-btn');
            if (page === currentPage) pageButton.classList.add('active');

            pageButton.addEventListener('click', () => fetchPromos(page));
            paginationContainer.appendChild(pageButton);
        }
    }

    // Fetch and display promo details
    async function fetchPromoDetails(promoId) {
        try {
            const response = await fetch('../v2/fetch_promo_details.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ promo_id: promoId })
            });
            const data = await response.json();

            if (data.success) {
                displayPromoDetails(data.promo_details);
                document.getElementById('orderModal').style.display = 'block';
            } else {
                console.error('Failed to fetch promo details:', data.message);
            }
        } catch (error) {
            console.error('Error fetching promo details:', error);
        }
    }

    // Display promo details in the modal
    function displayPromoDetails(details) {
        const orderDetailsTableBody = document.querySelector('#orderDetailsTable tbody');
        orderDetailsTableBody.innerHTML = '';
        const fragment = document.createDocumentFragment();

        const firstDetail = details[0];
        const detailsArray = [
            { label: 'Promo Code', value: firstDetail.promo_code },
            { label: 'Promo Name', value: firstDetail.promo_name },
            { label: 'Description', value: firstDetail.promo_description },
            { label: 'Start Date', value: firstDetail.start_date },
            { label: 'End Date', value: firstDetail.end_date },
            { label: 'Percentage (%)', value: firstDetail.discount_value },
            { label: 'Minimum Order Required', value: firstDetail.min_order_value },
            { label: 'Max Discount Obtainable', value: firstDetail.max_discount },
            { label: 'Eligible Customers', value: firstDetail.eligibility_criteria }
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
    
    // Add the label and value to the row
    row.innerHTML = `<td colspan="4"><strong>${label}</strong></td><td>${value}</td>`;
    
    // Check if the label is "Promo Code"
    if (label === "Promo Code") {
        // Create a copy icon
        const copyIcon = document.createElement('i');
        copyIcon.className = 'fa-solid fa-copy'; // FontAwesome copy icon
        copyIcon.style.cursor = 'pointer';
        copyIcon.style.marginLeft = '10px';
        copyIcon.title = 'Click to Copy to Clipboard';

        // Add click event to copy promo code
        copyIcon.addEventListener('click', () => {
            navigator.clipboard.writeText(value).then(() => {
                alert(`Promo code "${value}" copied to clipboard!`);
            }).catch(err => {
                alert('Failed to copy promo code. Please try again.');
            });
        });

        // Append the copy icon to the value cell
        const valueCell = row.querySelector('td:last-child');
        valueCell.appendChild(copyIcon);
    }

    return row;
}


    // Close modal
    document.querySelector('.modal .close').addEventListener('click', () => {
        document.getElementById('orderModal').style.display = 'none';
    });

    window.addEventListener('click', event => {
        if (event.target === document.getElementById('orderModal')) {
            document.getElementById('orderModal').style.display = 'none';
        }
    });

    // Live search with debounce to improve performance
    document.getElementById("liveSearch").addEventListener("input", debounce(filterTable, 300));

    function filterTable() {
        const input = document.getElementById("liveSearch").value.toLowerCase();
        const rows = document.getElementById("ordersTable").getElementsByTagName("tr");

        for (let i = 1; i < rows.length; i++) {
            const cells = rows[i].getElementsByTagName("td");
            const found = Array.from(cells).some(cell => cell && cell.textContent.toLowerCase().includes(input));
            rows[i].style.display = found ? "" : "none";
        }
    }

    // Debounce function to limit the rate of execution for search
    function debounce(func, delay) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    }
});
