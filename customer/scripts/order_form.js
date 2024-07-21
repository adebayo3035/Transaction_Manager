document.addEventListener('DOMContentLoaded', function () {
    const addFoodButton = document.getElementById('addFoodButton');
    const submitOrderButton = document.getElementById('submitOrderButton');
    const orderSummaryTable = document.getElementById('orderSummaryTable').querySelector('tbody');
    let orderItems = [];

    addFoodButton.addEventListener('click', function () {
        const foodSelect = document.getElementById('food-name');
        const foodId = foodSelect.value;
        const foodName = foodSelect.options[foodSelect.selectedIndex].text;
        const foodPrice = parseFloat(foodSelect.options[foodSelect.selectedIndex].getAttribute('data-price'));
        const quantityInput = document.getElementById('number-of-portion');
        const quantity = parseInt(quantityInput.value);

        if (isNaN(quantity) || isNaN(foodPrice)) {
            console.error('Invalid quantity or price');
            return;
        } else {
            console.log("Quantity and Price are Valid Data Type");
        }

        if (foodId && quantity > 0) {
            let totalPrice = foodPrice * quantity;

            const orderItem = {
                food_id: foodId,
                food_name: foodName,
                quantity: parseInt(quantity),
                price_per_unit: parseFloat(foodPrice),
                total_price: parseFloat(totalPrice)
            };

            orderItems.push(orderItem);

            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${orderItems.length}</td>
                <td>${foodName}</td>
                <td>${quantity}</td>
                <td>N ${foodPrice.toFixed(2)}</td>
                <td class="total-price">N ${totalPrice.toFixed(2)}</td>
                <td>
                    <button type="button" class="edit-button">Edit</button>
                    <button type="button" class="delete-button">Delete</button>
                </td>
            `;

            orderSummaryTable.appendChild(row);
            // Clear input fields after adding food item
            foodSelect.value = "";
            quantityInput.value = "";

            // Add event listeners for edit and delete buttons
            const editButton = row.querySelector('.edit-button');
            const deleteButton = row.querySelector('.delete-button');

            editButton.addEventListener('click', function () {
                const newQuantity = prompt("Enter new quantity:", quantity);
                if (newQuantity !== null && !isNaN(newQuantity) && newQuantity > 0) {
                    const newTotalPrice = foodPrice * newQuantity;
                    row.cells[2].innerText = newQuantity;
                    row.cells[4].innerText = `$${newTotalPrice.toFixed(2)}`;

                    // Update order items array
                    orderItem.quantity = newQuantity;
                    orderItem.total_price = newTotalPrice;

                    updateTotalAmount();
                }
            });

            deleteButton.addEventListener('click', function () {
                const index = orderItems.indexOf(orderItem);
                if (index > -1) {
                    orderItems.splice(index, 1);
                }
                orderSummaryTable.removeChild(row);
                updateTotalAmount();
            });

            updateTotalAmount();
        } else {
            alert('Please select a food item and enter a valid quantity.');
        }
    });

    submitOrderButton.addEventListener('click', function () {
        console.log('Order Items:', JSON.stringify({ order_items: orderItems }));

        // Validate quantities before sending the request
        fetch('../v2/validate_quantities.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ order_items: orderItems })
        })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Failed to place order: ' + data.message);
                    return;
                }

                // Calculate the total amount
                let totalAmount = 0;
                orderItems.forEach(item => {
                    totalAmount += parseFloat(item.total_price);
                });

                console.log('Total Amount:', totalAmount);
                console.log("Data Type of Total amount is:", typeof(totalAmount));

                // Proceed with placing the order
                fetch('../v2/place_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ order_items: orderItems, total_amount: totalAmount })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Order placed successfully.' + data.message);
                            location.reload(); // Refresh the page
                        } else {
                            alert('Failed to place order: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('message').textContent = 'An error occurred. Please try again.';
                    });
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('message').textContent = 'An error occurred. Please try again.';
            });
    });

    function updateTotalAmount() {
        const totalAmountElement = document.getElementById('totalAmount');
        const totalAmountInput = document.getElementById('total_amount_input');
        let totalAmount = orderItems.reduce((sum, item) => sum + item.total_price, 0);
        totalAmountElement.textContent = totalAmount.toFixed(2);
        totalAmountInput.value = totalAmount.toFixed(2);
    }
});
