class GroupManager {
    constructor() {
        this.limit = 10;
        this.currentPage = 1;
        this.init();
    }

    init() {
        this.initModalControls();
        this.setupAddGroupForm();
        this.fetchGroups();
        this.setupSearchListener();
    }

    /** ------------------------- Modal Controls ------------------------- **/
    initModalControls() {
        document.querySelector('.modal .modal-content .close2').addEventListener('click', () => {
            document.getElementById('addNewGroupModal').style.display = 'none';
        });

        document.querySelector('.modal .close').addEventListener('click', () => {
            document.getElementById('orderModal').style.display = 'none';
            this.fetchGroups();
        });

        window.addEventListener('click', (event) => {
            document.querySelectorAll('.modal').forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
    }

    toggleModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";
    }

    /** ------------------------- Form Submission ------------------------- **/
    setupAddGroupForm() {
        const form = document.getElementById('addGroupForm');
        if (!form) return;

        const messageDiv = document.getElementById('addGroupMessage');

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!confirm('Are you sure you want to add a new group?')) return;

            try {
                const formData = new FormData(form);
                const response = await fetch('backend/add_group.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    this.showSuccessMessage(messageDiv, 'New Group has been successfully added!');
                    form.reset();
                } else {
                    this.showErrorMessage(messageDiv, data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                this.showErrorMessage(messageDiv, 'An error occurred. Please try again later.');
            }
        });
    }

    showSuccessMessage(element, message) {
        element.textContent = message;
        element.style.color = 'green';
        alert(message);
    }

    showErrorMessage(element, message) {
        element.textContent = message;
        element.style.color = 'red';
    }

    /** ------------------------- Fetch & Display ------------------------- **/
    async fetchGroups(page = 1) {
        const ordersTableBody = document.getElementById('ordersTableBody');
        this.showLoadingSpinner(ordersTableBody);

        try {
            const [data] = await Promise.all([
                fetch(`backend/fetch_grouptable.php?page=${page}&limit=${this.limit}`).then(res => res.json()),
                new Promise(resolve => setTimeout(resolve, 1000)) // Minimum loading time
            ]);

            if (data.success && data.groups.length > 0) {
                this.updateTable(data.groups, data.user_role);
                this.updatePagination(data.pagination.total, data.pagination.page, data.pagination.limit);
            } else {
                this.showNoDataMessage(ordersTableBody);
            }
        } catch (error) {
            console.error('Error fetching data:', error);
            this.showErrorMessage(ordersTableBody, 'Error loading Groups data');
        }
    }

    showLoadingSpinner(container) {
        container.innerHTML = `
            <tr>
                <td colspan="5" style="text-align:center; padding: 20px;">
                    <div class="spinner"
                        style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: auto;">
                    </div>
                </td>
            </tr>`;
    }

    showNoDataMessage(container) {
        container.innerHTML = `<tr><td colspan="5" style="text-align:center;">No Groups found at the moment</td></tr>`;
    }

    updateTable(groups, userRole) {
        const tbody = document.querySelector('#ordersTable tbody');
        tbody.innerHTML = '';

        groups.forEach(group => {
            const row = document.createElement('tr');
            let rowHTML = `
            <td>${group.group_id}</td>
            <td>${group.group_name}</td>
        `;

            if (group.is_deleted === false) {
                // Active record
                rowHTML += `<td><span class='edit-icon' data-group-id="${group.group_id}">&#9998;</span></td>`;
                if (userRole === "Super Admin") {
                    rowHTML += `<td><span class='delete-icon' data-group-id="${group.group_id}">&#128465;</span></td>`;
                } else {
                    rowHTML += `<td></td>`;
                }
            } else {
                // Soft-deleted record
                if (userRole === "Super Admin") {
                    rowHTML += `
                    <td colspan="2" style="text-align:center;">
                        <span class="restore-icon" data-class-type="group" data-class-id="${group.group_id}" title="Restore">&#9851;</span>
                    </td>`;
                } else {
                    // Non-super admins see nothing
                    rowHTML += `<td></td><td></td>`;
                }
            }

            row.innerHTML = rowHTML;
            tbody.appendChild(row);
        });

        this.setupEditDeleteListeners();
        this.setupRestoreListeners(); // Make sure you have this implemented to handle restore actions
    }


    setupEditDeleteListeners() {
        document.querySelectorAll('.edit-icon').forEach(span => {
            span.addEventListener('click', () => this.fetchGroupDetails(span.dataset.groupId));
        });

        document.querySelectorAll('.delete-icon').forEach(span => {
            span.addEventListener('click', () => this.deleteGroup(span.dataset.groupId));
        });
    }

