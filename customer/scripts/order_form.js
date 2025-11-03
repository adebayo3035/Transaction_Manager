const orderSummaryTable = document.getElementById('orderSummaryTable').querySelector('tbody');
let orderItems = []; // This is now properly global
document.addEventListener('DOMContentLoaded', () => {
    const createPackButton = document.getElementById('createPackButton');
    const closePackButton = document.getElementById('closePack');
    const addFoodButton = document.getElementById('addFoodButton');

    if (createPackButton) createPackButton.addEventListener('click', createPack);
    // if (closePackButton) closePackButton.addEventListener('click', closePack);

    // Fetch available food items from the backend API
    fetch('../v2/get_food.php')
        .then(response => response.json())
        .then(data => {
            const foodSelect = document.getElementById('food-name');
            data.forEach(item => {
                let option = document.createElement('option');
                option.value = item.food_id;
                option.setAttribute('data-price', item.food_price);
                option.setAttribute('data-food_name', item.food_name);
                option.text = item.food_name;
                foodSelect.appendChild(option);
            });
        })
        .catch(error => console.error('Error fetching food items:', error));

    addFoodButton.addEventListener('click', function () {
        const foodSelect = document.getElementById('food-name');
        const packSelect = document.getElementById('pack-selector');
        const foodId = foodSelect.value;
        const packId = packSelect.value;
        const foodName = foodSelect.options[foodSelect.selectedIndex].getAttribute('data-food_name');
        const foodPrice = parseFloat(foodSelect.options[foodSelect.selectedIndex].getAttribute('data-price'));
        const quantityInput = document.getElementById('number-of-portion');
        const quantity = parseInt(quantityInput.value);

        // Input validation
        if (!packId || packId === '') {
            alert('Please select a pack first');
            return;
        }

        if (isNaN(quantity) || isNaN(foodPrice) || quantity <= 0) {
            alert("Please select a food item and enter a valid portion quantity");
            return;
        }

        if (!foodId) {
            alert('Please select a food item');
            return;
        }

        // Check if food item already exists in THIS PACK
        const existingRow = findFoodItemInTable(foodId, packId);
        if (existingRow) {
            showDuplicateItemModal(foodName, existingRow);
            return;
        }

        // If food item doesn't exist, proceed with adding it
        addNewFoodItem(foodId, foodName, foodPrice, quantity, packId);
    });

    submitOrderButton.addEventListener('click', function (event) {
        event.preventDefault();

        // Validate cart and packs
        if (orderItems.length === 0) {
            alert('Your cart is empty. Please add items before checkout.');
            return;
        }

        // Calculate pack count and organize items by pack
        const packCount = document.getElementById('packQuantity').value;
        const packs = {};

        orderItems.forEach(item => {
            if (!packs[item.pack_id]) {
                packs[item.pack_id] = [];
            }
            packs[item.pack_id].push(item);
        });

        // Verify all packs have items
        const actualPackCount = Object.keys(packs).length;
        if (actualPackCount < packCount) {
            alert(`Warning: You created ${packCount} packs but only ${actualPackCount} contain items.`);
            return;
        }

        // Calculate totals
        const totalAmount = orderItems.reduce((sum, item) => sum + item.total_price, 0);
        const serviceFee = 0.06 * totalAmount;
        const deliveryFee = 0.10 * totalAmount;

        // Prepare data for backend
        const orderData = {
            order_items: orderItems,
            packs: packs, // Organized by pack
            pack_count: packCount,
            total_order: totalAmount.toFixed(2),
            service_fee: serviceFee.toFixed(2),
            delivery_fee: deliveryFee.toFixed(2)
        };

        console.log("Submitting order:", orderData);

        // Two-step validation and submission
        fetch('../v2/validate_quantities.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(orderData)
        })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Validation failed');
                }
                return fetch('../v2/save_order_session.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(orderData)
                });
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = '../v1/checkout.php';
                } else {
                    throw new Error(data.message || 'Failed to save order');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Checkout error: ' + error.message);
            });
    });
});

const createPack = () => {
    const packQuantityEl = document.getElementById('packQuantity');
    const packSelector = document.getElementById('pack-selector');
    const packContainer = document.getElementById('pack-container');
    const orderSummaryContainer = document.getElementById('order-summary-container');

    if (!packQuantityEl || !packSelector) return;

    // parse and validate quantity
    const qty = parseInt(packQuantityEl.value, 10);
    if (Number.isNaN(qty) || qty < 1) {
        if (typeof showErrorModal === 'function') showErrorModal('Enter a valid pack quantity (minimum 1).');
        else alert('Enter a valid pack quantity (minimum 1).');
        return;
    }

    const confirmed = window.confirm(`Create ${qty} pack${qty > 1 ? 's' : ''}? This will lock the pack count.`);
    if (!confirmed) return;

    // clear any existing options
    packSelector.innerHTML = '';
    // add default placeholder option
    const defaultOpt = document.createElement('option');
    defaultOpt.value = '';
    defaultOpt.textContent = '-- Select a Pack --';
    defaultOpt.disabled = true;
    defaultOpt.selected = true;
    packSelector.appendChild(defaultOpt);
    // create options Pack-1 ... Pack-N
    for (let i = 1; i <= qty; i++) {
        const opt = document.createElement('option');
        opt.value = `Pack-${i}`;
        opt.textContent = `Pack-${i}`;
        packSelector.appendChild(opt);
    }

    // disable the input and create button to lock the pack count
    packQuantityEl.disabled = true;
    const createBtn = document.getElementById('createPackButton');
    if (createBtn) createBtn.disabled = true;
    packContainer.style.display = 'flex';
    orderSummaryContainer.style.display = "flex";

    // focus the selector so user can pick a pack
    packSelector.focus();

    packSelector.addEventListener('change', () => {
        const foodContainer = document.getElementById('food-selector-container');
        foodContainer.style.display = 'flex';
        const packNumberSpan = document.getElementById('pack-number');
        if (packNumberSpan) packNumberSpan.textContent = packSelector.value;
        // Don't disable the pack selector here - let user switch between packs
    });
};

