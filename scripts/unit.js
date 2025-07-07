// Modal Functions
function toggleModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";
}

function setupModalCloseListeners() {
    document.querySelector('.modal .modal-content .close2').addEventListener('click', () => {
        document.getElementById('addNewUnitModal').style.display = 'none';
    });

    document.querySelector('.modal .close').addEventListener('click', () => {
        document.getElementById('orderModal').style.display = 'none';
    });

    window.addEventListener('click', (event) => {
        document.querySelectorAll('.modal').forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });
}

// Unit Management Class
class UnitManager {
    constructor() {
        this.limit = 10;
        this.currentPage = 1;
        this.init();
    }

    init() {
        setupModalCloseListeners();
        this.fetchUnits();
        this.loadGroupsOnboarding();
        this.setupSearchListener();
        this.setupAddUnitForm();
    }

    // Fetch and display units
    async fetchUnits(page = 1) {
        const tbody = document.getElementById('ordersTableBody');
        this.showLoadingSpinner(tbody);

        try {
            const [data] = await Promise.all([
                fetch(`backend/fetch_unittable.php?page=${page}&limit=${this.limit}`).then(res => res.json()),
                new Promise(resolve => setTimeout(resolve, 1000)) // Minimum spinner display time
            ]);

            if (data.success && data.units.length > 0) {
                this.updateTable(data.units, data.user_role);
                this.updatePagination(data.pagination.total, data.pagination.page, data.pagination.limit);
            } else {
                this.showNoDataMessage(tbody);
            }
        } catch (error) {
            this.showErrorMessage(tbody);
        }
    }

    showLoadingSpinner(tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="5" style="text-align:center; padding: 20px;">
                    <div class="spinner"
                        style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: auto;">
                    </div>
                </td>
            </tr>`;
    }

    showNoDataMessage(tableBody) {
        tableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;">No Units found</td></tr>`;
    }

    showErrorMessage(tableBody) {
        tableBody.innerHTML = `<tr><td colspan="5" style="text-align:center; color:red;">Error loading units</td></tr>`;
    }

    // Render units table
    updateTable(units, userRole) {
        const tbody = document.querySelector('#ordersTable tbody');
        tbody.innerHTML = '';
        
        units.forEach(unit => {
            const row = document.createElement('tr');
            let rowHTML = `
                <td>${unit.unit_id}</td>
                <td>${unit.unit_name}</td>
                <td>${unit.group_name}</td>
                <td><span class='edit-icon' data-unit-id="${unit.unit_id}">&#9998;</span></td>
                
            `;
            if(userRole === "Super Admin"){
                rowHTML+= `<td><span class='delete-icon' data-unit-id="${unit.unit_id}">&#128465;</span></td>`
            }
            else{
                rowHTML += `<td></td>`;
            }
            row.innerHTML = rowHTML;
            tbody.appendChild(row);
        });

        this.setupEditDeleteListeners();
    }

    setupEditDeleteListeners() {
        document.querySelectorAll('.edit-icon').forEach(span => {
            span.addEventListener('click', () => this.fetchUnitDetails(span.dataset.unitId));
        });

        document.querySelectorAll('.delete-icon').forEach(span => {
            span.addEventListener('click', () => this.deleteUnit(span.dataset.unitId));
        });
    }

