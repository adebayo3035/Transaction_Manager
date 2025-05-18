function toggleModal(modalId) {
    let modal = document.getElementById(modalId);
    modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";
}
document.addEventListener('DOMContentLoaded', () => {
    const limit = 10; // Number of items per page
    let currentPage = 1;

    function fetchDeletedStaffs(page = 1) {
        currentPage = page;
        toggleLoader(true);
        toggleTable(false);

        fetch(`backend/get_staff_deactivation_reactivation_history.php?page=${page}&limit=${limit}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    setTimeout(() => {
                        if (data.deletedStaff.length === 0) {  // Changed from staffData to deletedStaff
                            const tableBody = document.querySelector('#ordersTable tbody');
                            tableBody.innerHTML = `
                        <tr>
                            <td colspan="9" style="text-align: center; font-style: italic;">No Record Found</td>
                        </tr>
                    `;
                            togglePagination(false);
                        } else {
                            updateTable(data.deletedStaff);  // Updated parameter name
                            updatePagination(data.total, data.page, data.limit);
                        }
                        toggleLoader(false);
                        toggleTable(true);
                    }, 1000);
                } else {
                    console.error('Failed to fetch Staff Records:', data.message);
                }
            })
            .catch(error => {
                toggleLoader(false);
                console.error('Error fetching data:', error);
            });
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

        for (let page = 1; page <= totalPages; page++) {
            const pageButton = document.createElement('button');
            pageButton.textContent = page;
            pageButton.classList.add('page-btn');
            if (page === currentPage) {
                pageButton.classList.add('active');
            }
            pageButton.addEventListener('click', () => {
                fetchDeletedStaffs(page);
            });
            paginationContainer.appendChild(pageButton);
        }
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
    fetchDeletedStaffs(currentPage);

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

});