// function closePack() {
//     const packSelector = document.getElementById('pack-selector');
//     const foodContainer = document.getElementById('food-selector-container');

//     if (!packSelector) return;

//     const selectedValue = packSelector.value;
//     if (!selectedValue) {
//         alert('Please select a pack before closing.');
//         return;
//     }

//     // Reset dropdown to default placeholder option
//     packSelector.value = '';

//     // Hide food selector container
//     if (foodContainer) {
//         foodContainer.style.display = 'none';
//     }
// }

function updateTotalAmount() {
    const totalAmountElement = document.getElementById('totalAmount');
    const totalAmountInput = document.getElementById('total_amount_input');
    let totalAmount = orderItems.reduce((sum, item) => sum + item.total_price, 0);
    totalAmountElement.textContent = `N ${totalAmount.toFixed(2)}`;
    totalAmountInput.value = totalAmount.toFixed(2);
}

// Helper function to find existing food item in table (now checks pack too)
function findFoodItemInTable(foodId, packId) {
    const rows = document.querySelectorAll('#orderSummaryTable tbody tr');
    for (const row of rows) {
        const rowFoodId = row.getAttribute('data-food-id');
        const rowPackId = row.cells[1].textContent;
        if (rowFoodId === foodId && rowPackId === packId) {
            return row;
        }
    }
    return null;
}

function showDuplicateItemModal(foodName, existingRow) {
    const modal = document.createElement('div');
    modal.className = 'duplicate-item-modal';
    modal.innerHTML = `
        <div class="modal-content">
            <h3>Item Already Added</h3>
            <p>"${foodName}" is already in this pack.</p>
            <p>Would you like to edit the existing item instead?</p>
            <div class="modal-buttons">
                <button id="edit-existing">Edit Existing</button>
                <button id="cancel-add">Cancel</button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    document.getElementById('edit-existing').addEventListener('click', function () {
        document.body.removeChild(modal);
        const editButton = existingRow.querySelector('.edit-button');
        editButton.click();
    });

    document.getElementById('cancel-add').addEventListener('click', function () {
        document.body.removeChild(modal);
    });
}

function addNewFoodItem(foodId, foodName, foodPrice, quantity, packId) {
    const totalPrice = foodPrice * quantity;
    const orderItem = {
        food_id: foodId,
        food_name: foodName,
        quantity: quantity,
        price_per_unit: foodPrice,
        total_price: totalPrice,
        pack_id: packId
    };

    // Add to array
    orderItems.push(orderItem);

    // ✅ Sort items alphabetically by packId (case-insensitive)
    orderItems.sort((a, b) => a.pack_id.localeCompare(b.pack_id, undefined, { sensitivity: 'base' }));

    // ✅ Re-render table
    renderOrderTable();
    updateTotalAmount();

    // Clear input fields
    document.getElementById('food-name').value = "";
    document.getElementById('number-of-portion').value = "";
}

function renderOrderTable() {
    // Clear existing rows
    orderSummaryTable.innerHTML = "";

    // Recreate rows based on sorted orderItems
    orderItems.forEach((item, index) => {
        const row = document.createElement('tr');
        row.setAttribute('data-food-id', item.food_id);
        row.innerHTML = `
            <td>${index + 1}</td>
            <td>${item.pack_id}</td>
            <td>${item.food_name}</td>
            <td>${item.quantity}</td>
            <td>N ${item.price_per_unit.toFixed(2)}</td>
            <td class="total-price">N ${item.total_price.toFixed(2)}</td>
            <td class="buttons">
                <button type="button" class="edit-button">Edit</button>
                <button type="button" class="delete-button">Delete</button>
            </td>
        `;

        orderSummaryTable.appendChild(row);
        addRowEventListeners(row, item, item.price_per_unit);
    });
}


function addRowEventListeners(row, orderItem, foodPrice) {
    const editButton = row.querySelector('.edit-button');
    const deleteButton = row.querySelector('.delete-button');

    editButton.addEventListener('click', function () {
        const newQuantity = prompt("Enter new quantity:", orderItem.quantity);
        if (newQuantity !== null && !isNaN(newQuantity) && newQuantity > 0) {
            const newQuantityNum = parseInt(newQuantity);
            const newTotalPrice = foodPrice * newQuantityNum;

            // Update table row
            row.cells[3].innerText = newQuantityNum;
            row.cells[5].innerText = `N ${newTotalPrice.toFixed(2)}`;

            // Update order item
            orderItem.quantity = newQuantityNum;
            orderItem.total_price = newTotalPrice;

            updateTotalAmount();
        }
    });

    deleteButton.addEventListener('click', function () {
        if (confirm('Are you sure you want to remove this item?')) {
            const index = orderItems.indexOf(orderItem);
            if (index > -1) {
                orderItems.splice(index, 1);
            }
            row.remove();
            updateTotalAmount();
            updateRowNumbers();
        }
    });
}

function updateRowNumbers() {
    const rows = document.querySelectorAll('#orderSummaryTable tbody tr');
    rows.forEach((row, index) => {
        row.cells[0].innerText = index + 1;
    });
}