document.addEventListener('DOMContentLoaded', () => {
    const limit = 10; // Number of items per page
    let currentPage = 1;

    function maskDetails(details) {
        return details.slice(0, 2) + ' ** ** ' + details.slice(-3);
    }

    function fetchStaffs(page = 1) {
        fetch(`backend/get_staffs.php?page=${page}&limit=${limit}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateTable(data.staffs);
                    updatePagination(data.total, data.page, data.limit);
                } else {
                    console.error('Failed to fetch Staff Records:', data.message);
                }
            })
            .catch(error => console.error('Error fetching data:', error));
    }

    function updateTable(staffs) {
        const ordersTableBody = document.querySelector('#ordersTable tbody');
        ordersTableBody.innerHTML = '';
        staffs.forEach(staff => {
            const restrictionText = staff.restriction_id === 0 ? 'Not Restricted' : 'Restricted';
            const blockText = staff.block_id === 0 ? 'Not Blocked' : 'Blocked';

            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${staff.firstname}</td>
                <td>${staff.lastname}</td>
                <td>${maskDetails(staff.phone)}</td>
                <td>${maskDetails(staff.email)}</td>
                <td>${restrictionText}</td>
                <td>${blockText}</td>
                <td><button class="view-details-btn" data-staff-id="${staff.unique_id}">View Details</button></td>
            `;
            ordersTableBody.appendChild(row);
        });

        // Attach event listeners to the view details buttons
        document.querySelectorAll('.view-details-btn').forEach(button => {
            button.addEventListener('click', (event) => {
                const staffId = event.target.getAttribute('data-staff-id');
                fetchStaffDetails(staffId);
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
                fetchStaffs(page);
            });
            paginationContainer.appendChild(pageButton);
        }
    }

    function fetchStaffDetails(staffId) {
        fetch(`backend/fetch_staff_details.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ staff_id: staffId })
        })
            .then(response => response.json())
            .then(data => {
                console.log(data); // Log the entire response to the console
                if (data.success) {
                    populateStaffDetails(data.staff_details, data.logged_in_user_role);
                    console.log(data.logged_in_user_role);
                    document.getElementById('orderModal').style.display = 'block';
                } else {
                    console.error('Failed to fetch Staff details:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching Staff details:', error);
            });
    }

    function populateStaffDetails(staff_details, logged_in_user_role) {
        const orderDetailsTable = document.querySelector('#orderDetailsTable tbody');
        const photoCell = document.querySelector('#driverPhoto');

        // Store original email and phone number in hidden fields
        const hiddenEmailInput = `<input type="hidden" id="originalEmail" value="${staff_details.email}">`;
        const hiddenPhoneNumberInput = `<input type="hidden" id="originalPhoneNumber" value="${staff_details.phone}">`;

        // Disable input fields if the logged-in user is not a "Super Admin"
        const isSuperAdmin = logged_in_user_role === 'Super Admin';
        const disableAttribute = isSuperAdmin ? '' : 'disabled';


        // Function to unmask or mask based on checkbox state
        function toggleMasking(checkboxId, visibleInputId, hiddenInputId, labelId) {
            const checkbox = document.getElementById(checkboxId);
            const visibleInput = document.getElementById(visibleInputId);
            const hiddenInput = document.getElementById(hiddenInputId);
            const label = document.getElementById(labelId)

            checkbox.addEventListener('change', function () {
                if (this.checked) {
                    // Show unmasked value
                    visibleInput.value = hiddenInput.value;
                    label.textContent = `Hide ${visibleInputId}`
                } else {
                    // Show masked value
                    visibleInput.value = maskDetails(hiddenInput.value);
                    label.textContent = `Show ${visibleInputId}`
                }
            });
        }

        orderDetailsTable.innerHTML = `
        <tr>
            <td>Staff ID</td>
            <td><input type="text" id="staffID" value="${staff_details.unique_id}" disabled></td>
        </tr>
        <tr>
            <td>Date Onboarded</td>
            <td><input type="text" id="dateCreated" value="${staff_details.created_at}" disabled></td>
        </tr>
        <tr>
            <td>First Name</td>
            <td><input type="text" id="firstname" value="${staff_details.firstname}" ${disableAttribute}></td>
        </tr>
        <tr>
            <td>Last Name</td>
            <td><input type="text" id="lastname" value="${staff_details.lastname}" ${disableAttribute}></td>
        </tr>
        <tr>
            <td>Email</td>
            <td>
                <input type="email" id="email" value="${maskDetails(staff_details.email)}" ${disableAttribute}>
                ${hiddenEmailInput}
                <div class="masking"> 
                    <input type="checkbox" id="toggleMaskingEmail" name="viewEmail" ${disableAttribute}>
                    <label id="emailLabel" for="toggleMaskingEmail"> Show Email</label>
                </div>
            </td>
        </tr>
        <tr>
            <td>Phone Number</td>
            <td>
                <input type="text" id="phoneNumber" value="${maskDetails(staff_details.phone)}" ${disableAttribute}>
                ${hiddenPhoneNumberInput}
                <div class="masking">
                    <input type="checkbox" id="toggleMaskingPhone" name="viewPhone" ${disableAttribute}>
                    <label id="phoneLabel" for="toggleMaskingPhone">Show Phone Number</label>
                </div>
            </td>
        </tr>
        <tr>
            <td>Gender</td>
            <td>
                <select id="gender" ${disableAttribute}>
                    <option value="Male" ${staff_details.gender === 'Male' ? 'selected' : ''}>Male</option>
                    <option value="Female" ${staff_details.gender === 'Female' ? 'selected' : ''}>Female</option>
                </select>
            </td>
        </tr>
        <tr>
            <td>Staff Role</td>
            <td>
                <select id="role" ${disableAttribute}>
                    <option value="Admin" ${staff_details.role === 'Admin' ? 'selected' : ''}>Admin</option>
                    <option value="Super Admin" ${staff_details.role === 'Super Admin' ? 'selected' : ''}>Super Admin</option>
                </select>
            </td>
        </tr>
         <tr>
            <td>Address</td>
            <td><input type="text" id="address" value="${staff_details.address}" ${disableAttribute}></td>
        </tr>
    `;

        // Display photo if available
        if (staff_details.photo) {
            const photo = staff_details.photo;
            photoCell.innerHTML = `<img src="backend/admin_photos/${photo}" alt="Staff Photo" class="driver-photo">`;
        } else {
            photoCell.innerHTML = `<p>No photo available</p>`;
        }

        // Conditionally add "Update" and "Delete" buttons if the logged-in user is a "Super Admin"
        if (isSuperAdmin) {
            const actionButtons = `
            <tr>
                <td colspan="2" style="text-align: center;">
                    <button id="updateStaffBtn">Update</button>
                    <button id="deleteStaffBtn">Delete</button>
                </td>
            </tr>
        `;

            // Display photo if available
            if (staff_details.photo) {
                const photo = staff_details.photo;
                photoCell.innerHTML = `<img src="backend/admin_photos/${photo}" alt="Staff Photo" class="driver-photo">`;
            } else {
                photoCell.innerHTML = `<p>No photo available</p>`;
            }
            orderDetailsTable.innerHTML += actionButtons;


            // Attach event listeners for checkbox toggle
            toggleMasking('toggleMaskingPhone', 'phoneNumber', 'originalPhoneNumber', 'phoneLabel');
            toggleMasking('toggleMaskingEmail', 'email', 'originalEmail', 'emailLabel');

            // Event listeners for update and delete buttons
            document.getElementById('updateStaffBtn').addEventListener('click', () => {
                updateStaff(staff_details.unique_id);
            });

            document.getElementById('deleteStaffBtn').addEventListener('click', () => {
                deleteStaff(staff_details.unique_id);
            });
        }
    }

    function updateStaff(staffId) {
        const emailInput = document.getElementById('email').value;
        const phoneNumberInput = document.getElementById('phoneNumber').value;

        // Get the original values
        const originalEmail = document.getElementById('originalEmail').value;
        const originalPhoneNumber = document.getElementById('originalPhoneNumber').value;

        // Validation: Check if the input still contains masked characters (e.g., "***")
        if (emailInput.includes('*')) {
            alert('Please Unmask the email address before updating.');
            return;
        }
        if (phoneNumberInput.includes('*')) {
            alert('Please unmask the phone number before updating.');
            return;
        }

        // Determine whether the user has edited the email or phone number
        const emailToSend = emailInput === maskDetails(originalEmail) ? originalEmail : emailInput;
        const phoneNumberToSend = phoneNumberInput === maskDetails(originalPhoneNumber) ? originalPhoneNumber : phoneNumberInput;

        const staffData = {
            staff_id: staffId,
            firstname: document.getElementById('firstname').value,
            lastname: document.getElementById('lastname').value,
            email: emailToSend,
            phone_number: phoneNumberToSend,
            role: document.getElementById('role').value,
            gender: document.getElementById('gender').value,
            address: document.getElementById('address').value
        };

        fetch('backend/update_staff_profile.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(staffData)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Staff Details has been updated successfully.');
                    document.getElementById('orderModal').style.display = 'none';
                    fetchStaffs(currentPage); // Refresh the table after update
                } else {
                    alert('Failed to update Staff Records: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error updating Staff Record:', error);
            });
    }


    function deleteStaff(staffId) {
        if (confirm('Are you sure you want to delete this Staff?')) {
            fetch('backend/delete_staff.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ staff_id: staffId })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Staff has been successfully deleted!');
                        document.getElementById('orderModal').style.display = 'none';
                        fetchStaffs(currentPage); // Refresh the driver list
                    } else {
                        console.error('Failed to delete Staff Records:', data.message);
                        alert('Failed to delete Staff Records:', data.message)
                    }
                })
                .catch(error => {
                    console.error('Error deleting Staff Records:', error);
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

    // Fetch initial drivers data
    fetchStaffs(currentPage);
});
