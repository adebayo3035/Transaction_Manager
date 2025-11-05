function toggleModal(modalId) {
    let modal = document.getElementById(modalId);
    modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";
}
document.addEventListener('DOMContentLoaded', () => {
    const limit = 10; // Number of items per page
    let currentPage = 1;
    loadSuperAdmins()

    // Function to fetch basic list
function fetchDeactivationList(page = 1) {
    currentPage = page;
    toggleLoader(true);
    toggleTable(false);

    // Get filter values
    const deactivator = document.getElementById('deactivator').value;
    const reactivator = document.getElementById('reactivator').value;
    const deactivationStatus = document.getElementById('deactivationStatus').value;

    // Build query string with filters
    const params = new URLSearchParams({
        page: page,
        limit: limit,
        ...(deactivator && { deactivator: deactivator }),
        ...(reactivator && { reactivator: reactivator }),
        ...(deactivationStatus && { deactivationStatus: deactivationStatus })
    });

    fetch(`backend/get_deactivation_reactivation_list.php?${params}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            setTimeout(() => {
                if (data.deletedStaff.length === 0) {
                    const tableBody = document.querySelector('#ordersTable tbody');
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="9" style="text-align: center; font-style: italic;">No Record Found</td>
                        </tr>
                    `;
                    togglePagination(false);
                } else {
                    updateTable(data.deletedStaff);
                    updatePagination(data.total, data.page, data.limit);
                }
                
                // Populate dropdowns with Super Admins if returned
                if (data.super_admins) {
                    populateAdminDropdowns(data.super_admins);
                }
                
                toggleLoader(false);
                toggleTable(true);
            }, 1000);
        } else {
            toggleLoader(false);
            showErrorModal(`Failed to fetch records: ${data.error || 'Unknown error'}`);
            console.error('Failed to fetch records:', data.error);
        }
    })
    .catch(error => {
        toggleLoader(false);
        showErrorModal('Error fetching data. Please try again later.');
        console.error('Error fetching data:', error);
    });
}
    // Apply filters function
    function applyFilters() {
        fetchDeletedfetchDeactivationList(1); // Reset to page 1 when filters are applied
    }

    // Event listener for Apply Filters button
    document.getElementById('applyFilters').addEventListener('click', applyFilters);

    // Optional: Add event listeners for Enter key in filter inputs
    document.querySelectorAll('.filters input, .filters select').forEach(element => {
        element.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
    });
    function showErrorModal(message) {
        const errorModal = document.getElementById('errorModal');
        const errorMessage = document.getElementById('errorMessage');

        errorMessage.textContent = message;

        // If using Bootstrap
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modal = new bootstrap.Modal(errorModal);
            modal.show();
        }
        // Fallback for non-Bootstrap
        else {
            errorModal.style.display = 'block';

            // Add click handler for close button
            const closeButtons = errorModal.querySelectorAll('[data-dismiss="modal"], .close');
            closeButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    errorModal.style.display = 'none';
                });
            });

            // Close when clicking outside modal
            errorModal.addEventListener('click', (e) => {
                if (e.target === errorModal) {
                    errorModal.style.display = 'none';
                }
            });
        }
        // Then in showErrorModal:
        const retryButton = document.getElementById('retryButton');
        if (retryButton) {
            retryButton.onclick = function () {
                fetchDeactivationList(currentPage);
                errorModal.style.display = 'none';
            };
        }
        // In showErrorModal:
        setTimeout(() => {
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bootstrap.Modal.getInstance(errorModal).hide();
            } else {
                errorModal.style.display = 'none';
            }
        }, 5000); // 5 seconds
    }

    function updateTable(deletedStaffs) {
        const ordersTableBody = document.querySelector('#ordersTable tbody');

        // Show loading state
        ordersTableBody.innerHTML = '<tr class="loading-row"><td colspan="10">Loading data...</td></tr>';

        // Clear after a brief delay (simulates async operation)
        setTimeout(() => {
            ordersTableBody.innerHTML = '';

            if (deletedStaffs.length === 0) {
                ordersTableBody.innerHTML = '<tr class="no-data-row"><td colspan="10">No deactivated staff found</td></tr>';
                return;
            }

            deletedStaffs.forEach(record => {
                const row = document.createElement('tr');
                row.classList.add('data-row');

                // Format dates with null checks
                const deactivationDate = record.deactivation_date
                    ? new Date(record.deactivation_date).toLocaleString()
                    : 'N/A';

                const lastUpdatedDate = record.date_last_updated
                    ? new Date(record.date_last_updated).toLocaleString()
                    : 'N/A';

                // Handle reactivation status
                const reactivationStatus = record.reactivation_status
                    ? `<span class="status-badge ${record.reactivation_status.toLowerCase()}">${record.reactivation_status}</span>`
                    : 'N/A';

                // Handle null reactivated_by object or properties
                const reactivatedByName = record.reactivated_by?.name || 'N/A';
                const reactivatedById = record.reactivated_by?.admin_id || 'N/A';

                row.innerHTML = `
                <td>${record.deactivation_id}</td>
                <td>${record.staff.staff_id}</td>
                <td>${record.staff.firstname}</td>
                <td>${record.staff.lastname}</td>
                <td><a href="mailto:${record.staff.email}">${record.staff.email}</a></td>
                <td>${record.deactivated_by.name} (${record.deactivated_by.admin_id})</td>
                <td data-sort="${record.deactivation_date || ''}">${deactivationDate}</td>
                <td>${reactivationStatus}</td>
                <td>${reactivatedByName} (${reactivatedById})</td>
                <td data-sort="${record.date_last_updated || ''}">${lastUpdatedDate}</td>
            `;

                ordersTableBody.appendChild(row);
            });
        }, 500);
    }
    function togglePagination(show) {
        const paginationContainer = document.getElementById('pagination');
        paginationContainer.style.display = show ? 'block' : 'none';
    }

    // Close modal when the "close" button is clicked
    document.querySelectorAll('.modal .close').forEach(closeBtn => {
        closeBtn.addEventListener('click', (event) => {
            const modal = event.target.closest('.modal'); // Find the closest modal
            if (modal) {
                modal.style.display = 'none';
                location.reload();
            }
        });
    });

    // Close modal when clicking outside the modal content
    window.addEventListener('click', (event) => {
        document.querySelectorAll('.modal').forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
                location.reload();
            }
        });
    });

    function updatePagination(totalItems, currentPage, itemsPerPage) {
        const paginationContainer = document.getElementById('pagination');
        paginationContainer.innerHTML = '';

        const totalPages = Math.ceil(totalItems / itemsPerPage);
        paginationButtons = [];

        const createButton = (label, page, disabled = false) => {
            const btn = document.createElement('button');
            btn.textContent = label;
            if (disabled) btn.disabled = true;
            btn.addEventListener('click', () => fetchDeactivationList(page));
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
            btn.addEventListener('click', () => fetchDeactivationList(i));
            paginationButtons.push(btn);
            paginationContainer.appendChild(btn);
        }

        // Show: Next, Last
        createButton('Next ›', currentPage + 1, currentPage === totalPages);
        createButton('Last »', totalPages, currentPage === totalPages);
    }
    document.getElementById("liveSearch").addEventListener("input", filterTable);

    function filterTable() {
        const searchTerm = document.getElementById("liveSearch").value.toLowerCase();
        const rows = document.querySelectorAll("#ordersTable tbody tr");

        rows.forEach(row => {
            const cells = row.getElementsByTagName("td");
            let matchFound = false;

            for (let i = 0; i < cells.length; i++) {
                const cellText = cells[i].textContent.toLowerCase();
                if (cellText.includes(searchTerm)) {
                    matchFound = true;
                    break;
                }
            }

            row.style.display = matchFound ? "" : "none";
        });
    }

    // Initial load
    fetchDeactivationList(currentPage);

    function toggleLoader(show) {
        const loader = document.getElementById('spinner');
        if (loader) {
            loader.style.display = show ? 'block' : 'none';
        }
    }

    function toggleTable(show) {
        const table = document.getElementById('ordersTable');
        if (table) {
            table.style.display = show ? 'table' : 'none'; // 'table' for correct table rendering
        }
    }
    // Function to populate dropdowns with Super Admins
    function populateAdminDropdowns(superAdmins) {
        const deactivatorSelect = document.getElementById('deactivator');
        const reactivatorSelect = document.getElementById('reactivator');

        // Clear existing options (except the first one)
        deactivatorSelect.innerHTML = '<option value="">--All Admin--</option>';
        reactivatorSelect.innerHTML = '<option value="">All</option>';

        // Add Super Admin options using firstname + lastname
        superAdmins.forEach(admin => {
            const fullName = `${admin.firstname} ${admin.lastname}`.trim();
            const displayText = fullName || `Admin ${admin.unique_id}`; // Fallback if names are empty

            const deactivatorOption = new Option(displayText, admin.unique_id);
            const reactivatorOption = new Option(displayText, admin.unique_id);

            deactivatorSelect.add(deactivatorOption);
            reactivatorSelect.add(reactivatorOption);
        });

        console.log(`Populated dropdowns with ${superAdmins.length} Super Admins`);
    }
    function loadSuperAdmins() {
        fetch('backend/get_super_admins.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    populateAdminDropdowns(data.super_admins);
                }
            })
            .catch(error => {
                console.error('Error loading super admins:', error);
            });
    }

});
