function toggleModal(modalId) {
    let modal = document.getElementById(modalId);
    modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";

}
// Close modal when the close icon is clicked
document.querySelector('.modal .modal-content .close2').addEventListener('click', () => {
    document.getElementById('addNewUnitModal').style.display = 'none';
});
// Close modal when clicking outside the modal content
window.addEventListener('click', (event) => {
    document.querySelectorAll('.modal').forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});

// function to fetch all groups
// Function to load groups and populate the select dropdown
function loadGroups(selectedGroupId = null) {
    fetch('backend/fetch_groups.php', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const groupSelect = document.getElementById('selectGroup');
                groupSelect.innerHTML = '<option value="">--Select a Group--</option>';

                data.groups.forEach(group => {
                    const option = document.createElement('option');
                    option.value = group.group_id;
                    option.textContent = group.group_name;

                    // Pre-select the group if it's the current one
                    if (group.group_id === selectedGroupId) {
                        option.selected = true;
                    }

                    groupSelect.appendChild(option);
                });
            } else {
                console.error('Error:', data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching groups:', error);
        });
}


const addUnitForm = document.getElementById('addUnitForm');

document.addEventListener('DOMContentLoaded', () => {
    // Function to load groups and populate the select dropdown
    function loadGroupsOnboarding() {
        fetch('backend/fetch_groups.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const groupSelect = document.getElementById('selectedGroup');
                    groupSelect.innerHTML = '<option value="">--Select a Group--</option>';

                    data.groups.forEach(group => {
                        const option = document.createElement('option');
                        option.value = group.group_id;
                        option.textContent = group.group_name;

                        groupSelect.appendChild(option);
                    });
                } else {
                    console.error('Error:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching groups:', error);
            });
    }
    loadGroupsOnboarding();

    // function to add new group
    function addNewUnit(form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const formData = new FormData(this)
            const messageDiv = document.getElementById('addUnitMessage');
            fetch('backend/add_unit.php', {
                method: 'POST',
                body: formData,
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Success:', data.message);
                        messageDiv.textContent = 'New Unit has been successfully Created!';
                        alert('New Unit has been successfully Created!')
                        location.reload();
                    }
                    else {
                        console.log('Error:', data.message);
                        messageDiv.textContent = data.message;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    messageDiv.textContent = 'Error: ' + error.message;
                    alert('An error occurred. Please Try Again Later')
                });
        })
    }
    
    addNewUnit(addUnitForm);

    function fetchUnits() {
        fetch(`backend/fetch_unittable.php`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateTable(data.units);
                } else {
                    console.error('Failed to fetch Unit Records:', data.message);
                }
            })
            .catch(error => console.error('Error fetching data:', error));
    }

    function updateTable(units) {
        const ordersTableBody = document.querySelector('#ordersTable tbody');
        ordersTableBody.innerHTML = '';
        units.forEach(unit => {

            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${unit.unit_id}</td>
                <td>${unit.unit_name}</td>
                <td>${unit.group_name}</td>
                <td> <span class='edit-icon' data-unit-id = "${unit.unit_id}">&#9998;</span></td>
                <td> <span class='delete-icon' data-unit-id = "${unit.unit_id}">&#128465;</span></td>
                
            `;
            ordersTableBody.appendChild(row);
        });

        // Attach event listeners to the edit icon buttons
        document.querySelectorAll('.edit-icon').forEach(span => {
            span.addEventListener('click', (event) => {
                const unitId = event.target.getAttribute('data-unit-id');
                fetchUnitDetails(unitId);
            });
        });

        // Attach event listeners to the delete icon buttons
        document.querySelectorAll('.delete-icon').forEach(span => {
            span.addEventListener('click', (event) => {
                const unitId = event.target.getAttribute('data-unit-id');
                deleteUnit(unitId);
            });
        });
    }

    function fetchUnitDetails(unitId) {
        fetch(`backend/fetch_unit_details.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ unit_id: unitId })
        })
            .then(response => response.json())
            .then(data => {
                console.log(data); // Log the entire response to the console
                if (data.success) {
                    populateUnitDetails(data.unit_details);
                    console.log(data.logged_in_user_role);
                    document.getElementById('orderModal').style.display = 'block';
                } else {
                    console.error('Failed to fetch Unit details:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching Group details:', error);
            });
    }

    function populateUnitDetails(unit_details) {
        const orderDetailsTable = document.querySelector('#orderDetailsTable tbody');
        // const isSuperAdmin = logged_in_user_role === 'Super Admin';
        // const disableAttribute = isSuperAdmin ? '' : 'disabled';
        orderDetailsTable.innerHTML = `
        
        <tr>
            <td>Unit ID</td>
            <td><input type="text" id="unit_id" value="${unit_details.unit_id}" disabled></td>
        </tr>
        <tr>
            <td>Unit Name</td>
            <td><input type="text" id="unit_name" value="${unit_details.unit_name}"></td>
        </tr>
            <tr>
            <td>Select Group</td>
            <td>
                <select id="selectGroup" class="selectGroup">
                    <option value="">--Select a Group--</option>
                    <!-- Group options will be populated dynamically -->
                </select>
            </td>
        </tr>
        <tr>
                <td colspan="2" style="text-align: center;">
                    <button id="updateUnitBtn">Update</button>
                </td>
            </tr>
        `;

        // Import loadGroups function here
        loadGroups(unit_details.unit_id)
        // Event listeners for update and delete buttons
        document.getElementById('updateUnitBtn').addEventListener('click', () => {
            updateUnit(unit_details.unit_id);
        });
    }

    function updateUnit(unitId) {
        const unitData = {
            unit_id: unitId,
            unit_name: document.getElementById('unit_name').value,
            group_id: document.getElementById('selectGroup').value
        };

        fetch('backend/update_unit.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(unitData)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Unit Details has been updated successfully.');
                    document.getElementById('orderModal').style.display = 'none';
                    location.reload();
                } else {
                    alert('Failed to update Unit Data: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error updating Unit Details:', error);
            });
    }

    function deleteUnit(unitId) {
        if (confirm('Are you sure you want to delete this Unit?')) {
            fetch('backend/delete_unit.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ unit_id: unitId })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Unit has been successfully deleted!');

                        location.reload();
                    } else {
                        console.error('Failed to delete Unit:', data.message);
                        alert('Failed to delete Unit:' + data.message)
                    }
                })
                .catch(error => {
                    console.error('Error deleting Unit:', error);
                });
        }
    }

    document.querySelector('.modal .close').addEventListener('click', () => {
        document.getElementById('orderModal').style.display = 'none';
    });

    window.addEventListener('click', (event) => {
        if (event.target === document.getElementById('orderModal')) {
            document.getElementById('orderModal').style.display = 'none';
        }
    });

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
    fetchUnits();
});
