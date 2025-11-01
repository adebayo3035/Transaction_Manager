// Global variables
const limit = 10;
let currentPage = 1;
let discountTypeSelect, discountDiv, discountInput, discountIdentifier, addPromoForm, maxDiscount;


// Function to sync discountInput with maxDiscount
function syncFlatDiscount() {
    discountInput.value = this.value;
}
const syncMaxToDiscount = () => {
    // mirror value as the user types
    maxDiscount.value = discountInput.value;
};
// Utility Functions
function toggleModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// DOM Initialization
function initializeDOM() {
    discountTypeSelect = document.getElementById("discount_type");
    discountDiv = document.getElementById("discount-div");
    discountInput = document.getElementById("discount");
    discountIdentifier = document.getElementById("discount_identifier");
    addPromoForm = document.getElementById('addPromoForm');
    maxDiscount = document.getElementById('max_discount');

    // Generate promo code if input exists
    const promoCodeInput = document.getElementById('promo_code');
    if (promoCodeInput) {
        promoCodeInput.addEventListener('click', generatePromoCode);
        generatePromoCode();
    }
    setupRealTimeValidation();
    addCharacterCounter('promoDescription', 'charCount');

    // Update discount fields whenever type changes
    discountTypeSelect.addEventListener('change', updateDiscountFields);
    updateDiscountFields(); // initial call
}

// Discount Field Handling
function updateDiscountFields() {
    if (!discountTypeSelect) return;

    const isPercentage = discountTypeSelect.value === "percentage";
    const isFlat = discountTypeSelect.value === "flat";

    // reset sync + editability first (prevents duplicate listeners)
    discountInput.removeEventListener("input", syncMaxToDiscount);
    maxDiscount.removeAttribute("readonly");

    if (isPercentage) {
        discountDiv.style.display = "block";
        discountInput.required = true;
        discountInput.min = 0.1;
        discountInput.max = 100;
        discountInput.step = "0.1";            // optional: allow decimals
        discountIdentifier.innerHTML = "Discount (%)";
    } else if (isFlat) {
        discountDiv.style.display = "block";
        discountInput.required = true;
        discountInput.min = 1;
        discountInput.removeAttribute("max");
        discountInput.step = "1";              // optional: whole numbers for flat
        discountIdentifier.innerHTML = "Rate (#)";

        // lock maxDiscount and mirror from discountInput
        maxDiscount.setAttribute("readonly", true);
        syncMaxToDiscount();                    // initialize mirror
        discountInput.addEventListener("input", syncMaxToDiscount);
    } else {
        // no valid type selected
        discountDiv.style.display = "none";
        discountInput.required = false;
        discountInput.value = "";
        discountInput.removeAttribute("min");
        discountInput.removeAttribute("max");
        discountInput.removeAttribute("step");
    }
}
// Date Time Handling
function updateDateTimeInputs(startDateId, endDateId, dateCreated = null) {
    const startDateInput = document.getElementById(startDateId);
    const endDateInput = document.getElementById(endDateId);

    if (!startDateInput || !endDateInput) return;

    const now = new Date();
    const minDate = dateCreated ? new Date(dateCreated) : now;
    const formattedMinDate = minDate.toISOString().slice(0, 16);

    startDateInput.min = formattedMinDate;

    startDateInput.addEventListener("change", () => {
        const selectedStartDate = new Date(startDateInput.value);
        endDateInput.min = startDateInput.value;

        if (selectedStartDate.toDateString() === minDate.toDateString()) {
            endDateInput.min = now.toISOString().slice(0, 16);
        }

        if (new Date(endDateInput.value) < selectedStartDate) {
            endDateInput.value = "";
        }
    });
}

// Event Delegation
function setupEventDelegation() {
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('action-btn')) {
            const action = e.target.dataset.action;
            const promoId = e.target.dataset.id;

            const actionHandlers = {
                'update': () => updatePromo(promoId),
                'delete': () => deletePromo(promoId),
                'reactivate': () => reactivatePromo(promoId),
                'confirm-date': () => confirmDateUpdate(promoId)
            };

            if (actionHandlers[action]) {
                actionHandlers[action]();
            }
        }
    });
}

