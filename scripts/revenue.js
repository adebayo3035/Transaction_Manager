function toggleModal(modalId) {
    let modal = document.getElementById(modalId);
    modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";

}
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

document.addEventListener('DOMContentLoaded', () => {

  
    function fetchRevenueTypes() {
        fetch(`backend/fetch_revenue_types.php`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateTable(data.revenueTypes);
                } else {
                    console.error('Failed to fetch Revenue Types Records:', data.message);
                }
            })
            .catch(error => console.error('Error fetching data:', error));
    }

    function updateTable(revenueTypes) {
        const ordersTableBody = document.querySelector('#ordersTable tbody');
        ordersTableBody.innerHTML = '';
        revenueTypes.forEach(revenueType => {

            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${revenueType.revenue_type_id}</td>
                <td>${revenueType.revenue_type_name}</td>
                <td>${revenueType.revenue_type_description}</td>
                <td> <span class='edit-icon' data-revenue-id = "${revenueType.revenue_type_id}">&#9998;</span></td>
                <td> <span class='delete-icon' data-revenue-id = "${revenueType.revenue_type_id}">&#128465;</span></td>
                
            `;
            ordersTableBody.appendChild(row);
        });

        // Attach event listeners to the edit icon buttons
        document.querySelectorAll('.edit-icon').forEach(span => {
            span.addEventListener('click', (event) => {
                const revenueId = event.target.getAttribute('data-revenue-id');
                fetchRevenueTypeDetails(revenueId);
            });
        });

         // Attach event listeners to the delete icon buttons
         document.querySelectorAll('.delete-icon').forEach(span => {
            // span.style.display = "none";
            span.addEventListener('click', (event) => {
                const revenueId = event.target.getAttribute('data-revenue-id');
                deleteRevenueType(revenueId);
            });
        });
    }

    function fetchRevenueTypeDetails(revenueId) {
        fetch(`backend/revenue_type_details.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ revenue_id: revenueId })
        })
            .then(response => response.json())
            .then(data => {
                console.log(data); // Log the entire response to the console
                if (data.success) {
                    populateRevenueDetails(data.revenueType_details);
                    document.getElementById('orderModal').style.display = 'block';
                } else {
                    console.error('Failed to fetch Group details:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching Group details:', error);
            });
    }

    function populateRevenueDetails(revenueType_details){
        const orderDetailsTable = document.querySelector('#orderDetailsTable tbody');
        
        orderDetailsTable.innerHTML = `
        <tr>
            <td>Revenue ID</td>
            <td><input type="text" id="revenue_id" value="${revenueType_details.revenue_type_id}" disabled></td>
        </tr>
        <tr>
            <td>Revenue Name</td>
            <td><input type="text" id="revenue_name" value="${revenueType_details.revenue_type_name}"></td>
        </tr>
        <tr>
            <td>Revenue Description</td>
            <td><input type="text" id="revenue_description" value="${revenueType_details.revenue_type_description}"></td>
        </tr>
        <tr>
                <td colspan="2" style="text-align: center;">
                    <button id="updateRevenueBtn">Update</button>
                </td>
            </tr>
        `;
        // Event listeners for update and delete buttons
        document.getElementById('updateRevenueBtn').addEventListener('click', () => {
            updateRevenueType(revenueType_details.revenue_type_id);
        });
    }

    function updateRevenueType(revenueId) {
        const revenueData = {
            revenue_id: revenueId,
            revenue_name: document.getElementById('revenue_name').value,
            revenue_description: document.getElementById('revenue_description').value
        };
        if (confirm('Are you sure you want to Update this Revenue Type?')){
            fetch('backend/update_revenue_type.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(revenueData)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Revenue Type Details has been updated successfully.');
                        document.getElementById('orderModal').style.display = 'none';
                       location.reload();
                    } else {
                        alert('Failed to update Revenue Type Details: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error updating Revenue Details:', error);
                });
        }
        
    }

    function deleteRevenueType(revenueId) {
        if (confirm('Are you sure you want to delete this Revenue Type?')) {
            fetch('backend/delete_revenue_type.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ revenue_id: revenueId })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Revenue Type has been successfully deleted!');
                        
                       location.reload();
                    } else {
                        console.error('Failed to delete Revenue Type:', data.message);
                        alert('Failed to delete Revenue Type:' + data.message)
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
    fetchRevenueTypes();
});
