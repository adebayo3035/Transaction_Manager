function toggleModal(modalId) {
    let modal = document.getElementById(modalId);
    modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";
}
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
                    updateTable(data.staffs, data.logged_in_user_role);
                    updatePagination(data.total, data.page, data.limit);
                    const adminActionBtn = document.getElementById('customer-form');

                    // Check for Super Admin privileges and add action buttons
                    if (data.logged_in_user_role === "Super Admin") {
                        const actionButtons = [
                            {
                                href: "staff_reactivation_request.php",
                                className: "admin-action-btn btn-view-deactivated",
                                text: "View Pending Reactivation Request",
                                color: "#2c3e50" // Dark blue-gray
                            },
                            {
                                href: "staff_deactivation_reactivation_history.php",
                                className: "admin-action-btn btn-deactivation-records",
                                text: "View Deactivation and Reactivation History",
                                color: "#e67e22" // Orange
                            }
                        ];

                        // Add buttons only if they don't already exist
                        actionButtons.forEach(button => {
                            if (!adminActionBtn.querySelector(`.${button.className}`)) {
                                const btn = document.createElement('a');
                                btn.href = button.href;
                                btn.className = button.className;
                                btn.textContent = button.text;

                                // Apply consistent button styling
                                btn.style.cssText = `
                                    display: inline-block;
                                    padding: 10px 15px;
                                    margin: 0 10px 10px 0;
                                    border-radius: 4px;
                                    color: white;
                                    background-color: ${button.color};
                                    text-decoration: none;
                                    font-weight: 500;
                                    transition: all 0.3s ease;
                                    border: none;
                                    cursor: pointer;
                                `;

                                // Add hover effect
                                btn.addEventListener('mouseenter', () => {
                                    btn.style.opacity = '0.9';
                                    btn.style.transform = 'translateY(-2px)';
                                });
                                btn.addEventListener('mouseleave', () => {
                                    btn.style.opacity = '1';
                                    btn.style.transform = 'translateY(0)';
                                });

                                adminActionBtn.appendChild(btn);
                            }
                        });
                    } else if (data.message) {
                        console.error('Failed to fetch Staff Records:', data.message);
                    }
                } else {
                    console.error('Failed to fetch Staff Records:', data.message);
                }
            })
            .catch(error => console.error('Error fetching data:', error));
    }

    function updateTable(staffs, loggedInUserRole) {
        const ordersTableBody = document.querySelector('#ordersTable tbody');
        ordersTableBody.innerHTML = '';

        staffs.forEach(staff => {
            const restrictionText = staff.restriction_id === 0 ? 'Not Restricted' : 'Restricted';
            const blockText = staff.block_id === 0 ? 'Not Blocked' : 'Blocked';

            // Conditionally set the Edit/View button text
            const buttonText = loggedInUserRole === 'Admin' ? 'View Details' : 'Edit Details';

            const showDeactivateBtn = staff.role === 'Admin' && loggedInUserRole === 'Super Admin';
            const deactivateBtnHtml = showDeactivateBtn
                ? `<td><button class="deactivate-staff" data-staff-id="${staff.unique_id}">Deactivate</button></td>`
                : '<td></td>';

            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${staff.firstname}</td>
                <td>${staff.lastname}</td>
                <td>${maskDetails(staff.phone)}</td>
                <td>${maskDetails(staff.email)}</td>
                <td>${restrictionText}</td>
                <td>${blockText}</td>
                <td>${staff.admin_status}</td>
                <td><button class="view-details-btn" data-staff-id="${staff.unique_id}">${buttonText}</button></td>
                ${deactivateBtnHtml}
            `;
            ordersTableBody.appendChild(row);
        });

        // Attach event listeners to the view/edit buttons
        document.querySelectorAll('.view-details-btn').forEach(button => {
            button.addEventListener('click', event => {
                const staffId = event.target.getAttribute('data-staff-id');
                fetchStaffDetails(staffId);
            });
        });

        // Attach event listeners to deactivate buttons
        document.querySelectorAll('.deactivate-staff').forEach(button => {
            button.addEventListener('click', event => {
                const staffId = event.target.getAttribute('data-staff-id');
                fetchAdminDetails(staffId);
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

    // function to fetch admin details for Deactivation
    function fetchAdminDetails(staffId) {
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
                    populateAdminDetails(data.staff_details, data.logged_in_user_role);
                    console.log(data.logged_in_user_role);
                    document.getElementById('staffModal').style.display = 'block';
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

        // Disable all input fields if the logged-in user is not a "Super Admin"
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
                    <button id="updateStaffBtn">Update Record</button>
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

            // 1. Get the button reference (do this once at page load)
            // const deleteStaffBtn = document.getElementById('deleteStaffBtn');

            // // 2. Add event listener with proper error handling
            // deleteStaffBtn.addEventListener('click', async () => {
            //     try {
            //         // Assuming staff_details is defined in your scope
            //         if (!staff_details?.unique_id) {
            //             throw new Error('No staff member selected');
            //         }

            //         await deleteStaff(staff_details.unique_id);

            //     } catch (error) {
            //         console.error('Delete Staff Handler Error:', error);
            //         alert('Error', error.message || 'Failed to initiate staff deletion');
            //     }
            // });
        }
    }

    function populateAdminDetails(staff_details) {
        const staffDetailsTable = document.querySelector('#staffDetailsTable tbody');
        const staffPhotoCell = document.querySelector('#staffPhoto');

        // Clear existing content
        staffDetailsTable.innerHTML = '';

        // Create staff details rows
        staffDetailsTable.innerHTML = `
            <tr>
                <td>Staff ID</td>
                <td><input type="text" id="staffID" value="${staff_details.unique_id || ''}" disabled></td>
            </tr>
            <tr>
                <td>First Name</td>
                <td><input type="text" id="firstname" value="${staff_details.firstname || ''}" disabled></td>
            </tr>
            <tr>
                <td>Last Name</td>
                <td><input type="text" id="lastname" value="${staff_details.lastname || ''}" disabled></td>
            </tr>
            <tr>
                <td>Deactivation Reason</td>
                <td>
                    <textarea id="deactivationReason" placeholder="Enter reason here..."></textarea>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="text-align: center;">
                    <button id="deActivateStaffBtn">Deactivate</button>
                </td>
            </tr>
        `;

        // Display photo if available
        if (staff_details.photo) {
            staffPhotoCell.innerHTML = `<img src="backend/admin_photos/${staff_details.photo}" alt="Staff Photo" class="driver-photo">`;
        } else {
            staffPhotoCell.innerHTML = `<p>No photo available</p>`;
        }

        // Deactivation button handler
        const deActivateStaffBtn = document.getElementById('deActivateStaffBtn');
        deActivateStaffBtn.addEventListener('click', async () => {
            try {
                const deactivationReason = document.getElementById('deactivationReason');
                const reason = deactivationReason.value.trim();

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


    async function deleteStaff(staffId, reason) {
        const confirmation = confirm("Proceed to Deactivate Staff Account?");
        if (!confirmation) return;

        // Get references to the elements
        const deActivateStaffBtn = document.getElementById('deActivateStaffBtn');
        const deactivationReason = document.getElementById('deactivationReason');

        try {
            // Disable elements and show loading state
            deActivateStaffBtn.disabled = true;
            deactivationReason.disabled = true;
            deActivateStaffBtn.textContent = 'Processing...';

            const response = await fetch('backend/delete_staff.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ staff_id: staffId, reason: reason })
            });

            const result = await response.json();

            if (response.ok) {
                alert(result.success);
                location.reload(); // Reload page after successful deletion
            } else {
                alert(result.error);
                console.log(result.error);
            }
        } catch (error) {
            console.error("Error deleting user:", error);
            alert("An error occurred while trying to delete the user.");
        } finally {
            // Re-enable elements regardless of success/failure
            deActivateStaffBtn.disabled = false;
            deactivationReason.disabled = false;
            deActivateStaffBtn.textContent = 'Deactivate Staff';
        }
    }


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

    // Add New Staff form submission
    const addStaffForm = document.getElementById('addStaffForm');
    const inputs = addStaffForm.querySelectorAll('input, select');
    const submitBtn = document.getElementById('submitBtn');

    function checkFormCompletion() {
        let allFilled = true;
        inputs.forEach(input => {
            if (input.type !== 'hidden' && (input.type !== 'file' ? !input.value : !input.files.length)) {
                allFilled = false;
            }
        });
        submitBtn.style.visibility = allFilled ? 'visible' : 'hidden';
    }
    inputs.forEach(input => {
        input.addEventListener('input', checkFormCompletion);
        input.addEventListener('change', checkFormCompletion);
    });

    window.onload = checkFormCompletion;
    function handleFormSubmission(form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const formData = new FormData(this);
            const messageDiv = document.getElementById('addStaffMessage');

            fetch('backend/admin_onboarding.php', {
                method: 'POST',
                // headers: {
                //     'Content-Type': 'application/x-www-form-urlencoded'
                // },
                body: formData,
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Success:', data.message);
                        messageDiv.textContent = 'Staff has been successfully Onboarded!';
                        alert('Staff has been successfully Onboarded!')
                        location.reload();
                        // window.location.href = '../../Transaction_manager/dashboard.php';
                    } else {
                        console.log('Error:', data.message);
                        alert('Error:', data.message);
                        messageDiv.textContent = data.message;
                        // alert(data.message)
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    messageDiv.textContent = 'Error: ' + error.message;
                    alert('An error occurred. Please Try Again Later')
                });
        });
    }
    handleFormSubmission(addStaffForm);
});
