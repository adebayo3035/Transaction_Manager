function toggleModal(modalId) {
    let modal = document.getElementById(modalId);
    modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";

}
document.querySelector('.modal .modal-content .close2').addEventListener('click', () => {
    document.getElementById('addNewGroupModal').style.display = 'none';
});

document.querySelector('.modal .close').addEventListener('click', () => {
    document.getElementById('orderModal').style.display = 'none';
    fetchGroups();
});

window.addEventListener('click', (event) => {
    document.querySelectorAll('.modal').forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});
// const addGroupForm = document.getElementById('addGroupForm');

document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById('addGroupForm');
    const messageDiv = document.getElementById('addGroupMessage');

    if (form) {
        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            if (!confirm('Are you sure you want to add a new group?')) {
                return;
            }

            const formData = new FormData(form);

            try {
                const response = await fetch('backend/add_group.php', {
                    method: 'POST',
                    body: formData,
                });

                const data = await response.json();

                if (data.success) {
                    messageDiv.textContent = 'New Group has been successfully added!';
                    messageDiv.style.color = 'green';
                    alert('New Group has been successfully added!');

                    // Instead of reloading, clear the form and update UI if necessary
                    form.reset();
                } else {
                    messageDiv.textContent = data.message;
                    messageDiv.style.color = 'red';
                }
            } catch (error) {
                console.error('Error:', error);
                messageDiv.textContent = 'An error occurred. Please try again later.';
                messageDiv.style.color = 'red';
                alert('An error occurred. Please try again later.');
            }
        });
    }
});


function fetchGroups() {
    fetch(`backend/fetch_groups.php`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.groups.length > 0) {
                updateTable(data.groups);
            } else {
                const ordersTableBody = document.querySelector('#ordersTable tbody');
                ordersTableBody.innerHTML = '';
                const noOrderRow = document.createElement('tr');
                noOrderRow.innerHTML = `<td colspan="4" style="text-align:center;">No Group Records found at the moment</td>`;
                ordersTableBody.appendChild(noOrderRow);
                console.error('Failed to fetch Group Records:', data.message);
            }
        })
        .catch(error => console.error('Error fetching data:', error));
}

function updateTable(groups) {
    const ordersTableBody = document.querySelector('#ordersTable tbody');
    ordersTableBody.innerHTML = '';
    groups.forEach(group => {

        const row = document.createElement('tr');
        row.innerHTML = `
                <td>${group.group_id}</td>
                <td>${group.group_name}</td>
                <td> <span class='edit-icon' data-group-id = "${group.group_id}">&#9998;</span></td>
                <td> <span class='delete-icon' data-group-id = "${group.group_id}">&#128465;</span></td>
                
            `;
        ordersTableBody.appendChild(row);
    });

    // Attach event listeners to the edit icon buttons
    document.querySelectorAll('.edit-icon').forEach(span => {
        span.addEventListener('click', (event) => {
            const groupId = event.target.getAttribute('data-group-id');
            fetchGroupDetails(groupId);
        });
    });

    // Attach event listeners to the delete icon buttons
    document.querySelectorAll('.delete-icon').forEach(span => {
        span.addEventListener('click', (event) => {
            const groupId = event.target.getAttribute('data-group-id');
            deleteGroup(groupId);
        });
    });
}

function fetchGroupDetails(groupId) {
    fetch(`backend/fetch_group_details.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ group_id: groupId })
    })
        .then(response => response.json())
        .then(data => {
            console.log(data); // Log the entire response to the console
            if (data.success) {
                populateGroupDetails(data.group_details);
                console.log(data.logged_in_user_role);
                document.getElementById('orderModal').style.display = 'block';
            } else {
                console.error('Failed to fetch Group details:', data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching Group details:', error);
        });
}

function populateGroupDetails(group_details) {
    const orderDetailsTable = document.querySelector('#orderDetailsTable tbody');
    // const isSuperAdmin = logged_in_user_role === 'Super Admin';
    // const disableAttribute = isSuperAdmin ? '' : 'disabled';
    orderDetailsTable.innerHTML = `
        <tr>
            <td>Group ID</td>
            <td><input type="text" id="group_id" value="${group_details.group_id}" disabled></td>
        </tr>
        <tr>
            <td>Group Name</td>
            <td><input type="text" id="group_name" value="${group_details.group_name}"></td>
        </tr>
        <tr>
                <td colspan="2" style="text-align: center;">
                    <button id="updateGroupBtn">Update</button>
                </td>
            </tr>
        `;
    // Event listeners for update and delete buttons
    document.getElementById('updateGroupBtn').addEventListener('click', () => {
        if (confirm('Are you sure you want to Update Group Information?')) {
            updateGroup(group_details.group_id);
        }

    });
}

function updateGroup(groupId) {
    const groupData = {
        group_id: groupId,
        group_name: document.getElementById('group_name').value
    };

    fetch('backend/update_group.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(groupData)
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Group Details has been updated successfully.');
                document.getElementById('orderModal').style.display = 'none';
                location.reload();
            } else {
                alert('Failed to update Group Data: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error updating Group Details:', error);
        });
}

function deleteGroup(groupId) {
    if (confirm('Are you sure you want to delete this Group?')) {
        fetch('backend/delete_groups.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ group_id: groupId })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Group has been successfully deleted!');

                    location.reload();
                } else {
                    console.error('Failed to delete Group:', data.message);
                    alert('Failed to delete Group:' + data.message)
                }
            })
            .catch(error => {
                console.error('Error deleting Group:', error);
            });
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
fetchGroups();