    updatePagination(totalItems, currentPage, itemsPerPage) {
        const paginationContainer = document.getElementById('pagination');
        paginationContainer.innerHTML = '';
        const totalPages = Math.ceil(totalItems / itemsPerPage);

        this.createPaginationButton('« First', 1, currentPage === 1, paginationContainer);
        this.createPaginationButton('‹ Prev', currentPage - 1, currentPage === 1, paginationContainer);

        const maxVisible = 2;
        const start = Math.max(1, currentPage - maxVisible);
        const end = Math.min(totalPages, currentPage + maxVisible);

        for (let i = start; i <= end; i++) {
            const btn = document.createElement('button');
            btn.textContent = i;
            if (i === currentPage) btn.classList.add('active');
            btn.addEventListener('click', () => this.fetchGroups(i));
            paginationContainer.appendChild(btn);
        }

        this.createPaginationButton('Next ›', currentPage + 1, currentPage === totalPages, paginationContainer);
        this.createPaginationButton('Last »', totalPages, currentPage === totalPages, paginationContainer);
    }

    createPaginationButton(label, page, disabled, container) {
        const btn = document.createElement('button');
        btn.textContent = label;
        if (disabled) btn.disabled = true;
        btn.addEventListener('click', () => this.fetchGroups(page));
        container.appendChild(btn);
    }

    /** ------------------------- CRUD Operations ------------------------- **/
    async fetchGroupDetails(groupId) {
        try {
            const response = await fetch(`backend/fetch_group_details.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ group_id: groupId })
            });
            const data = await response.json();

            if (data.success) {
                this.populateGroupDetails(data.group_details);
                document.getElementById('orderModal').style.display = 'block';
            } else {
                console.error('Failed to fetch Group details:', data.message);
            }
        } catch (error) {
            console.error('Error fetching Group details:', error);
        }
    }

    populateGroupDetails(group) {
        const tableBody = document.querySelector('#orderDetailsTable tbody');
        tableBody.innerHTML = `
            <tr>
                <td>Group ID</td>
                <td><input type="text" id="group_id" value="${group.group_id}" disabled></td>
            </tr>
            <tr>
                <td>Group Name</td>
                <td><input type="text" id="group_name" value="${group.group_name}"></td>
            </tr>
            <tr>
                <td colspan="2" style="text-align:center;">
                    <button id="updateGroupBtn">Update</button>
                </td>
            </tr>`;

        document.getElementById('updateGroupBtn').addEventListener('click', () => {
            if (confirm('Are you sure you want to Update Group Information?')) {
                this.updateGroup(group.group_id);
            }
        });
    }

    async updateGroup(groupId) {
        const groupData = {
            group_id: groupId,
            group_name: document.getElementById('group_name').value
        };

        try {
            const response = await fetch('backend/update_group.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(groupData)
            });
            const data = await response.json();

            if (data.success) {
                alert('Group Details has been updated successfully.');
                document.getElementById('orderModal').style.display = 'none';
                location.reload();
            } else {
                alert('Failed to update Group Data: ' + data.message);
            }
        } catch (error) {
            console.error('Error updating Group Details:', error);
        }
    }

    async deleteGroup(groupId) {
        if (!confirm('Are you sure you want to delete this Group?')) return;

        try {
            const response = await fetch('backend/delete_groups.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ group_id: groupId })
            });
            const data = await response.json();

            if (data.success) {
                alert('Group has been successfully deleted!');
                location.reload();
            } else {
                alert('Failed to delete Group: ' + data.message);
                location.reload();
            }
        } catch (error) {
            console.error('Error deleting Group:', error);
        }
    }

    //setupRestoreListener
    setupRestoreListeners() {
    const restoreIcons = document.querySelectorAll('.restore-icon');

    restoreIcons.forEach(icon => {
        icon.addEventListener('click', () => {
            const classType = icon.getAttribute('data-class-type'); // "unit" or "group"
            const classId = icon.getAttribute('data-class-id');     // e.g., 5
            const confirmRestore = confirm(`Are you sure you want to restore this ${classType}?`);

            if (confirmRestore) {
                fetch('backend/update_unit_group.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        [`${classType}_id`]: classId,
                        class_type: classType,
                        delete_status: null
                    })
                })
                .then(res => res.json())
                .then(response => {
                    alert(response.message);
                    if (response.success) {
                        if (classType === 'group') this.fetchGroups();
                    }
                })
                .catch(err => {
                    console.error("Restore failed:", err);
                    alert("An error occurred while restoring the record.");
                });
            }
        });
    });
}

    /** ------------------------- Search Filter ------------------------- **/
    setupSearchListener() {
        document.getElementById("liveSearch").addEventListener("input", () => this.filterTable());
    }

    filterTable() {
        const searchTerm = document.getElementById("liveSearch").value.toLowerCase();
        const rows = document.querySelectorAll("#ordersTable tbody tr");

        rows.forEach(row => {
            const cells = Array.from(row.getElementsByTagName("td"));
            const matchFound = cells.some(cell => cell.textContent.toLowerCase().includes(searchTerm));
            row.style.display = matchFound ? "" : "none";
        });
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new GroupManager();
});