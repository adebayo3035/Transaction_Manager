function toggleModal(modalId) {
    let modal = document.getElementById(modalId);
    modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";
}

document.addEventListener('DOMContentLoaded', () => {

    function updateDateTimeInputs(startDateId, endDateId) {
        const now = new Date();
        const formattedNow = now.toISOString().slice(0, 16);
        const startDateInput = document.getElementById(startDateId);
        const endDateInput = document.getElementById(endDateId);

        startDateInput.min = formattedNow;

        startDateInput.addEventListener("change", () => {
            const selectedStartDate = new Date(startDateInput.value);
            endDateInput.min = startDateInput.value;

            if (selectedStartDate.toDateString() === now.toDateString()) {
                const formattedEndMin = now.toISOString().slice(0, 16);
                endDateInput.min = formattedEndMin;
            }

            if (new Date(endDateInput.value) < selectedStartDate) {
                endDateInput.value = "";
            }
        });
    }
    updateDateTimeInputs("start_date", "end_date");

    const limit = 10;
let currentPage = 1;

function fetchPromos(page = 1) {
    const ordersTableBody = document.getElementById('ordersTableBody');
    const paginationContainer = document.getElementById('pagination'); // Adjust if your pagination container has a different ID

    // Disable pagination buttons during fetch (optional)
    // [...paginationContainer.querySelectorAll('button')].forEach(btn => btn.disabled = true);

    // Inject spinner
    ordersTableBody.innerHTML = `
        <tr>
            <td colspan="6" style="text-align:center; padding: 20px;">
                <div class="spinner"
                    style="border: 4px solid #f3f3f3;
                           border-top: 4px solid #3498db;
                           border-radius: 50%;
                           width: 30px;
                           height: 30px;
                           animation: spin 1s linear infinite;
                           margin: auto;">
                </div>
            </td>
        </tr>
    `;

    const minDelay = new Promise(resolve => setTimeout(resolve, 1000)); // Ensures spinner is visible for 1s
    const fetchData = fetch(`backend/get_promo.php?page=${page}&limit=${limit}`)
        .then(res => res.json());

    Promise.all([fetchData, minDelay])
        .then(([data]) => {
            if (data.success && data.promos && Array.isArray(data.promos.all) && data.promos.all.length > 0) {
                updateTable(data.promos.all);
                updatePagination(data.total, data.page, data.limit);
            } else {
                ordersTableBody.innerHTML = `
                    <tr><td colspan="6" style="text-align:center;">No Promo Details at the moment</td></tr>
                `;
                console.warn('Empty or invalid promo data:', data.message || "No data returned");
            }
        })
        .catch(error => {
            console.error("Error fetching data:", error);
            ordersTableBody.innerHTML = `
                <tr><td colspan="6" style="text-align:center; color:red;">Error loading Promo data</td></tr>
            `;
        })
        .finally(() => {
            // Re-enable pagination buttons (optional)
            // [...paginationContainer.querySelectorAll('button')].forEach(btn => btn.disabled = false);
        });
}

function updateTable(promos) {
    const ordersTableBody = document.querySelector('#ordersTable tbody');
    ordersTableBody.innerHTML = '';

    promos.forEach(promo => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${promo.promo_code}</td>
            <td>${promo.promo_name}</td>
            <td>${promo.start_date}</td>
            <td>${promo.end_date}</td>
            <td>${promo.status}</td>
            <td><button class="view-details-btn" data-promo-id="${promo.promo_id}">View Details</button></td>
        `;
        ordersTableBody.appendChild(row);
    });

    document.querySelectorAll('.view-details-btn').forEach(button => {
        button.addEventListener('click', (event) => {
            const promoId = event.target.getAttribute('data-promo-id');
            fetchPromoDetails(promoId);
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    fetchPromos(currentPage);
});


    // Function to update pagination
    function updatePagination(totalItems, currentPage, itemsPerPage) {
        const paginationContainer = document.getElementById('pagination');
        paginationContainer.innerHTML = '';

        const totalPages = Math.ceil(totalItems / itemsPerPage);
        paginationButtons = [];

        const createButton = (label, page, disabled = false) => {
            const btn = document.createElement('button');
            btn.textContent = label;
            if (disabled) btn.disabled = true;
            btn.addEventListener('click', () => fetchPromos(page));
            paginationContainer.appendChild(btn);
        };

        // Show: First, Prev
        createButton('« First', 1, currentPage === 1);
        createButton('‹ Prev', currentPage - 1, currentPage === 1);

        // Show range around current page (e.g. ±2)
        const maxVisible = 2;
        const start = Math.max(1, currentPage - maxVisible);
        const end = Math.min(totalPages, currentPage + maxVisible);

        for (let i = start; i <= end; i++) {
            const btn = document.createElement('button');
            btn.textContent = i;
            if (i === currentPage) btn.classList.add('active');
            btn.addEventListener('click', () => fetchPromos(i));
            paginationButtons.push(btn);
            paginationContainer.appendChild(btn);
        }

        // Show: Next, Last
        createButton('Next ›', currentPage + 1, currentPage === totalPages);
        createButton('Last »', totalPages, currentPage === totalPages);
    }

    function fetchPromoDetails(promoId) {
        fetch('backend/fetch_promo_details.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ promo_id: promoId })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    populatePromoDetails(data.promo_details, data.logged_in_user_role);
                    document.getElementById('orderModal').style.display = 'block';
                    // Ensure all your DOM manipulations are here or after this event 
                    const dateCreatedElement = document.getElementById('dateCreated');
                    if (dateCreatedElement) {
                        const dateCreated = dateCreatedElement.value;
                        updateDateTimeInputs("startDate", "endDate", dateCreated);
                    }
                    else {
                        console.error('Date Created input not found.');
                    }
                } else {
                    console.error('Failed to fetch Promo details:', data.message);
                }
            })
            .catch(error => console.error('Error fetching Promo details:', error));
    }

    function populatePromoDetails(promo_details, logged_in_user_role) {
        const orderDetailsTable = document.querySelector('#orderDetailsTable tbody');
        const isSuperAdmin = logged_in_user_role === 'Super Admin';
        const disableAttribute = isSuperAdmin ? '' : 'disabled';

        orderDetailsTable.innerHTML = `
            <!-- HTML content here for promo details -->
              <tr>
        <td>Promo ID</td>
        <td><input type="text" id="promoID" value="${promo_details.promo_id}" disabled></td>
    </tr>
    <tr>
        <td>Promo Code</td>
        <td><input type="text" id="promoCode" value="${promo_details.promo_code}" disabled></td>
    </tr>
    <tr>
        <td>Date Created</td>
        <td><input type="datetime-local" id="dateCreated" value="${promo_details.date_created}" disabled></td>
    </tr>
    <tr>
        <td>Promo Name</td>
        <td><input type="text" id="promoName" value="${promo_details.promo_name}" ${disableAttribute}></td>
    </tr>
    
    <tr>
        <td>Start Date</td>
        <td><input type="datetime-local" id="startDate" value="${promo_details.start_date}" ${disableAttribute}></td>
    </tr>
    <tr>
        <td>End Date</td>
        <td><input type="datetime-local" id="endDate" value="${promo_details.end_date}" ${disableAttribute}></td>
    </tr>
        <td>Description</td>
        <td>
            <textarea id="promoDescriptions" ${disableAttribute} required maxlength = "150" value= "${promo_details.promo_description}">${promo_details.promo_description}</textarea>
            <span id="charCounter" class="char-counter">150 characters remaining</div>
        </td>
    </tr>
    <tr>
        <td>Discount Type</td>
        <td>
            <select id="discountType" ${disableAttribute}>
                <option value="percentage" ${promo_details.discount_type === 'percentage' ? 'selected' : ''}>Percentage</option>
                <option value="flat" ${promo_details.discount_type === 'flat' ? 'selected' : ''}>Flat Rate</option>
            </select>
        </td>
    </tr>
    <tr>
        <td>Discount Value (%)</td>
        <td><input type="number" id="discountPercentage" value="${promo_details.discount_value}" min="1" max="100" ${disableAttribute}></td>
    </tr>
    <tr>
        <td>Eligibility Criteria</td>
        <td>
            <select id="eligibilityCriteria" ${disableAttribute}>
                <option value="All Customers" ${promo_details.eligibility_criteria === 'All Customers' ? 'selected' : ''}>All Customers</option>
                <option value="New Customers" ${promo_details.eligibility_criteria === 'New Customers' ? 'selected' : ''}>New Customers</option>
                <option value="Others" ${promo_details.eligibility_criteria === 'Others' ? 'selected' : ''}>Others</option>
            </select>
        </td>
    </tr>
    <tr>
        <td>Minimum Order Value</td>
        <td><input type="number" id="min_order_value" value="${promo_details.min_order_value}" ${disableAttribute}></td>
    </tr>
    <tr>
        <td>Maximum Discount</td>
        <td><input type="number" id="max_discount" value="${promo_details.max_discount}" ${disableAttribute}></td>
    </tr>
    
    
    <tr>
        <td>Status</td>
        <td>
            <select id="status" ${disableAttribute}>
                <option value="1" ${promo_details.status === '1' ? 'selected' : ''}>Active</option>
                <option value="0" ${promo_details.status === '0' ? 'selected' : ''}>Inactive</option>
            </select>
        </td>
    </tr>
        `;

        if (isSuperAdmin) {
            const actionButtons = `
                <tr>
                    <td colspan="2" style="text-align: center;">
                        <button id="updatePromoBtn">Update</button>
                        <button id="deletePromoBtn">Delete</button>
                    </td>
                </tr>
                <tr id="updateDateBtnRow" style="display: none;">
                    <td colspan="2" style="text-align: center;">
                        <button id="confirmDateUpdateBtn">Confirm Date Update</button>
                    </td>
                </tr>
            `;
            orderDetailsTable.innerHTML += actionButtons;

            document.getElementById('updatePromoBtn').addEventListener('click', () => {
                updatePromo(promo_details.promo_id);
            });
            document.getElementById('deletePromoBtn').addEventListener('click', () => {
                deletePromo(promo_details.promo_id);
            });
            addCharacterCounter('promoDescriptions', 'charCounter');
        }
    }

    function updateDateTimeUpdate(startDateId, endDateId, dateCreated) {
        const startDateInput = document.getElementById(startDateId);
        const endDateInput = document.getElementById(endDateId);

        const formattedDateCreated = new Date(dateCreated).toISOString().slice(0, 16);
        startDateInput.min = formattedDateCreated;

        startDateInput.addEventListener("change", () => {
            const selectedStartDate = new Date(startDateInput.value);
            endDateInput.min = startDateInput.value;

            if (new Date(selectedStartDate).toDateString() === new Date(dateCreated).toDateString()) {
                const formattedEndMin = new Date().toISOString().slice(0, 16); // Current time
                endDateInput.min = formattedEndMin;
            }

            if (new Date(endDateInput.value) < selectedStartDate) {
                endDateInput.value = ""; // Clear end date
            }
        });
    }

    function updatePromo(promoId) {
        const dateCreated = document.getElementById('dateCreated').value;
        updateDateTimeUpdate("startDate", "endDate", dateCreated);

        // Collect and parse promo data for the update request
        const promoData = {
            promo_id: promoId,
            promo_code: document.getElementById('promoCode').value,
            promo_name: document.getElementById('promoName').value,
            description: document.getElementById('promoDescriptions').value,
            date_created: document.getElementById('dateCreated').value,
            start_date: document.getElementById('startDate').value,
            end_date: document.getElementById('endDate').value,
            discount_type: document.getElementById('discountType').value,
            discount_percentage: parseFloat(document.getElementById('discountPercentage').value),
            eligibility_criteria: document.getElementById('eligibilityCriteria').value,
            min_order_value: parseFloat(document.getElementById('min_order_value').value),
            max_discount: parseFloat(document.getElementById('max_discount').value),
            status: parseInt(document.getElementById('status').value)
        };
        if(!confirm("Are you sure you want to Update Promo Details?")){
            return;
        }
        // Send the update request
        fetch('backend/update_promo.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(promoData)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Promo Details have been updated successfully.');
                    document.getElementById('orderModal').style.display = 'none';
                    fetchPromos(currentPage); // Refresh promo table after update
                } else {
                    alert('Failed to update Promo Details: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error updating Promo Details:', error);
                alert('An error occurred while updating Promo Details. Please try again later.');
            });
    }

    function deletePromo(promoId) {
        if (confirm('Are you sure you want to delete this Promo Offer?')) {
            fetch('backend/delete_promo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ promo_id: promoId })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Promo Offer has been successfully deleted!');
                        document.getElementById('orderModal').style.display = 'none';
                        fetchPromos(currentPage); // Refresh the driver list
                    } else {
                        console.error('Failed to delete Promo Offer:', data.message);
                        alert('Failed to delete Promo Offer:', data.message)
                    }
                })
                .catch(error => {
                    console.error('Error deleting Promo Offer:', error);
                });
        }
    }

    // Close modal when the "close" button is clicked
    document.querySelectorAll('.modal .close').forEach(closeBtn => {
        closeBtn.addEventListener('click', (event) => {
            const modal = event.target.closest('.modal'); // Find the closest modal
            if (modal) {
                modal.style.display = 'none';
                location.reload();
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

    fetchPromos(currentPage);

    // Add New Promo Offer submission
    const addPromoForm = document.getElementById('addPromoForm');
    const inputs = addPromoForm.querySelectorAll('input, select, textarea');
    const submitBtn = document.getElementById('createPromoButton');

    function checkFormCompletion() {
        let allFilled = true;
        const startDate = new Date(document.getElementById('start_date').value);
        const endDate = new Date(document.getElementById('end_date').value);
        inputs.forEach(input => {
            if (input.type !== 'hidden' && (input.type !== 'file' ? !input.value : !input.files.length)) {
                allFilled = false;
            }
            else if ((startDate >= endDate)) {
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
            const messageDiv = document.getElementById('addPromoMessage');
            if(!confirm("Are you sure you want to add new Promo?")){
                return;
            }
            fetch('backend/create_promo.php', {
                method: 'POST',
                body: formData,
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {  // Check for status
                        console.log('Success:', data.message);
                        alert('Success: ' + data.message); // Use + for concatenation
                        messageDiv.textContent = 'Success: ' + data.message; // Use + for concatenation
                        location.reload();
                    } else {
                        console.log('Error:', data.message);
                        messageDiv.textContent = data.message;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    messageDiv.textContent = 'Error: ' + error.message;
                    alert('An error occurred. Please Try Again Later');
                });
        });
    }

    // function to generate promo code
    function generatePromoCode() {
        const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        let promoCode = '';
        const length = 8; // Length of the promo code

        for (let i = 0; i < length; i++) {
            promoCode += characters.charAt(Math.floor(Math.random() * characters.length));
        }

        // Set the generated promo code in the promoCode input
        document.getElementById('promo_code').value = promoCode;
    }
    function addCharacterCounter(textareaId, charCountId) {
        const textarea = document.getElementById(textareaId);
        const charCount = document.getElementById(charCountId);
        const maxLength = textarea.maxLength; // Get the maximum length

        // Function to update character count
        function updateCharCount() {
            const remaining = maxLength - textarea.value.length; // Calculate remaining characters
            charCount.textContent = `${remaining} characters remaining`; // Update the counter text

            // Optional: Change the color of the counter if limit is reached
            if (remaining < 0) {
                charCount.style.color = 'red'; // Change color to red if limit exceeded
            } else {
                charCount.style.color = '#555'; // Reset color
            }
        }

        // Event listener for input in the textarea
        textarea.addEventListener('input', updateCharCount);
        // Initial call to set the character count on page load
        updateCharCount();
    }

    // Usage
    addCharacterCounter('promoDescription', 'charCount');

    document.getElementById('promo_code').addEventListener('click', generatePromoCode())
    handleFormSubmission(addPromoForm);
});
