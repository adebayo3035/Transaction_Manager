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

        fetch(`backend/get_staff_reactivation_request.php?page=${page}&limit=${limit}`, {
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
            if (staff.reactivation_status === 'Pending' || staff.reactivation_status === 'No Request' || staff.reactivation_status === 'Declined' || staff.reactivation_status === 'Reactivated') {
                actionButton = `<button class="view-request-btn" data-staff-id="${staff.staff_id}" data-reactivation-id="${staff.reactivation_id}">View Request</button>`;
            }

            // Format deactivator information
            const deactivatorInfo = staff.deactivated_by
                ? `${staff.deactivated_by.admin_id} - ${staff.deactivated_by.name}`
                : 'Unknown'; // Fallback in case deactivated_by is null

            row.innerHTML = `
                <td>${staff.staff_id}</td>
                <td>${staff.firstname}</td>
                <td>${staff.lastname}</td>
                <td>${staff.reactivation_status}</td>
                <td>${staff.date_deactivated}</td>
                <td>${deactivatorInfo}</td> <!-- New column for deactivator info -->
                <td>${actionButton}</td>
            `;

            ordersTableBody.appendChild(row);
        });

        // Add event listener to all "View Request" buttons
        document.querySelectorAll('.view-request-btn').forEach(button => {
            button.addEventListener('click', async (e) => {
                const staffId = e.target.dataset.staffId;
                const reactivationId = e.target.dataset.reactivationId;
                await fetchReactivationRequest(staffId, reactivationId);
            });
        });
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

    // function to fetch admin details for Deactivation
    async function fetchReactivationRequest(staffId, reactivationId) {
        fetch(`backend/get_reactivation_request_details.php?staff_id=${staffId}&reactivation_id=${reactivationId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    populateReactivationRequest(data.data);
                    document.getElementById('staffModal').style.display = 'block';
                } else {
                    alert("No request found: " + data.message);
                }
            })
            .catch(error => console.error('Error:', error));
    }

    function populateReactivationRequest(responseData) {
        const staffDetailsTable = document.querySelector('#staffDetailsTable tbody');
        const { deactivation, staff, deactivator, reactivation } = responseData;

        // Clear existing content
        staffDetailsTable.innerHTML = '';

        // Determine if comment field should be editable
        const isPending = reactivation?.status === 'Pending';
        const commentValue = reactivation?.comment || '';

        // Create staff details rows
        staffDetailsTable.innerHTML = `
            <tr>
                <td>Staff ID</td>
                <td><input type="text" value="${staff?.id || ''}" disabled></td>
            </tr>
            <tr>
                <td>Staff Name</td>
                <td><input type="text" value="${staff?.firstname || ''} ${staff?.lastname || ''}" disabled></td>
            </tr>
            <tr>
                <td>Deactivated By</td>
                <td>
                    <input type="text" value="${deactivator ?
                `${deactivator.firstname} ${deactivator.lastname} (ID: ${deactivator.id})` :
                'Unknown'
            }" disabled>
                </td>
            </tr>
            <tr>
                <td>Reason for Deactivation</td>
                <td>${deactivation?.deactivation_reason}</td>
            </tr>
            <tr>
                <td>Justification</td>
                <td>${reactivation?.reactivation_reason || 'None'} </td>
            </tr>
            <tr>
                <td>Reactivation Status</td>
                <td><input type="text" value="${reactivation?.status || ''}" disabled></td>
            </tr>
            <tr>
                <td>Date Requested</td>
                <td><input type="text" value="${reactivation?.date || ''}" disabled></td>
            </tr>
            <tr>
                <td>Comment</td>
                <td>
                    <textarea id="reactivationComment" 
                        ${isPending ? '' : 'disabled'}
                        placeholder="Enter comment here..." maxlength="100" rows="5" cols="40">${commentValue}</textarea>
                </td>
            </tr>
            ${isPending ? `
            <tr>
                <td style="text-align: center;">
                    <button id="approveBtn">Approve</button>
                </td>
                <td style="text-align: center;">
                    <button id="declineBtn" style="background-color: black;">Decline</button>
                </td>
            </tr>
            ` : ''}
        `;

        // Add event listeners for buttons (if they exist)
        document.getElementById('approveBtn')?.addEventListener('click', () => {
            const confirmed = confirm("Are you sure you want to approve this reactivation?");
            if (confirmed) {
                ReactiveateAccount(staff.id, 'Reactivated');
            }
        });

        document.getElementById('declineBtn')?.addEventListener('click', () => {
            const confirmed = confirm("Are you sure you want to decline this reactivation?");
            if (confirmed) {
                ReactiveateAccount(staff.id, 'Declined');
            }
        });
    }

    function ReactiveateAccount(staff_id, action) {
        const comment = document.getElementById('reactivationComment').value;
        const reactivateBtn = document.getElementById('reactivateBtn'); // Make sure your button has this ID

        // Validate comment if required
        if (!comment.trim()) {
            alert('Please enter a reactivation comment');
            return;
        }

        // Create and show loader
        const loaderOverlay = document.createElement('div');
        loaderOverlay.className = 'loader-overlay';
        loaderOverlay.innerHTML = '<div class="roller-loader"></div>';
        document.body.appendChild(loaderOverlay);

        // Disable button during processing
        if (reactivateBtn) {
            reactivateBtn.disabled = true;
            reactivateBtn.textContent = 'Processing...';
        }

        fetch('backend/reactivate_admin_account.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                staff_id: staff_id,
                action: action,
                comment: comment
            })
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === "success") {
                    alert(`Staff Account Reactivation Request has been successfully ${action}`);
                    setTimeout(() => location.reload(), 2000);
                } else {
                    throw new Error(data.message || 'Unknown error occurred');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(`Error: ${error.message}`);
                setTimeout(() => location.reload(), 3000);
            })
            .finally(() => {
                // Remove loader and reset button
                loaderOverlay.remove();
                if (reactivateBtn) {
                    reactivateBtn.disabled = false;
                    reactivateBtn.textContent = 'Reactivate Account'; // Or your original button text
                }
            });
    }

    function updatePagination(totalItems, currentPage, itemsPerPage) {
        const paginationContainer = document.getElementById('pagination');
        paginationContainer.innerHTML = '';

        const totalPages = Math.ceil(totalItems / itemsPerPage);
        paginationButtons = [];

        const createButton = (label, page, disabled = false) => {
            const btn = document.createElement('button');
            btn.textContent = label;
            if (disabled) btn.disabled = true;
            btn.addEventListener('click', () => fetchSDeletedtaffs(page));
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
            btn.addEventListener('click', () => fetchDeletedStaffs(i));
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
