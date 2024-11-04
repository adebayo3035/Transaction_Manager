function toggleModal(modalId) {
    let modal = document.getElementById(modalId);
    modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";
}
document.addEventListener('DOMContentLoaded', () => {

    function updateDateTimeInputs(startDateId, endDateId) {
        const now = new Date();
        const formattedNow = now.toISOString().slice(0, 16); // Formats to 'YYYY-MM-DDTHH:MM'
        const startDateInput = document.getElementById(startDateId);
        const endDateInput = document.getElementById(endDateId);

        startDateInput.min = formattedNow;

        startDateInput.addEventListener("change", () => {
            const selectedStartDate = new Date(startDateInput.value);

            endDateInput.min = startDateInput.value;

            if (selectedStartDate.toDateString() === now.toDateString()) {
                const formattedEndMin = now.toISOString().slice(0, 16); // Current time
                endDateInput.min = formattedEndMin;
            }

            if (new Date(endDateInput.value) < selectedStartDate) {
                endDateInput.value = ""; // Clear end date
            }
        });
    }

    // Usage
    updateDateTimeInputs("start_date", "end_date");


    const limit = 10; // Number of items per page
    let currentPage = 1;

    function fetchPromos(page = 1) {
        fetch(`backend/get_promo.php?page=${page}&limit=${limit}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateTable(data.promos);
                    updatePagination(data.total, data.page, data.limit);
                } else {
                    console.error('Failed to fetch Promo Details:', data.message);
                }
            })
            .catch(error => console.error('Error fetching data:', error));
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

        // Attach event listeners to the view details buttons
        document.querySelectorAll('.view-details-btn').forEach(button => {
            button.addEventListener('click', (event) => {
                const promoId = event.target.getAttribute('data-promo-id');
                fetchPromoDetails(promoId);
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
                fetchPromos(page);
            });
            paginationContainer.appendChild(pageButton);
        }
    }

    function fetchPromoDetails(promoId) {
        fetch(`backend/fetch_promo_details.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ promo_id: promoId })
        })
            .then(response => response.json())
            .then(data => {
                console.log(data); // Log the entire response to the console
                if (data.success) {
                    populatePromoDetails(data.promo_details, data.logged_in_user_role);
                    console.log(data.logged_in_user_role);
                    document.getElementById('orderModal').style.display = 'block';
                } else {
                    console.error('Failed to fetch Promo details:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching Promo details:', error);
            });
    }

    function populatePromoDetails(promo_details, logged_in_user_role) {
        const orderDetailsTable = document.querySelector('#orderDetailsTable tbody');

        // Disable input fields if the logged-in user is not a "Super Admin"
        const isSuperAdmin = logged_in_user_role === 'Super Admin';
        const disableAttribute = isSuperAdmin ? '' : 'disabled';

        orderDetailsTable.innerHTML = `
        <tr>
    <td>Promo ID</td>
    <td><input type="text" id="promoID" value="${promo_details.promo_id}" disabled></td>
</tr>
<tr>
    <td>Promo Code</td>
    <td><input type="text" id="promoCode" value="${promo_details.promo_code}" disabled></td>
</tr>
<tr>
    <td>Promo Name</td>
    <td><input type="text" id="promoName" value="${promo_details.promo_name}" ${disableAttribute}></td>
</tr>
<tr>
    <td>Description</td>
    <td>
        <textarea id="promoDescriptions" ${disableAttribute} required maxlength = "150">${promo_details.promo_description}</textarea>
        <span id="charCounter" class="char-counter">150 characters remaining</div>
    </td>
</tr>

<tr>
    <td>Start Date</td>
    <td><input type="datetime-local" id="startDate" value="${promo_details.start_date}" ${disableAttribute}></td>
</tr>
<tr>
    <td>End Date</td>
    <td><input type="datetime-local" id="endDate" value="${promo_details.end_date}" ${disableAttribute}></td>
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

        // Conditionally add "Update" and "Delete" buttons if the logged-in user is a "Super Admin"
        if (isSuperAdmin) {
            const actionButtons = `
            <tr>
                <td colspan="2" style="text-align: center;">
                    <button id="updatePromoBtn">Update</button>
                    <button id="deletePromoBtn">Delete</button>
                </td>
            </tr>
        `;
            orderDetailsTable.innerHTML += actionButtons;

            // Event listeners for update and delete buttons
            document.getElementById('updatePromoBtn').addEventListener('click', () => {
                updatePromo(promo_details.promo_id);
            });

            document.getElementById('deletePromoBtn').addEventListener('click', () => {
                deletePromo(promo_details.promo_id);
            });
            addCharacterCounter('promoDescriptions', 'charCounter');
        }
    }

    function updatePromo(promoId) {
        updateDateTimeInputs("startDate", "endDate");

        // Collect and parse promo data for the update request
        const promoData = {
            promo_id: promoId,
            promo_code: document.getElementById('promoCode').value,
            promo_name: document.getElementById('promoName').value,
            description: document.getElementById('promoDescription').value,
            start_date: document.getElementById('startDate').value,
            end_date: document.getElementById('endDate').value,
            discount_type: document.getElementById('discountType').value,
            discount_percentage: parseFloat(document.getElementById('discountPercentage').value),
            eligibility_criteria: document.getElementById('eligibilityCriteria').value,
            min_order_value: parseFloat(document.getElementById('min_order_value').value),
            max_discount: parseFloat(document.getElementById('max_discount').value),
            status: parseInt(document.getElementById('status').value)
        };

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

    // Fetch initial drivers data
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
