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

    function fetchCustomers(page = 1) {
        fetch(`backend/get_customers.php?page=${page}&limit=${limit}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.customers.length > 0) {
                    updateTable(data.customers);
                    updatePagination(data.total, data.page, data.limit);
                } else {
                    const ordersTableBody = document.querySelector('#ordersTable tbody');
                    ordersTableBody.innerHTML = '';
                    const noOrderRow = document.createElement('tr');
                    noOrderRow.innerHTML = `<td colspan="6" style="text-align:center;">No Customer Record Found</td>`;
                    ordersTableBody.appendChild(noOrderRow);
                    console.error('Failed to fetch Customers:', data.message);
                }
            })
            .catch(error => console.error('Error fetching data:', error));
    }

    function updateTable(customers) {
        const ordersTableBody = document.querySelector('#ordersTable tbody');
        ordersTableBody.innerHTML = '';
        customers.forEach(customer => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${customer.firstname}</td>
                <td>${customer.lastname}</td>
                <td>${maskDetails(customer.mobile_number)}</td>
                <td>${maskDetails(customer.email)}</td>
                <td>${customer.gender}</td>
                <td><button class="view-details-btn" data-customer-id="${customer.customer_id}">View Details</button></td>
            `;
            ordersTableBody.appendChild(row);
        });

        // Attach event listeners to the view details buttons
        document.querySelectorAll('.view-details-btn').forEach(button => {
            button.addEventListener('click', (event) => {
                const customerId = event.target.getAttribute('data-customer-id');
                fetchCustomerDetails(customerId);
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
                fetchCustomers(page);
            });
            paginationContainer.appendChild(pageButton);
        }
    }

    function fetchCustomerDetails(customerId) {
        fetch(`backend/fetch_customer_details.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ customer_id: customerId })
        })
            .then(response => response.json())
            .then(data => {
                console.log(data); // Log the entire response to the console
                if (data.success) {
                    populateCustomerDetails(data.customer_details);
                    document.getElementById('orderModal').style.display = 'block';
                } else {
                    console.error('Failed to fetch Customer details:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching Customer details:', error);
            });
    }

    function populateCustomerDetails(customer_details) {
        const orderDetailsTable = document.querySelector('#orderDetailsTable tbody');
        const photoCell = document.querySelector('#driverPhoto');

        // Store original email and phone number in hidden fields
        const hiddenEmailInput = `<input type="hidden" id="originalEmail" value="${customer_details.email}">`;
        const hiddenPhoneNumberInput = `<input type="hidden" id="originalPhoneNumber" value="${customer_details.mobile_number}">`;

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
                <td>Date Onboarded</td>
                <td><input type="text" id="dateCreated" value="${customer_details.date_created}" disabled></td>
            </tr>
            <tr>
                <td>First Name</td>
                <td><input type="text" id="firstname" value="${customer_details.firstname}"></td>
            </tr>
            <tr>
                <td>Last Name</td>
                <td><input type="text" id="lastname" value="${customer_details.lastname}"></td>
            </tr>
            <tr>
                <td>Email</td>
                <td>
                    <input type="email" id="email" value="${maskDetails(customer_details.email)}">
                    ${hiddenEmailInput}
                    <div class="masking"> 
                        <input type="checkbox" id="toggleMaskingEmail" name="viewEmail">
                        <label id="emailLabel" for = "toggleMaskingEmail"> Show Email</label>
                    
                    </div>
                    
                </td>
            </tr>
            <tr>
                <td>Phone Number</td>
                <td>
                    <input type="text" id="phoneNumber" value="${maskDetails(customer_details.mobile_number)}">
                     ${hiddenPhoneNumberInput}
                    <div class="masking">
                        <input type="checkbox" id="toggleMaskingPhone" name="viewPhone">
                        <label id="phoneLabel" for = "toggleMaskingPhone">Show Phone Number</label>
                    </div>
                </td>
            </tr>
            <tr>
                <td>Gender</td>
                <td>
                    <select id="gender">
                        <option value="Male" ${customer_details.gender === 'Male' ? 'selected' : ''}>Male</option>
                        <option value="Female" ${customer_details.gender === 'Female' ? 'selected' : ''}>Female</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td>House Address</td>
                <td><input type="text" id="address" value="${customer_details.address}"></td>
            </tr>
            <tr>
                <td>Customer ID</td>
                <td><input type="text" id="licenseNumber" value="${customer_details.customer_id}" disabled></td>
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
            <td>Select Unit</td>
            <td>
                <select id="selectUnit" class="selectedUnit">
                    <option value="">--Select a Unit--</option>
                    <!-- Unit options will be populated dynamically based on the group -->
                </select>
            </td>
        </tr>
            
        `;

        // Display photo if available
        if (customer_details.photo) {
            const photo = customer_details.photo;
            photoCell.innerHTML = `<img src="backend/customer_photos/${photo}" alt="Driver Photo" class="driver-photo">`;
        } else {
            photoCell.innerHTML = `<p>No photo available</p>`;
        }


        // Add "Update" and "Delete" buttons below the table for performing actions
        const actionButtons = `
            <tr>
                <td colspan="2" style="text-align: center;">
                    <button id="updateDriverBtn">Update</button>
                    <button id="deleteDriverBtn">Delete</button>
                </td>
            </tr>
        `;
        orderDetailsTable.innerHTML += actionButtons;

        // Load groups and preselect the group the customer belongs to
        loadGroups(customer_details.group_id);

        // Pre-load the units based on the customer's group and preselect the correct unit
        loadUnits(customer_details.group_id, customer_details.unit_id);

        document.getElementById('selectGroup').addEventListener('change', function () {
            const groupId = this.value;
            const selectedUnitId = customer_details.unit_id; // Make sure this is available in the scope
            loadUnits(groupId, selectedUnitId);
        });

        // Attach event listeners for checkbox toggle
        toggleMasking('toggleMaskingPhone', 'phoneNumber', 'originalPhoneNumber', 'phoneLabel');
        toggleMasking('toggleMaskingEmail', 'email', 'originalEmail', 'emailLabel');

        // Event listeners for update and delete buttons
        document.getElementById('updateDriverBtn').addEventListener('click', () => {
            updateCustomer(customer_details.customer_id);
        });

        document.getElementById('deleteDriverBtn').addEventListener('click', () => {
            deleteCustomer(customer_details.customer_id);
        });
    }

    // Function to load groups and populate the select dropdown
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


    // Function to load Units and populate the select dropdown
    function loadUnits(groupId, selectedUnitId = null) {
        fetch('backend/fetch_units.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ group_id: groupId })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const unitSelect = document.getElementById('selectUnit');
                    unitSelect.innerHTML = '<option value="">--Select a Unit--</option>';

                    data.units.forEach(unit => {
                        const option = document.createElement('option');
                        option.value = unit.unit_id;
                        option.textContent = unit.unit_name;

                        // Pre-select the group if it's the current one
                        if (unit.unit_id === selectedUnitId) {
                            option.selected = true;
                        }

                        unitSelect.appendChild(option);
                    });
                } else {
                    console.error('Error:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching Units:', error);
            });
    }

    function updateCustomer(customerId) {
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

        const customerData = {
            customer_id: customerId,
            firstname: document.getElementById('firstname').value,
            lastname: document.getElementById('lastname').value,
            email: emailToSend,
            phone_number: phoneNumberToSend,
            gender: document.getElementById('gender').value,
            address: document.getElementById('address').value,
            group: document.getElementById('selectGroup').value,
            unit: document.getElementById('selectUnit').value
        };

        fetch('backend/update_customer.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(customerData)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Customer Details has been updated successfully.');
                    document.getElementById('orderModal').style.display = 'none';
                    fetchCustomers(currentPage); // Refresh the table after update
                } else {
                    alert('Failed to update Customer Records: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error updating Customer Records:', error);
            });
    }


    function deleteCustomer(customerId) {
        if (confirm('Are you sure you want to delete this customer?')) {
            fetch('backend/delete_customer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ customer_id: customerId })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Customer has been successfully deleted!');
                        document.getElementById('orderModal').style.display = 'none';
                        fetchCustomers(currentPage); // Refresh the driver list
                    } else {
                        console.error('Failed to delete Customer:', data.message);
                        alert('Failed to delete Customer:', data.message)
                    }
                })
                .catch(error => {
                    console.error('Error deleting Customer:', error);
                });
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
    fetchCustomers(currentPage);


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


    // Function to load Units and populate the select dropdown
    function loadUnitsOnboarding(groupId) {
        fetch('backend/fetch_units.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ group_id: groupId })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const unitSelect = document.getElementById('selectedUnit');
                    unitSelect.innerHTML = '<option value="">--Select a Unit--</option>';

                    data.units.forEach(unit => {
                        const option = document.createElement('option');
                        option.value = unit.unit_id;
                        option.textContent = unit.unit_name;

                        unitSelect.appendChild(option);
                    });
                } else {
                    console.error('Error:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching Units:', error);
            });
    }
    document.getElementById('selectedGroup').addEventListener('change', function () {
        const groupId = this.value;
        const selectedUnitId = document.getElementById('selectedUnit').value;
        loadUnitsOnboarding(groupId, selectedUnitId);
    });

    // Module to add new customer
    // Add New Driver form submission
    const addCustomerForm = document.getElementById('addCustomerForm');
    function handleFormSubmission(form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const formData = new FormData(this);
            const messageDiv = document.getElementById('addCustomerMessage');

            fetch('backend/add_customer.php', {
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
                        messageDiv.textContent = 'Customer has been successfully Onboarded!';
                        messageDiv.style.color = 'green';
                        messageDiv.style.fontSize = '12';
                        alert('Customer has been successfully Onboarded!')
                        location.reload();
                        // window.location.href = '../../Transaction_manager/dashboard.php';
                    } else {
                        console.log('Error:', data.message);
                        alert('Error', data.message);
                        messageDiv.textContent = data.message;
                        messageDiv.style.color = 'red';
                        messageDiv.style.fontSize = '12';
                        // alert(data.message)
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error:', error);
                    messageDiv.textContent = 'Error: ' + error.message;
                    messageDiv.style.color = 'red';
                    messageDiv.style.fontSize = '12';
                    alert('An error occurred. Please Try Again Later')
                });
        });
    }
    loadGroupsOnboarding();
    handleFormSubmission(addCustomerForm);
});
