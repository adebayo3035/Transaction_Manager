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

        fetch(`backend/get_deleted_staff.php?page=${page}&limit=${limit}`, {
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
                                <td colspan="9" style="text-align: center; font-style: italic;">No Record Found</td>
                            </tr>
                        `;
                            togglePagination(false); // Optional: hide pagination when no data
                        } else {
                            updateTable(data.staffData);
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
    function togglePagination(show) {
        const paginationContainer = document.getElementById('pagination');
        paginationContainer.style.display = show ? 'block' : 'none';
    }


    function updateTable(deleted_staffs) {
        const ordersTableBody = document.querySelector('#ordersTable tbody');
        ordersTableBody.innerHTML = '';
    
        deleted_staffs.forEach(staff => {
            const row = document.createElement('tr');
    
            let actionButton = '';
            if (staff.reactivation_status === 'Pending' || staff.reactivation_status === 'No Request') {
                actionButton = `<button class="view-request-btn" data-staff-id="${staff.staff_id}">View Request</button>`;
            }
    
            row.innerHTML = `
                <td>${staff.staff_id}</td>
                <td>${staff.firstname}</td>
                <td>${staff.lastname}</td>
                <td>${staff.reactivation_status}</td>
                <td>${staff.date_deactivated}</td>
                <td>${actionButton}</td>
            `;
    
            ordersTableBody.appendChild(row);
        });
    
        // Add event listener to all "View Request" buttons
        document.querySelectorAll('.view-request-btn').forEach(button => {
            button.addEventListener('click', async (e) => {
                const staffId = e.target.dataset.staffId;
                await fetchReactivationRequest(staffId);
            });
        });
    }

    // function closeModal() {
    //     document.getElementById('reactivationModal').style.display = 'none';
    // }
    
    // function openModal() {
    //     document.getElementById('reactivationModal').style.display = 'block';
    // }

     // Close modal when the "close" button is clicked
     document.querySelectorAll('.modal .close').forEach(closeBtn => {
        closeBtn.addEventListener('click', (event) => {
            const modal = event.target.closest('.modal'); // Find the closest modal
            if (modal) {
                modal.style.display = 'none';
            }
        });
    });

    // Close modal when clicking outside the modal content
    window.addEventListener('click', (event) => {
        document.querySelectorAll('.modal').forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });

    // function to fetch admin details for Deactivation
    async function fetchReactivationRequest(staffId) {
        fetch(`backend/get_reactivation_request.php?staff_id=${staffId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    populateReactivationRequest(data.data);
                    document.getElementById('staffModal').style.display = 'block';
                } else {
                    console.error('Failed to fetch Staff details:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching Staff details:', error);
            });
    }

    function populateReactivationRequest(staff_details) {
        const staffDetailsTable = document.querySelector('#staffDetailsTable tbody');

        // Clear existing content
        staffDetailsTable.innerHTML = '';

        // Create staff details rows
        staffDetailsTable.innerHTML = `
            <tr>
                <td>Staff ID</td>
                <td><input type="text" id="staffID" value="${staff_details.admin_id || ''}" disabled></td>
            </tr>
           <tr>
                <td>Reactivation Reason</td>
                <td>
                    <div id="reactivationReason"> ${staff_details.reactivation_reason || ''}</div>
                </td>
            </tr>
            <tr>
                <td>Status</td>
                <td><input type="text" id="lastname" value="${staff_details.status || ''}" disabled></td>
            </tr>
            <tr>
                <td>Date Requested</td>
                <td><input type="text" id="lastname" value="${staff_details.date_created || ''}" disabled></td>
            </tr>

             <tr>
                <td>Comment</td>
                <td><textarea id ="reactivationReason" placeholder="Enter Comment here..."></textarea></td>
            </tr>
            
            <tr>
                <td style="text-align: center;">
                    <button id="approveBtn">Approve</button>
                </td>
                <td style="text-align: center;">
                    <button id="declineBtn" style = "background-color: black;">Decline</button>
                </td>
            </tr>
        `;

        // Deactivation button handler
        const deActivateStaffBtn = document.getElementById('approveBtn');
        deActivateStaffBtn.addEventListener('click', async () => {
            try {
                const reason = document.getElementById('deactivationReason').value.trim();

                if (!staff_details?.unique_id) {
                    alert('No staff selected.');
                    return;
                }

                if (!reason) {
                    alert('Please provide a deactivation reason.');
                    return;
                }

                await deleteStaff(staff_details.unique_id, reason);

            } catch (error) {
                console.error('Delete Staff Handler Error:', error);
                alert(`Error: ${error.message || 'Failed to deactivate staff.'}`);
            }
        });
    }

    
    // async function fetchReactivationRequest(staffId) {
    //     fetch(`backend/get_reactivation_request.php?staff_id=${staffId}`)
    //         .then(res => res.json())
    //         .then(data => {
    //             if (data.success) {
    //                 document.getElementById('modalReason').textContent = data.reason;
    //                 document.getElementById('modalRequestedBy').textContent = data.admin_id;
    //                 // document.getElementById('modalRequestedDate').textContent = data.date_created;
    
    //                 document.getElementById('approveBtn').onclick = () => approveRequest(staffId);
    //                 document.getElementById('declineBtn').onclick = () => declineRequest(staffId);
    
    //                 openModal();
    //             } else {
    //                 alert(data.error || "Could not fetch request details.");
    //             }
    //         })
    //         .catch(err => {
    //             console.error("Error fetching reactivation details:", err);
    //         });
    // }
    
    
    
    
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