// HTML Template Functions
function createSpinnerHTML() {
    return `
        <tr>
            <td colspan="7" style="text-align:center; padding: 20px;">
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
}

function createNoDataHTML(message = "No Promo Details at the moment") {
    return `
        <tr><td colspan="7" style="text-align:center;">${message}</td></tr>
    `;
}

function createErrorHTML() {
    return `
        <tr><td colspan="7" style="text-align:center; color:red;">Error loading Promo data</td></tr>
    `;
}

// Helper functions for promo details options
function createEligibilityOptions(selectedCriteria) {
    const criteria = [
        { value: 'All Customers', label: 'All Customers' },
        { value: 'New Customers', label: 'New Customers' },
        { value: 'Others', label: 'Others' }
    ];

    return criteria.map(item =>
        `<option value="${item.value}" ${selectedCriteria === item.value ? 'selected' : ''}>${item.label}</option>`
    ).join('');
}

function createStatusOptions(selectedStatus) {
    const statuses = [
        { value: '1', label: 'Active' },
        { value: '0', label: 'Inactive' }
    ];

    return statuses.map(status =>
        `<option value="${status.value}" ${selectedStatus == status.value ? 'selected' : ''}>${status.label}</option>`
    ).join('');
}

// Action buttons for super admin
function addActionButtons(container, promoDetails) {
    const isActive = promoDetails.delete_id == 0;

    let actionButtons = `
        <tr>
            <td colspan="2" style="text-align: center;">
    `;

    if (isActive) {
        actionButtons += `
            <button class="action-btn update-promo" data-action="update" data-id="${promoDetails.promo_id}">Update Promo</button>
            <button class="action-btn delete-promo" data-action="delete" data-id="${promoDetails.promo_id}">Delete</button>
        `;
    } else {
        actionButtons += `
            <button class="action-btn reactivate-promo" data-action="reactivate" data-id="${promoDetails.promo_id}">Reactivate</button>
        `;
    }

    actionButtons += `</td></tr>`;

    actionButtons += `
        <tr id="updateDateBtnRow" style="display: none;">
            <td colspan="2" style="text-align: center;">
                <button class="action-btn confirm-date-update" data-action="confirm-date" data-id="${promoDetails.promo_id}">Confirm Date Update</button>
            </td>
        </tr>
    `;

    container.innerHTML += actionButtons;
}

// Delete and Reactivate functions (previously missing)
async function deletePromo(promoId) {
    if (!confirm('Are you sure you want to delete this Promo Offer?')) return;

    try {
        const response = await fetch('backend/delete_promo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ promo_id: promoId })
        });

        const data = await response.json();

        if (data.success) {
            alert('Promo Offer has been successfully deleted!');
            document.getElementById('orderModal').style.display = 'none';
            fetchPromos(currentPage);
        } else {
            alert('Failed to delete Promo Offer: ' + data.message);
        }
    } catch (error) {
        console.error('Error deleting Promo Offer:', error);
        alert('Error deleting Promo Offer: ' + error.message);
    }
}

async function reactivatePromo(promoId) {
    if (!confirm('Are you sure you want to Reactivate this Promo Offer?')) return;

    try {
        const response = await fetch('backend/reactivate_promo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ promo_id: promoId })
        });

        if (!response.ok) {
            throw new Error('Network response was not ok');
        }

        const data = await response.json();

        if (data.success) {
            alert('Promo Offer has been successfully Reactivated!');
            document.getElementById('orderModal').style.display = 'none';
            fetchPromos(currentPage);
        } else {
            alert('Failed to Reactivate Promo Offer: ' + data.message);
        }
    } catch (error) {
        console.error('Error Reactivating Promo Offer:', error);
        alert('Error Reactivating Promo Offer: ' + error.message);
    }
}

// Confirm date update function (placeholder - implement as needed)
function confirmDateUpdate(promoId) {
    alert('Confirm date update functionality for promo ID: ' + promoId);
    // Implement your date update confirmation logic here
}

// Promo Management
async function fetchPromos(page = 1) {
    const ordersTableBody = document.getElementById('ordersTableBody');
    const paginationContainer = document.getElementById('pagination');

    // Read filter values
    const statusFilter = document.getElementById('statusFilter').value;
    const deleteFilter = document.getElementById('deleteFilter').value;

    // Build query string dynamically
    let queryParams = `page=${page}&limit=${limit}`;
    if (statusFilter !== "") queryParams += `&status=${statusFilter}`;
    if (deleteFilter !== "") queryParams += `&delete_id=${deleteFilter}`;

    // Show spinner
    ordersTableBody.innerHTML = createSpinnerHTML();

    try {
        const [data] = await Promise.all([
            fetch(`backend/get_promo.php?${queryParams}`).then(res => res.json()),
            new Promise(resolve => setTimeout(resolve, 1000)) // artificial delay for spinner
        ]);

        if (data.success && data.promos?.all?.length > 0) {
            updateTable(data.promos.all);
            updatePagination(data.total, data.page, data.limit);
        } else {
            ordersTableBody.innerHTML = createNoDataHTML(data.message);
        }
    } catch (error) {
        console.error("Error fetching data:", error);
        ordersTableBody.innerHTML = createErrorHTML();
    }
}

// Event listener for filter button
document.getElementById('applyPromoFilters').addEventListener('click', () => {
    fetchPromos(1); // reset to first page when applying filters
});


function updateTable(promos) {
    const ordersTableBody = document.querySelector('#ordersTable tbody');
    ordersTableBody.innerHTML = '';

    promos.forEach(promo => {
        const row = createPromoTableRow(promo);
        ordersTableBody.appendChild(row);
    });

    attachViewDetailsListeners();
}

function createPromoTableRow(promo) {
    const statusText = promo.status == 1 ? 'Active' : 'Inactive';
    const deleteText = promo.delete_id == 1 ? 'Yes' : 'No';

    const row = document.createElement('tr');
    row.innerHTML = `
        <td>${promo.promo_code}</td>
        <td>${promo.promo_name}</td>
        <td>${promo.start_date}</td>
        <td>${promo.end_date}</td>
        <td>${statusText}</td>
        <td>${deleteText}</td>
        <td><button class="view-details-btn" data-promo-id="${promo.promo_id}">View Details</button></td>
    `;
    return row;
}

function attachViewDetailsListeners() {
    document.querySelectorAll('.view-details-btn').forEach(button => {
        button.addEventListener('click', (event) => {
            const promoId = event.target.getAttribute('data-promo-id');
            fetchPromoDetails(promoId);
        });
    });
}

// Pagination
function updatePagination(totalItems, currentPage, itemsPerPage) {
    const paginationContainer = document.getElementById('pagination');
    paginationContainer.innerHTML = '';

    const totalPages = Math.ceil(totalItems / itemsPerPage);
    const maxVisible = 2;
    const start = Math.max(1, currentPage - maxVisible);
    const end = Math.min(totalPages, currentPage + maxVisible);

    // Create pagination buttons
    createPaginationButton('« First', 1, currentPage === 1, paginationContainer);
    createPaginationButton('‹ Prev', currentPage - 1, currentPage === 1, paginationContainer);

    for (let i = start; i <= end; i++) {
        createPaginationButton(i, i, false, paginationContainer, i === currentPage);
    }

    createPaginationButton('Next ›', currentPage + 1, currentPage === totalPages, paginationContainer);
    createPaginationButton('Last »', totalPages, currentPage === totalPages, paginationContainer);
}

function createPaginationButton(label, page, disabled, container, isActive = false) {
    const btn = document.createElement('button');
    btn.textContent = label;
    btn.disabled = disabled;
    if (isActive) btn.classList.add('active');
    btn.addEventListener('click', () => fetchPromos(page));
    container.appendChild(btn);
}

// Promo Details
async function fetchPromoDetails(promoId) {
    try {
        const response = await fetch('backend/fetch_promo_details.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ promo_id: promoId })
        });

        const data = await response.json();

        if (data.success) {
            populatePromoDetails(data.promo_details, data.logged_in_user_role);
            document.getElementById('orderModal').style.display = 'block';

            const dateCreatedElement = document.getElementById('dateCreated');
            if (dateCreatedElement) {
                updateDateTimeInputs("startDate", "endDate", dateCreatedElement.value);
            }
        } else {
            console.error('Failed to fetch Promo details:', data.message);
        }
    } catch (error) {
        console.error('Error fetching Promo details:', error);
    }
}

// Add these global variables at the top
let promoFormElements = {};


// Update populatePromoDetails to store references
function populatePromoDetails(promoDetails, userRole) {
    const orderDetailsTable = document.querySelector('#orderDetailsTable tbody');
    const isSuperAdmin = userRole === 'Super Admin';

    orderDetailsTable.innerHTML = createPromoDetailsHTML(promoDetails, isSuperAdmin);

    // Use a small delay to ensure DOM is fully rendered
    setTimeout(() => {
        promoFormElements = {
            promoId: document.getElementById('promoID'),
            promoCode: document.getElementById('promoCode'),
            promoName: document.getElementById('promoName'),
            promoDescriptions: document.getElementById('promoDescriptions'),
            dateCreated: document.getElementById('dateCreated'),
            startDate: document.getElementById('startDate'),
            endDate: document.getElementById('endDate'),
            discountType: document.getElementById('discountType'),
            discountInput: document.getElementById('discountInput'),
            eligibilityCriteria: document.getElementById('eligibilityCriteria'),
            min_order_value: document.getElementById('min_order_value'),
            max_discount: document.getElementById('max_discount'),
            status: document.getElementById('status')
        };

        console.log('Form elements initialized:', {
            promoId: !!promoFormElements.promoId,
            promoCode: !!promoFormElements.promoCode,
            promoName: !!promoFormElements.promoName,
            // Add checks for other elements
        });
    }, 100);

    if (isSuperAdmin) {
        addActionButtons(orderDetailsTable, promoDetails);
    }

    addCharacterCounter('promoDescriptions', 'charCounter');
}

function createPromoDetailsHTML(promo_details, isSuperAdmin) {
    const disableAttribute = isSuperAdmin ? '' : 'disabled';

    return `
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
        <td>Discount Value</td>
        <td>
        <input type="number" id="discountInput" 
               value="${promo_details.discount_value}" 
               ${promo_details.discount_type === 'percentage' ? 'min="0.1" max="100" step="0.1"' : 'min="1"'} 
               ${disableAttribute}>
        </td>
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
                    <option value="1" ${promo_details.status == 1 ? 'selected' : ''}>Active</option>
                    <option value="0" ${promo_details.status == 0 ? 'selected' : ''}>Inactive</option>
                </select>
            </td>
        </tr>
    `;
}

function createDiscountTypeOptions(selectedType) {
    const types = [
        { value: 'percentage', label: 'Percentage' },
        { value: 'flat', label: 'Flat Rate' }
    ];

    return types.map(type =>
        `<option value="${type.value}" ${selectedType === type.value ? 'selected' : ''}>${type.label}</option>`
    ).join('');
}

// Similar helper functions for eligibility and status options...

// Action Functions
async function updatePromo(promoId) {
    if (!confirm("Are you sure you want to Update Promo Details?")) return;

    const promoData = collectPromoData();

    // Debug: Check what data is being sent
    console.log('Sending data to backend:', promoData);
    console.log('Expected promoId:', promoId);
    console.log('Actual promoId in data:', promoData?.promo_id);

    if (!promoData || promoData.promo_id !== parseInt(promoId)) {
        alert('Data validation failed. Please refresh and try again.');
        return;
    }

    // Client-side validation
    const validationErrors = validatePromoData(promoData);
    if (validationErrors.length > 0) {
        alert('Validation errors:\n' + validationErrors.join('\n'));
        return;
    }

    try {
        const response = await fetch('backend/update_promo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(promoData)
        });

        const data = await response.json();

        if (data.success) {
            alert('Promo Details updated successfully.');
            document.getElementById('orderModal').style.display = 'none';
            fetchPromos(currentPage);
        } else {
            alert('Failed to update: ' + data.message);
        }
    } catch (error) {
        console.error('Error updating Promo:', error);
        alert('An error occurred. Please try again later.');
    }
}

// Comprehensive validation function
function validatePromoData(promoData) {
    const errors = [];

    // Required field validation
    const requiredFields = [
        'promo_code', 'promo_name', 'description', 'date_created',
        'start_date', 'end_date', 'discount_type', 'discount_value',
        'eligibility_criteria', 'min_order_value', 'max_discount', 'status'
    ];

    requiredFields.forEach(field => {
        if (!promoData[field] && promoData[field] !== 0) {
            errors.push(`- ${field.replace('_', ' ')} is required`);
        }
    });

    // Date validation
    const startDate = new Date(promoData.start_date);
    const endDate = new Date(promoData.end_date);
    const dateCreated = new Date(promoData.date_created);
    const now = new Date();

    if (startDate < dateCreated) {
        errors.push('- Start date cannot be before creation date');
    }

    if (startDate >= endDate) {
        errors.push('- End date must be after start date');
    }

    if (endDate < now) {
        errors.push('- End date cannot be in the past');
    }

    // Discount validation based on type
    if (promoData.discount_type === 'percentage') {
        if (promoData.discount_value < 0.1 || promoData.discount_value > 100) {
            errors.push('- Percentage discount must be between 0.1% and 100%');
        }

        // For percentage, max_discount should be a reasonable cap
        if (promoData.max_discount <= 0) {
            errors.push('- Maximum discount must be greater than 0');
        }

    } else if (promoData.discount_type === 'flat') {
        if (promoData.discount_value <= 0) {
            errors.push('- Flat discount must be greater than 0');
        }

        // Flat discount must equal max_discount
        if (promoData.discount_value !== promoData.max_discount) {
            errors.push('- Flat discount value must match maximum discount amount');
        }

        // Flat discount cannot be greater than min_order_value
        if (promoData.discount_value >= promoData.min_order_value) {
            errors.push('- Flat discount amount must be less than minimum order value');
        }
    }

    // Minimum order value validation
    if (promoData.min_order_value < 0) {
        errors.push('- Minimum order value cannot be negative');
    }

    // Max discount validation
    if (promoData.max_discount < 0) {
        errors.push('- Maximum discount cannot be negative');
    }

    // Promo code validation
    if (!/^[A-Z0-9_-]{3,20}$/i.test(promoData.promo_code)) {
        errors.push('- Promo code must be 3-20 alphanumeric characters (may include - or _)');
    }

    // Promo name validation
    if (promoData.promo_name.length < 3 || promoData.promo_name.length > 100) {
        errors.push('- Promo name must be between 3 and 100 characters');
    }

    return errors;
}

// You can also add real-time validation in your form
function setupRealTimeValidation() {
    const discountType = document.getElementById('discountType');
    const discountInput = document.getElementById('discountInput');
    const maxDiscount = document.getElementById('max_discount');
    const minOrderValue = document.getElementById('min_order_value');

    if (discountType && discountInput && maxDiscount && minOrderValue) {
        discountType.addEventListener('change', validateDiscounts);
        discountInput.addEventListener('input', validateDiscounts);
        maxDiscount.addEventListener('input', validateDiscounts);
        minOrderValue.addEventListener('input', validateDiscounts);
    }
}

function validateDiscounts() {
    const discountType = document.getElementById('discountType').value;
    const discountInput = document.getElementById('discountInput');
    const maxDiscount = document.getElementById('max_discount');
    const minOrderValue = document.getElementById('min_order_value');
    const errorDiv = document.getElementById('discountError') || createErrorDiv();

    let errors = [];

    if (discountType === 'percentage') {
        const value = parseFloat(discountInput.value);
        if (value < 0.1 || value > 100) {
            errors.push('Percentage must be between 0.1% and 100%');
        }
    } else if (discountType === 'flat') {
        const discountValue = parseFloat(discountInput.value);
        const maxValue = parseFloat(maxDiscount.value);
        const minValue = parseFloat(minOrderValue.value);

        if (discountValue <= 0) {
            errors.push('Flat discount must be greater than 0');
        }

        if (discountValue !== maxValue) {
            errors.push('Flat discount must match maximum discount');
        }

        if (discountValue >= minValue) {
            errors.push('Flat discount must be less than minimum order value');
        }
    }

    if (errors.length > 0) {
        errorDiv.innerHTML = errors.join('<br>');
        errorDiv.style.display = 'block';
    } else {
        errorDiv.style.display = 'none';
    }
}

function createErrorDiv() {
    const errorDiv = document.createElement('div');
    errorDiv.id = 'discountError';
    errorDiv.style.color = 'red';
    errorDiv.style.marginTop = '5px';
    errorDiv.style.display = 'none';

    const discountRow = document.querySelector('tr:has(#discountInput)');
    if (discountRow) {
        discountRow.querySelector('td:last-child').appendChild(errorDiv);
    }

    return errorDiv;
}


// Then update collectPromoData to use stored references
function collectPromoData() {
    try {
        // Query elements directly to avoid reference issues
        const data = {
            promo_id: parseInt(document.getElementById('promoID').value) || 0,
            promo_code: document.getElementById('promoCode').value,
            promo_name: document.getElementById('promoName').value,
            description: document.getElementById('promoDescriptions').value,
            date_created: document.getElementById('dateCreated').value,
            start_date: document.getElementById('startDate').value,
            end_date: document.getElementById('endDate').value,
            discount_type: document.getElementById('discountType').value,
            discount_value: parseFloat(document.getElementById('discountInput').value) || 0,
            eligibility_criteria: document.getElementById('eligibilityCriteria').value,
            min_order_value: parseFloat(document.getElementById('min_order_value').value) || 0,
            max_discount: parseFloat(document.getElementById('max_discount').value) || 0,
            status: parseInt(document.getElementById('status').value) || 0
        };

        console.log('Collected promo data:', data);
        return data;
    } catch (error) {
        console.error('Error collecting promo data:', error);
        console.log('Available elements:', {
            promoID: document.getElementById('promoID'),
            promoCode: document.getElementById('promoCode'),
            promoName: document.getElementById('promoName'),
            promoDescriptions: document.getElementById('promoDescriptions')
            // Add other elements
        });
        return null;
    }
}
// Similar optimized functions for deletePromo and reactivatePromo...

// Form Handling
function setupFormHandling() {
    setupDiscountTypeListener();
    setupFormValidation();
    setupFormSubmission();
    setupModalHandlers();
    setupSearchFilter();

    // Additional initializations
    const promoCodeInput = document.getElementById('promo_code');
    if (promoCodeInput) {
        promoCodeInput.addEventListener('click', generatePromoCode);
        if (!promoCodeInput.value) {
            generatePromoCode();
        }
    }
    addCharacterCounter('promoDescription', 'charCount');
}

function setupDiscountTypeListener() {
    if (discountTypeSelect) {
        discountTypeSelect.addEventListener('change', updateDiscountFields);
    }
}

function setupFormValidation() {
    const inputs = addPromoForm.querySelectorAll('input, select, textarea');
    const submitBtn = document.getElementById('createPromoButton');

    function checkFormCompletion() {
        const allFilled = Array.from(inputs).every(input =>
            input.type === 'hidden' || (input.type === 'file' ? input.files.length : input.value)
        );

        const startDate = new Date(document.getElementById('start_date').value);
        const endDate = new Date(document.getElementById('end_date').value);
        const datesValid = startDate < endDate;

        submitBtn.style.visibility = allFilled && datesValid ? 'visible' : 'hidden';
    }

    inputs.forEach(input => {
        input.addEventListener('input', checkFormCompletion);
        input.addEventListener('change', checkFormCompletion);
    });

    window.addEventListener('load', checkFormCompletion);
}
function setupFormSubmission() {
    if (!addPromoForm) return;

    addPromoForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        const messageDiv = document.getElementById('addPromoMessage');

        if (!confirm("Are you sure you want to add new Promo?")) {
            return;
        }

        // Validate discount values
        const discountType = discountTypeSelect.value;
        const discountValue = parseFloat(discountInput.value);

        if (discountType === 'percentage') {
            if (isNaN(discountValue) || discountValue < 1 || discountValue > 100) {
                alert('Percentage discount must be between 1% and 100%');
                return;
            }
        } else if (discountType === 'flat') {
            if (isNaN(discountValue) || discountValue <= 0) {
                alert('Flat rate discount must be greater than 0');
                return;
            }
        }

        // Validate dates
        const startDate = new Date(document.getElementById('start_date').value);
        const endDate = new Date(document.getElementById('end_date').value);
        if (startDate >= endDate) {
            alert('End date must be after start date');
            return;
        }

        try {
            const formData = new FormData(this);

            // Always append the unified discount value and type
            formData.set('discount_value', discountValue);
            formData.set('discount_type', discountType);

            const response = await fetch('backend/create_promo.php', {
                method: 'POST',
                body: formData,
            });

            const data = await response.json();

            if (data.status === 'success') {
                alert('Success: ' + data.message);
                messageDiv.textContent = 'Success: ' + data.message;
                location.reload();
            } else {
                alert('Error: ' + data.message);
                messageDiv.textContent = data.message;
            }
        } catch (error) {
            console.error('Error:', error);
            messageDiv.textContent = 'Error: ' + error.message;
            alert('An error occurred. Please Try Again Later');
        }
    });
}

function setupModalHandlers() {
    // Close modal when the "close" button is clicked
    document.querySelectorAll('.modal .close').forEach(closeBtn => {
        closeBtn.addEventListener('click', (event) => {
            const modal = event.target.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
                // Only reload if needed, remove if not necessary
                if (modal.id === 'orderModal') {
                    location.reload();
                }
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

    // Toggle modal function
    window.toggleModal = function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";
        }
    };
}

function setupSearchFilter() {
    const liveSearch = document.getElementById("liveSearch");
    if (!liveSearch) return;

    // Debounced search function
    const debouncedFilterTable = debounce(filterTable, 300);

    liveSearch.addEventListener("input", debouncedFilterTable);

    function filterTable() {
        const searchTerm = liveSearch.value.toLowerCase();
        const rows = document.querySelectorAll("#ordersTable tbody tr");

        rows.forEach(row => {
            const cells = row.querySelectorAll("td");
            let matchFound = false;

            for (let i = 0; i < cells.length - 1; i++) { // Skip actions column
                const cellText = cells[i].textContent.toLowerCase();
                if (cellText.includes(searchTerm)) {
                    matchFound = true;
                    break;
                }
            }

            row.style.display = matchFound ? "" : "none";
        });
    }
}

// Helper function for debouncing (already included in previous code)
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Additional helper functions needed for the form
function generatePromoCode() {
    const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let promoCode = '';
    const length = 8;

    for (let i = 0; i < length; i++) {
        promoCode += characters.charAt(Math.floor(Math.random() * characters.length));
    }

    const promoCodeInput = document.getElementById('promo_code');
    if (promoCodeInput) {
        promoCodeInput.value = promoCode;
    }
}

function addCharacterCounter(textareaId, charCountId) {
    const textarea = document.getElementById(textareaId);
    const charCount = document.getElementById(charCountId);

    if (!textarea || !charCount) return;

    const maxLength = textarea.maxLength;

    function updateCharCount() {
        const remaining = maxLength - textarea.value.length;
        charCount.textContent = `${remaining} characters remaining`;
        charCount.style.color = remaining < 0 ? 'red' : '#555';
    }

    textarea.addEventListener('input', updateCharCount);
    updateCharCount(); // Initial call
}
// Initialize everything
document.addEventListener('DOMContentLoaded', () => {
    initializeDOM();
    updateDiscountFields();
    setupEventDelegation();
    updateDateTimeInputs("start_date", "end_date");
    setupFormHandling();
    fetchPromos(currentPage);

    // Generate initial promo code
    generatePromoCode();
    // Reattach change event on discount type if needed
    discountTypeSelect.addEventListener("change", () => {
        updateDiscountFields(discountTypeSelect, discountInput, maxDiscount);
    });

    // Run once on load to apply correct rules
    updateDiscountFields(discountTypeSelect, discountInput, maxDiscount);
});