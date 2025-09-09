document.addEventListener('DOMContentLoaded', () => {
    // Configuration constants
    const CONFIG = {
        itemsPerPage: 10,
        minSpinnerTime: 1000,
        modalIds: {
            order: 'orderModal',
            customer: 'customerModal'
        }
    };


    // State management
    const state = {
        currentPage: 1,
        currentCustomerId: null
    };

    // DOM Elements
    const elements = {
        tables: {
            customers: {
                body: document.querySelector('#ordersTable tbody'),
                container: document.getElementById('ordersTableBody')
            },
            details: {
                body: document.querySelector('#orderDetailsTable tbody'),
                photoCell: document.querySelector('#driverPhoto')
            }
        },
        pagination: document.getElementById('pagination'),
        search: document.getElementById('liveSearch'),
        modals: {
            order: document.getElementById(CONFIG.modalIds.order),
            customer: document.getElementById(CONFIG.modalIds.customer)
        },
        forms: {
            addCustomer: document.getElementById('addCustomerForm'),
            groupSelect: document.getElementById('selectedGroup'),
            unitSelect: document.getElementById('selectedUnit')
        },
        messages: {
            addCustomer: document.getElementById('addCustomerMessage')
        }
    };

    // Utility functions
    const utils = {
        maskDetails: (details) => details.slice(0, 2) + ' ** ** ' + details.slice(-3),

        showSpinner: (container) => {
            container.innerHTML = `
                <tr>
                    <td colspan="6" class="spinner-container">
                        <div class="spinner"></div>
                    </td>
                </tr>
            `;
        },

        showError: (container, message = 'Error loading data') => {
            container.innerHTML = `
                <tr>
                    <td colspan="6" class="error-message">${message}</td>
                </tr>
            `;
        },

        toggleModal: (modalId) => {
            const modal = document.getElementById(modalId);
            modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";
        },

        closeModal: (event) => {
            const modal = event.target.closest('.modal');
            if (modal) modal.style.display = 'none';
        },

        handleOutsideClick: (event) => {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        },

        createButton: (label, page, disabled = false, active = false) => {
            const btn = document.createElement('button');
            btn.textContent = label;
            btn.disabled = disabled;
            if (active) btn.classList.add('active');
            btn.addEventListener('click', () => api.fetchCustomers(page));
            return btn;
        }
    };

    // API Functions
    const api = {
        fetchCustomers: (page = 1) => {
            utils.showSpinner(elements.tables.customers.container);

            // Get filter values from dropdowns
            const gender = document.getElementById('filterGender').value;
            const restriction = document.getElementById('filterRestriction').value;
            const delete_status = document.getElementById('filterDelete').value;

            const params = new URLSearchParams({
                page,
                limit: CONFIG.itemsPerPage
            });

            // Add filters only if selected
            if (gender) params.append('gender', gender);
            if (restriction) params.append('restriction', restriction);
            if (delete_status) params.append('delete_status', delete_status);

            const minDelay = new Promise(resolve => setTimeout(resolve, CONFIG.minSpinnerTime));
            const fetchData = fetch(`backend/get_customers.php?${params.toString()}`)
                .then(res => res.json());

            Promise.all([fetchData, minDelay])
                .then(([data]) => {
                    if (data.success && data.customers.length > 0) {
                        ui.updateCustomersTable(data.customers);
                        ui.updatePagination(data.pagination.total, data.pagination.page, data.pagination.limit);
                    } else {
                        utils.showError(elements.tables.customers.container, 'No Customer Details at the moment');
                    }
                })
                .catch(() => {
                    utils.showError(elements.tables.customers.container, 'Error loading Customer data');
                });
        },


        fetchCustomerDetails: (customerId) => {
            return fetch(`backend/fetch_customer_details.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ customer_id: customerId })
            })
                .then(response => response.json());
        },

        updateCustomer: (customerData) => {
            return fetch('backend/update_customer.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(customerData)
            })
                .then(response => response.json());
        },

        deleteCustomer: (customerId) => {
            return fetch('backend/delete_customer.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ customer_id: customerId })
            })
                .then(response => response.json());
        },

        fetchGroups: () => {
            return fetch('backend/fetch_groups.php')
                .then(response => response.json());
        },

        fetchUnits: (groupId) => {
            return fetch('backend/fetch_units.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ group_id: groupId })
            })
                .then(response => response.json());
        },

        addCustomer: (formData) => {
            return fetch('backend/add_customer.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json());
        }
    };

    // UI Functions
    const ui = {
        updateCustomersTable: (customers) => {
            elements.tables.customers.body.innerHTML = '';

            customers.forEach(customer => {
                const row = document.createElement('tr');
                const restrictionStatus = customer.restriction === 1 ? 'Restricted' : 'Not Restricted';
                const accountStatus = customer.delete_status === 'Yes' ? 'Deactivated' : 'Activated';
                const restrictionClass = customer.restriction === 1 ? 'restricted-badge' : 'not-restricted-badge';
                const accountStatusClass = customer.delete_status === 'Yes' ? 'restricted-badge' : 'not-restricted-badge';
                const isDeactivated = customer.delete_status == 'Yes';
                const isRestricted = customer.restriction == 1;

                const showViewOnly = isDeactivated || isRestricted;

                row.innerHTML = `
    <td>${customer.firstname}</td>
    <td>${customer.lastname}</td>
    <td>${customer.gender}</td>
    <td><span class="${restrictionClass}">${restrictionStatus}</span></td>
    <td><span class="${accountStatusClass}">${accountStatus}</span></td>
    <td>
        <button class="view-details-btn" data-customer-id="${customer.customer_id}">
            ${showViewOnly ? 'View Details' : 'Edit Details'}
        </button>
    </td>
`;

                elements.tables.customers.body.appendChild(row);
            });

            // Attach event listeners to view details buttons
            document.querySelectorAll('.view-details-btn').forEach(button => {
                button.addEventListener('click', (event) => {
                    state.currentCustomerId = event.target.getAttribute('data-customer-id');
                    customer.loadCustomerDetails(state.currentCustomerId);
                });
            });
        },

        updatePagination: (totalItems, currentPage, itemsPerPage) => {
            elements.pagination.innerHTML = '';
            const totalPages = Math.ceil(totalItems / itemsPerPage);

            // First and Previous buttons
            elements.pagination.appendChild(utils.createButton('« First', 1, currentPage === 1));
            elements.pagination.appendChild(utils.createButton('‹ Prev', currentPage - 1, currentPage === 1));

            // Page numbers
            const maxVisible = 2;
            const start = Math.max(1, currentPage - maxVisible);
            const end = Math.min(totalPages, currentPage + maxVisible);

            for (let i = start; i <= end; i++) {
                const btn = utils.createButton(i, i, false, i === currentPage);
                btn.addEventListener('click', () => {
                    state.currentPage = i;
                    api.fetchCustomers(i);  // Changed from fetchCustomers to api.fetchCustomers
                });
                elements.pagination.appendChild(btn);
            }

            // Next and Last buttons
            const nextBtn = utils.createButton('Next ›', currentPage + 1, currentPage === totalPages);
            nextBtn.addEventListener('click', () => {
                state.currentPage = currentPage + 1;
                api.fetchCustomers(currentPage + 1);  // Changed from fetchCustomers to api.fetchCustomers
            });
            elements.pagination.appendChild(nextBtn);

            const lastBtn = utils.createButton('Last »', totalPages, currentPage === totalPages);
            lastBtn.addEventListener('click', () => {
                state.currentPage = totalPages;
                api.fetchCustomers(totalPages);  // Changed from fetchCustomers to api.fetchCustomers
            });
            elements.pagination.appendChild(lastBtn);
        },

        populateCustomerDetails: (customerDetails, userRole) => {
            const { body, photoCell } = elements.tables.details;

            body.innerHTML = `
                <tr>
                    <td>Date Onboarded</td>
                    <td><input type="text" id="dateCreated" value="${customerDetails.date_created}" disabled></td>
                </tr>
                <tr>
                    <td>First Name</td>
                    <td><input type="text" id="firstname" value="${customerDetails.firstname}"></td>
                </tr>
                <tr>
                    <td>Last Name</td>
                    <td><input type="text" id="lastname" value="${customerDetails.lastname}"></td>
                </tr>
                <tr>
                    <td>Email</td>
                    <td>
                        <input type="email" id="email" value="${utils.maskDetails(customerDetails.email)}">
                        <input type="hidden" id="originalEmail" value="${customerDetails.email}">
                        <div class="masking"> 
                            <input type="checkbox" id="toggleMaskingEmail" name="viewEmail">
                            <label id="emailLabel" for="toggleMaskingEmail">Show Email</label>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>Phone Number</td>
                    <td>
                        <input type="text" id="phoneNumber" value="${utils.maskDetails(customerDetails.mobile_number)}">
                        <input type="hidden" id="originalPhoneNumber" value="${customerDetails.mobile_number}">
                        <div class="masking">
                            <input type="checkbox" id="toggleMaskingPhone" name="viewPhone">
                            <label id="phoneLabel" for="toggleMaskingPhone">Show Phone Number</label>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>Gender</td>
                    <td>
                        <select id="gender">
                            <option value="Male" ${customerDetails.gender === 'Male' ? 'selected' : ''}>Male</option>
                            <option value="Female" ${customerDetails.gender === 'Female' ? 'selected' : ''}>Female</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>House Address</td>
                    <td><input type="text" id="address" value="${customerDetails.address}"></td>
                </tr>
                <tr>
                    <td>Customer ID</td>
                    <td><input type="text" id="licenseNumber" value="${customerDetails.customer_id}" disabled></td>
                </tr>
                <tr>
                    <td>Select Group</td>
                    <td>
                        <select id="selectGroup" class="selectGroup">
                            <option value="">--Select a Group--</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>Select Unit</td>
                    <td>
                        <select id="selectUnit" class="selectedUnit">
                            <option value="">--Select a Unit--</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>Restriction Status</td>
                    <td>
                        ${customerDetails.restriction === 1 ?
                    `<input type="text" value="Restricted" readonly class="restricted-input">` :
                    `<select id="restriction">
                                <option value="0" ${customerDetails.restriction === 0 ? 'selected' : ''}>Not Restricted</option>
                                <option value="1" ${customerDetails.restriction === 1 ? 'selected' : ''}>Restricted</option>
                            </select>`
                }
                    </td>
                </tr>
                <tr class="action-button-row">
    <td colspan="2" class="action-buttons">
        ${(customerDetails.delete_status !== 'Yes' && customerDetails.restriction !== 1)
                    ? (
                        userRole === 'Super Admin'
                            ? `<button id="updateCustomerBtn">Update</button>
                           <button id="deleteCustomerBtn">Deactivate</button>`
                            : userRole === 'Admin'
                                ? `<button id="updateCustomerBtn">Update</button>`
                                : ''
                    )
                    : ''
                }
    </td>
</tr>

            `;

            // Display photo
            photoCell.innerHTML = customerDetails.photo ?
                `<img src="backend/customer_photos/${customerDetails.photo}" alt="Driver Photo" class="driver-photo">` :
                `<p>No photo available</p>`;

            // Set up toggle masking
            customer.setupMaskingToggle(
                'toggleMaskingPhone',
                'phoneNumber',
                'originalPhoneNumber',
                'phoneLabel',
                'Phone Number'
            );

            customer.setupMaskingToggle(
                'toggleMaskingEmail',
                'email',
                'originalEmail',
                'emailLabel',
                'Email'
            );

            // Load groups and units
            customer.loadGroups(customerDetails.group_id);
            customer.loadUnits(customerDetails.group_id, customerDetails.unit_id);

            // Set up group change listener
            document.getElementById('selectGroup').addEventListener('change', function () {
                customer.loadUnits(this.value);
            });

            // Set up action buttons
            if (customerDetails.delete_status !== 'Yes') {
                // Set up action buttons
                const updateBtn = document.getElementById('updateCustomerBtn');
                if (updateBtn) {
                    updateBtn.addEventListener('click', () => {
                        customer.updateCustomer(state.currentCustomerId);
                    });
                }

                const deleteBtn = document.getElementById('deleteCustomerBtn');
                if (deleteBtn) {
                    deleteBtn.addEventListener('click', () => {
                        customer.deleteCustomer(state.currentCustomerId);
                    });
                }

            }

            if (userRole === 'Super Admin' && customerDetails.delete_status !== 'Yes') {
                const deleteBtn = document.getElementById('deleteCustomerBtn');
                if (deleteBtn) {
                    deleteBtn.addEventListener('click', () => {
                        customer.deleteCustomer(state.currentCustomerId);
                    });
                }
            }
        },

        // populateGroups: (groups, selectedGroupId = null, elementId = 'selectGroup') => {
        //     const groupSelect = document.getElementById(elementId);
        //     groupSelect.innerHTML = '<option value="">--Select a Group--</option>';

        //     groups.forEach(group => {
        //         const option = document.createElement('option');
        //         option.value = group.group_id;
        //         option.textContent = group.group_name;
        //         option.selected = group.group_id === selectedGroupId;
        //         groupSelect.appendChild(option);
        //     });
        // },

        populateGroups: (groups, selectedGroupId = null, elementId = 'selectGroup') => {
            const interval = setInterval(() => {
                const groupSelect = document.getElementById(elementId);
                if (groupSelect) {
                    clearInterval(interval);
                    groupSelect.innerHTML = '<option value="">--Select a Group--</option>';
                    groups.forEach(group => {
                        const option = document.createElement('option');
                        option.value = group.group_id;
                        option.textContent = group.group_name;
                        option.selected = group.group_id === selectedGroupId;
                        groupSelect.appendChild(option);
                    });
                }
            }, 100);
        },


        populateUnits: (units, selectedUnitId = null, elementId = 'selectUnit') => {
            const unitSelect = document.getElementById(elementId);
            unitSelect.innerHTML = '<option value="">--Select a Unit--</option>';

            units.forEach(unit => {
                const option = document.createElement('option');
                option.value = unit.unit_id;
                option.textContent = unit.unit_name;
                option.selected = unit.unit_id === selectedUnitId;
                unitSelect.appendChild(option);
            });
        }
    };

    // Customer Management Functions
    const customer = {
        loadCustomerDetails: (customerId) => {
            api.fetchCustomerDetails(customerId)
                .then(data => {
                    if (data.success) {
                        ui.populateCustomerDetails(data.customer_details, data.user_role);
                        utils.toggleModal(CONFIG.modalIds.order);
                    } else {
                        console.error('Failed to fetch Customer details:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error fetching Customer details:', error);
                });
        },

        setupMaskingToggle: (checkboxId, visibleInputId, hiddenInputId, labelId, fieldName) => {
            const checkbox = document.getElementById(checkboxId);
            const visibleInput = document.getElementById(visibleInputId);
            const hiddenInput = document.getElementById(hiddenInputId);
            const label = document.getElementById(labelId);

            checkbox.addEventListener('change', function () {
                if (this.checked) {
                    visibleInput.value = hiddenInput.value;
                    label.textContent = `Hide ${fieldName}`;
                } else {
                    visibleInput.value = utils.maskDetails(hiddenInput.value);
                    label.textContent = `Show ${fieldName}`;
                }
            });
        },

        loadGroups: (selectedGroupId = null) => {
            api.fetchGroups()
                .then(data => {
                    if (data.success) {
                        ui.populateGroups(data.groups, selectedGroupId);

                    } else {
                        console.error('Error:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error fetching groups:', error);
                });
        },

        loadUnits: (groupId, selectedUnitId = null) => {
            if (!groupId) return;

            api.fetchUnits(groupId)
                .then(data => {
                    if (data.success) {
                        ui.populateUnits(data.units, selectedUnitId);
                    } else {
                        console.error('Error:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error fetching Units:', error);
                });
        },

        updateCustomer: (customerId) => {
            const emailInput = document.getElementById('email').value;
            const phoneNumberInput = document.getElementById('phoneNumber').value;
            const originalEmail = document.getElementById('originalEmail').value;
            const originalPhoneNumber = document.getElementById('originalPhoneNumber').value;

            // Validate unmasked fields
            if (emailInput.includes('*') || phoneNumberInput.includes('*')) {
                alert('Please unmask all fields before updating.');
                return;
            }

            const customerData = {
                customer_id: customerId,
                firstname: document.getElementById('firstname').value,
                lastname: document.getElementById('lastname').value,
                email: emailInput === utils.maskDetails(originalEmail) ? originalEmail : emailInput,
                phone_number: phoneNumberInput === utils.maskDetails(originalPhoneNumber) ? originalPhoneNumber : phoneNumberInput,
                gender: document.getElementById('gender').value,
                address: document.getElementById('address').value,
                group: document.getElementById('selectGroup').value,
                unit: document.getElementById('selectUnit').value
            };

            // Only include restriction if it's editable
            const restrictionElement = document.getElementById('restriction');
            if (restrictionElement && restrictionElement.tagName === 'SELECT') {
                customerData.restriction = restrictionElement.value;
            }

            if (confirm('Are you sure you want to Update Customer Information?')) {
                api.updateCustomer(customerData)
                    .then(data => {
                        if (data.success) {
                            alert('Customer Details has been updated successfully.');
                            utils.toggleModal(CONFIG.modalIds.order);
                            api.fetchCustomers(state.currentPage);
                        } else {
                            alert('Failed to update Customer Records: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error updating Customer Records:', error);
                    });
            }
        },

        deleteCustomer: (customerId) => {
            if (confirm('Are you sure you want to delete this customer?')) {
                api.deleteCustomer(customerId)
                    .then(data => {
                        if (data.success) {
                            alert('Customer has been successfully deleted!');
                            utils.toggleModal(CONFIG.modalIds.order);
                            api.fetchCustomers(state.currentPage);
                        } else {
                            alert('Failed to delete Customer: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error deleting Customer:', error);
                    });
            }
        },

        setupAddCustomerForm: () => {
            elements.forms.addCustomer.addEventListener('submit', (event) => {
                event.preventDefault();

                if (confirm('Are you sure you want to add new Customer?')) {
                    const formData = new FormData(elements.forms.addCustomer);

                    api.addCustomer(formData)
                        .then(data => {
                            if (data.success) {
                                elements.messages.addCustomer.textContent = 'Customer has been successfully Onboarded!';
                                elements.messages.addCustomer.style.color = 'green';
                                alert('Customer has been successfully Onboarded!');
                                location.reload();
                            } else {
                                elements.messages.addCustomer.textContent = data.message;
                                elements.messages.addCustomer.style.color = 'red';
                                alert(data.message);
                            }
                        })
                        .catch(error => {
                            elements.messages.addCustomer.textContent = 'Error: ' + error.message;
                            elements.messages.addCustomer.style.color = 'red';
                            alert('An error occurred. Please Try Again Later');
                        });
                }
            });
        }
    };

    // Search functionality
    const search = {
        filterTable: () => {
            const searchTerm = elements.search.value.toLowerCase();
            const rows = document.querySelectorAll("#ordersTable tbody tr");

            rows.forEach(row => {
                let matchFound = false;
                const cells = row.getElementsByTagName("td");

                for (let i = 0; i < cells.length; i++) {
                    if (cells[i].textContent.toLowerCase().includes(searchTerm)) {
                        matchFound = true;
                        break;
                    }
                }

                row.style.display = matchFound ? "" : "none";
            });
        }
    };

    // Event Listeners
    const setupEventListeners = () => {
        // Modal close buttons
        document.querySelectorAll('.modal .close').forEach(btn => {
            btn.addEventListener('click', utils.closeModal);
        });

        // Outside click to close modals
        window.addEventListener('click', utils.handleOutsideClick);

        // Live search
        elements.search.addEventListener('input', search.filterTable);

        // Group change for onboarding form
        elements.forms.groupSelect.addEventListener('change', () => {
            customer.loadUnits(elements.forms.groupSelect.value, null, 'selectedUnit');
        });

        // Setup add customer form
        customer.setupAddCustomerForm();
    };

    // Initialize
    const init = () => {
        setupEventListeners();
        api.fetchCustomers(state.currentPage);
        customer.loadGroups(null, 'selectedGroup');
    };
    // 🎯 Apply Filter button event listener
    document.getElementById('applyCustomerFilters').addEventListener('click', () => {
        api.fetchCustomers(1); // fetch from first page with filters applied
    });

    init();
});