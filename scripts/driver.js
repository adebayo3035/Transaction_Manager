// Function to toggle modals for adding new driver
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

    function fetchDrivers(page = 1) {
        fetch(`backend/get_drivers.php?page=${page}&limit=${limit}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.drivers.length > 0) {
                    updateTable(data.drivers);
                    updatePagination(data.total, data.page, data.limit);
                } else {
                    const ordersTableBody = document.querySelector('#ordersTable tbody');
                    ordersTableBody.innerHTML = '';
                    const noOrderRow = document.createElement('tr');
                    noOrderRow.innerHTML = `<td colspan="7" style="text-align:center;">Drivers Record Cannot be Found</td>`;
                    ordersTableBody.appendChild(noOrderRow);
                    console.error('Failed to fetch Drivers:', data.message);
                }
            })
            .catch(error => console.error('Error fetching data:', error));
    }

    function updateTable(drivers) {
        const ordersTableBody = document.querySelector('#ordersTable tbody');
        ordersTableBody.innerHTML = '';
        drivers.forEach(driver => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${driver.firstname}</td>
                <td>${driver.lastname}</td>
                <td>${maskDetails(driver.license_number)}</td>
                <td>${maskDetails(driver.phone_number)}</td>
                <td>${maskDetails(driver.email)}</td>
                <td>${driver.status}</td>
                <td><button class="view-details-btn" data-driver-id="${driver.id}">View Details</button></td>
            `;
            ordersTableBody.appendChild(row);
        });

        // Attach event listeners to the view details buttons
        document.querySelectorAll('.view-details-btn').forEach(button => {
            button.addEventListener('click', (event) => {
                const driverId = event.target.getAttribute('data-driver-id');
                fetchDriverDetails(driverId);
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
                fetchDrivers(page);
            });
            paginationContainer.appendChild(pageButton);
        }
    }

    function fetchDriverDetails(driverId) {
        fetch(`backend/fetch_driver_details.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ driver_id: driverId })
        })
            .then(response => response.json())
            .then(data => {
                console.log(data); // Log the entire response to the console
                if (data.success) {
                    populateDriverDetails(data.driver_details);
                    document.getElementById('orderModal').style.display = 'block';
                } else {
                    console.error('Failed to fetch Driver details:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching driver details:', error);
            });
    }

    function populateDriverDetails(driver_details) {
        const orderDetailsTable = document.querySelector('#orderDetailsTable tbody');
        const photoCell = document.querySelector('#driverPhoto');

        // Store original email and phone number in hidden fields
        const hiddenEmailInput = `<input type="hidden" id="originalEmail" value="${driver_details.email}">`;
        const hiddenPhoneNumberInput = `<input type="hidden" id="originalPhoneNumber" value="${driver_details.phone_number}">`;

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
                <td><input type="text" id="dateCreated" value="${driver_details.date_created}" disabled></td>
            </tr>
            <tr>
                <td>First Name</td>
                <td><input type="text" id="firstname" value="${driver_details.firstname}"></td>
            </tr>
            <tr>
                <td>Last Name</td>
                <td><input type="text" id="lastname" value="${driver_details.lastname}"></td>
            </tr>
            <tr>
                <td>Email</td>
                <td>
                    <input type="email" id="email" value="${maskDetails(driver_details.email)}">
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
                    <input type="text" id="phoneNumber" value="${maskDetails(driver_details.phone_number)}">
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
                        <option value="Male" ${driver_details.gender === 'Male' ? 'selected' : ''}>Male</option>
                        <option value="Female" ${driver_details.gender === 'Female' ? 'selected' : ''}>Female</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td>House Address</td>
                <td><input type="text" id="address" value="${driver_details.address}"></td>
            </tr>
            <tr>
                <td>License Number</td>
                <td><input type="text" id="licenseNumber" value="${driver_details.license_number}" disabled></td>
            </tr>
            <tr>
                <td>Availability Status</td>
                <td>
                    <select id="status">
                        <option value="Not Available" ${driver_details.status === 'Not Available' ? 'selected' : ''}>Not Available</option>
                        <option value="Available" ${driver_details.status === 'Available' ? 'selected' : ''}>Available</option>
                    </select>
                </td>
            </tr>

            <tr>
                <td>Vehicle Type</td>
                <td><input type="text" id="vehicleType" value="${driver_details.vehicle_type}"></td>
            </tr>
            <tr>
                <td>Restriction Status</td>
                <td>
                    <select id="restriction">
                        <option value="0" ${driver_details.restriction === '0' ? 'selected' : ''}>Not Restricted</option>
                        <option value="1" ${driver_details.restriction === '1' ? 'selected' : ''}>Restrict</option>
                    </select>
                </td>
            </tr>
        `;

        // Display photo if available
        if (driver_details.photo) {
            const photo = driver_details.photo;
            photoCell.innerHTML = `<img src="backend/driver_photos/${photo}" alt="Driver Photo" class="driver-photo">`;
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

        // Attach event listeners for checkbox toggle
        toggleMasking('toggleMaskingPhone', 'phoneNumber', 'originalPhoneNumber', 'phoneLabel');
        toggleMasking('toggleMaskingEmail', 'email', 'originalEmail', 'emailLabel');

        // Event listeners for update and delete buttons
        document.getElementById('updateDriverBtn').addEventListener('click', () => {
            updateDriver(driver_details.id);
        });

        document.getElementById('deleteDriverBtn').addEventListener('click', () => {
            deleteDriver(driver_details.id);
        });
    }

    function updateDriver(driverId) {
        const emailInput = document.getElementById('email').value;
        const phoneNumberInput = document.getElementById('phoneNumber').value;

        // Get the original values
        const originalEmail = document.getElementById('originalEmail').value;
        const originalPhoneNumber = document.getElementById('originalPhoneNumber').value;

        // Validation: Check if the input still contains masked characters (e.g., "***")
        if (emailInput.includes('*')) {
            alert('Please complete the email address before updating.');
            return;
        }
        if (phoneNumberInput.includes('*')) {
            alert('Please complete the phone number before updating.');
            return;
        }

        // Determine whether the user has edited the email or phone number
        const emailToSend = emailInput === maskDetails(originalEmail) ? originalEmail : emailInput;
        const phoneNumberToSend = phoneNumberInput === maskDetails(originalPhoneNumber) ? originalPhoneNumber : phoneNumberInput;

        const driverData = {
            id: driverId,
            firstname: document.getElementById('firstname').value,
            lastname: document.getElementById('lastname').value,
            email: emailToSend,
            phone_number: phoneNumberToSend,
            gender: document.getElementById('gender').value,
            address: document.getElementById('address').value,
            vehicle_type: document.getElementById('vehicleType').value,
            status: document.getElementById('status').value,
            restriction: document.getElementById('restriction').value
        };

        fetch('backend/update_driver.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(driverData)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Driver updated successfully.');
                    document.getElementById('orderModal').style.display = 'none';
                    fetchDrivers(currentPage); // Refresh the table after update
                } else {
                    console.error('Failed to Update driver:', data.message)
                    alert('Failed to update driver: ' + data.message);

                }
            })
            .catch(error => {
                console.error('Error updating driver:', error);
            });
    }


    function deleteDriver(driverId) {
        if (confirm('Are you sure you want to delete this driver?')) {
            fetch('backend/delete_driver.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: driverId })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Driver deleted successfully!');
                        document.getElementById('orderModal').style.display = 'none';
                        fetchDrivers(currentPage); // Refresh the driver list
                    } else {
                        console.error('Failed to delete driver:', data.message);
                        alert('Failed to delete driver:', data.message)
                    }
                })
                .catch(error => {
                    console.error('Error deleting driver:', error);
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
    fetchDrivers(currentPage);


    // Module for adding new driver
    // Function to generate a random license number
    function generateLicenseNumber() {
        const letters = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        const numbers = "0123456789";

        const randomLetter = () => letters.charAt(Math.floor(Math.random() * letters.length));
        const randomNumber = () => numbers.charAt(Math.floor(Math.random() * numbers.length));

        return `${randomLetter()}${randomLetter()}${randomNumber()}${randomNumber()}${randomNumber()}${randomLetter()}${randomLetter()}${randomLetter()}`;
    }
    // function to close modal
    document.getElementById('closeaddNewDriverModal').addEventListener('click', () => {
        document.getElementById('addNewDriverModal').style.display = 'none';
    });

    // Disable typing in the license number input and generate license number on click
    document.getElementById('add_license_number').addEventListener('click', function () {
        this.value = generateLicenseNumber();
        this.readOnly = true;
    });

    // Add New Driver form submission
    const addDriverForm = document.getElementById('addDriverForm');
    function handleFormSubmission(form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const formData = new FormData(this);
            const messageDiv = document.getElementById('addDriverMessage');

            fetch('backend/add_driver.php', {
                method: 'POST',
                // headers: {
                //     'Content-Type': 'application/x-www-form-urlencoded'
                // },
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Success:', data.message);
                        messageDiv.textContent = 'Driver has been successfully Onboarded!';
                        alert('Driver has been successfully Onboarded!')
                        location.reload();
                        // window.location.href = '../../Transaction_manager/dashboard.php';
                    } else {
                        console.log('Error:', data.message);
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
    handleFormSubmission(addDriverForm);
});
