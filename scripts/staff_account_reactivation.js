document.addEventListener('DOMContentLoaded', () => {
    const limit = 10; // Number of items per page
    let currentPage = 1;

    function maskDetails(details) {
        return details.slice(0, 2) + ' ** ** ' + details.slice(-3);
    }

    function fetchDeletedStaffs(page = 1) {
        currentPage = page;
        toggleLoader(true);
        toggleTable(false);

        fetch(`backend/deleted_staff.php?page=${page}&limit=${limit}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    setTimeout(() => {
                        if (data.staffData.length === 0) {
                            // Show "No Pending Request" row
                            const tableBody = document.querySelector('#ordersTable tbody');
                            tableBody.innerHTML = `
                            <tr>
                                <td colspan="9" style="text-align: center; font-style: italic;">No Pending Request</td>
                            </tr>
                        `;
                            togglePagination(false); // Optional: hide pagination when no data
                        } else {
                            updateTable(data.staffData);
                            updatePagination(data.total, data.page, data.limit);
                        }
                        toggleLoader(false);
                        toggleTable(true);
                    }, 2000);
                } else {
                    console.error('Failed to fetch Staff Records:', data.message);
                }
            })
            .catch(error => {
                toggleLoader(false);
                console.error('Error fetching data:', error);
            });
    }
    function togglePagination(show) {
        const paginationContainer = document.getElementById('pagination');
        paginationContainer.style.display = show ? 'block' : 'none';
    }


    function updateTable(deleted_staffs) {
        const ordersTableBody = document.querySelector('#ordersTable tbody');
        ordersTableBody.innerHTML = '';
    
        deleted_staffs.forEach(staff => {
            const row = document.createElement('tr');
    
            // Initialize button markup
            let actionButtons = '';
    
            if (staff.status === 'Pending') {
                actionButtons = `
                    <td><button class="view-details-btn approve" data-staff-id="${staff.staff_id}">Approve</button></td>
                    <td><button class="view-details-btn decline" data-staff-id="${staff.staff_id}">Decline</button></td>
                `;
            } else if (staff.status === 'Rejected') {
                actionButtons = `
                    <td><button class="view-details-btn approve" data-staff-id="${staff.staff_id}">Approve</button></td>
                    <td></td>
                `;
            } else {
                actionButtons = `
                    <td></td>
                    <td></td>
                `;
            }
    
            row.innerHTML = `
                <td>${staff.staff_id}</td>
                <td>${staff.firstname}</td>
                <td>${staff.lastname}</td>
                <td>${staff.status}</td>
                <td>${staff.reactivation_request_date}</td>
                ${actionButtons}
            `;
    
            ordersTableBody.appendChild(row);
        });
    
        // Add event listeners for the approve and decline buttons
        document.querySelectorAll('.approve').forEach(button => {
            button.addEventListener('click', event => {
                const staffId = event.target.getAttribute('data-staff-id');
                ReactivateStaff(staffId, 'approve');
            });
        });
    
        document.querySelectorAll('.decline').forEach(button => {
            button.addEventListener('click', event => {
                const staffId = event.target.getAttribute('data-staff-id');
                ReactivateStaff(staffId, 'decline');
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

    // Dummy placeholder for action handlers
    // Example of calling the approve action from the frontend
    function ReactivateStaff(staffId, action) {
        const confirmText = action === 'approve' 
            ? 'Are you sure you want to APPROVE this reactivation request?' 
            : 'Are you sure you want to REJECT this reactivation request?';
    
        if (!confirm(confirmText)) {
            return; // Exit if user cancels
        }
    
        fetch('backend/reactivate_admin_account.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=${action}&staff_id=${staffId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message); // You can use toast or modal instead
                fetchDeletedStaffs(currentPage); // Refresh the table
            } else {
                alert("Error: " + data.message);
                console.error(data.message);
            }
        })
        .catch(error => console.error('Error:', error));
    }
    

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