    // Pagination
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
            btn.addEventListener('click', () => this.fetchUnits(i));
            paginationContainer.appendChild(btn);
        }

        this.createPaginationButton('Next ›', currentPage + 1, currentPage === totalPages, paginationContainer);
        this.createPaginationButton('Last »', totalPages, currentPage === totalPages, paginationContainer);
    }

    createPaginationButton(label, page, disabled, container) {
        const btn = document.createElement('button');
        btn.textContent = label;
        if (disabled) btn.disabled = true;
        btn.addEventListener('click', () => this.fetchUnits(page));
        container.appendChild(btn);
    }

    // Group Management
    async loadGroupsOnboarding() {
        try {
            const response = await fetch('backend/fetch_groups.php');
            const data = await response.json();
            
            if (data.success) {
                const select = document.getElementById('selectedGroup');
                this.populateGroupDropdown(select, data.groups, '--Select a Group--');
            }
        } catch (error) {
            console.error('Error loading groups:', error);
        }
    }

    async loadGroups(selectedGroupId = null) {
        try {
            const response = await fetch('backend/fetch_groups.php');
            const data = await response.json();
            
            if (data.success) {
                const select = document.getElementById('selectGroup');
                this.populateGroupDropdown(select, data.groups, '--Select a Group--', selectedGroupId);
            }
        } catch (error) {
            console.error('Error loading groups:', error);
        }
    }

    populateGroupDropdown(selectElement, groups, defaultText, selectedId = null) {
        selectElement.innerHTML = `<option value="">${defaultText}</option>`;
        
        groups.forEach(group => {
            const option = document.createElement('option');
            option.value = group.group_id;
            option.textContent = group.group_name;
            if (group.group_id == selectedId) option.selected = true;
            selectElement.appendChild(option);
        });
    }

    // Unit CRUD Operations
    async fetchUnitDetails(unitId) {
        try {
            const response = await fetch('backend/fetch_unit_details.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ unit_id: unitId })
            });
            const data = await response.json();

            if (data.success) {
                this.populateUnitDetails(data.unit_details);
                document.getElementById('orderModal').style.display = 'block';
            }
        } catch (error) {
            console.error('Error fetching unit details:', error);
        }
    }

    populateUnitDetails(unit) {
        const tbody = document.querySelector('#orderDetailsTable tbody');
        tbody.innerHTML = `
            <tr><td>Unit ID</td><td><input type="text" id="unit_id" value="${unit.unit_id}" disabled></td></tr>
            <tr><td>Unit Name</td><td><input type="text" id="unit_name" value="${unit.unit_name}"></td></tr>
            <tr><td>Select Group</td><td><select id="selectGroup"></select></td></tr>
            <tr><td colspan="2" style="text-align:center;"><button id="updateUnitBtn">Update</button></td></tr>
        `;
        
        this.loadGroups(unit.group_id);
        document.getElementById('updateUnitBtn').addEventListener('click', () => this.updateUnit(unit.unit_id));
    }

    async updateUnit(unitId) {
        const data = {
            unit_id: unitId,
            unit_name: document.getElementById('unit_name').value,
            group_id: document.getElementById('selectGroup').value
        };

        try {
            const response = await fetch('backend/update_unit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();

            if (result.success) {
                alert('Unit updated successfully');
                document.getElementById('orderModal').style.display = 'none';
                location.reload();
            } else {
                alert('Update failed: ' + result.message);
            }
        } catch (error) {
            console.error('Error updating unit:', error);
            alert('An error occurred while updating the unit');
        }
    }

    async deleteUnit(unitId) {
        if (!confirm('Are you sure you want to delete this Unit?')) return;

        try {
            const response = await fetch('backend/delete_unit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ unit_id: unitId })
            });
            const data = await response.json();

            if (data.success) {
                alert('Unit deleted successfully');
                location.reload();
            } else {
                alert('Delete failed: ' + data.message);
            }
        } catch (error) {
            console.error('Error deleting unit:', error);
            alert('An error occurred while deleting the unit');
        }
    }

    // Form Handling
    setupAddUnitForm() {
        const addUnitForm = document.getElementById('addUnitForm');
        if (!addUnitForm) return;

        addUnitForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const messageDiv = document.getElementById('addUnitMessage');
            
            if (!confirm('Are you sure you want to create this Unit?')) return;

            try {
                const formData = new FormData(addUnitForm);
                const response = await fetch('backend/add_unit.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    alert('New Unit has been successfully created!');
                    location.reload();
                } else {
                    messageDiv.textContent = data.message;
                }
            } catch (error) {
                messageDiv.textContent = 'Error: ' + error.message;
                alert('An error occurred. Please try again later.');
            }
        });
    }

    // Search Functionality
    setupSearchListener() {
        document.getElementById("liveSearch").addEventListener("input", () => this.filterTable());
    }

    filterTable() {
        const searchTerm = document.getElementById("liveSearch").value.toLowerCase();
        document.querySelectorAll("#ordersTable tbody tr").forEach(row => {
            const cells = [...row.getElementsByTagName("td")];
            const matchFound = cells.some(cell => cell.textContent.toLowerCase().includes(searchTerm));
            row.style.display = matchFound ? "" : "none";
        });
    }
}
// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new UnitManager();
});