document.addEventListener('DOMContentLoaded', function () {
    const addFoodButton = document.getElementById('addFoodButton');
    const submitOrderButton = document.getElementById('submitOrderButton');
    const orderSummaryTable = document.getElementById('orderSummaryTable').querySelector('tbody');
    let orderItems = [];

    // Fetch available food items from the backend API
    fetch('../v2/get_food.php')
        .then(response => response.json())
        .then(data => {
            const foodSelect = document.getElementById('food-name');
            
            data.forEach(item => {
                let option = document.createElement('option');
                option.value = item.food_id;
                option.setAttribute('data-price', item.food_price);
                option.text = item.food_name;
                foodSelect.appendChild(option);
            });
        })
        .catch(error => console.error('Error fetching food items:', error));

    addFoodButton.addEventListener('click', function () {
        const foodSelect = document.getElementById('food-name');
        const foodId = foodSelect.value;
        const foodName = foodSelect.options[foodSelect.selectedIndex].text;
        const foodPrice = parseFloat(foodSelect.options[foodSelect.selectedIndex].getAttribute('data-price'));
        const quantityInput = document.getElementById('number-of-portion');
        const quantity = parseInt(quantityInput.value);

        if (isNaN(quantity) || isNaN(foodPrice)) {
            console.error('Invalid quantity or price');
            alert("Please select a food item and Portion");
            return;
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
                <td class="buttons">
                    <button type="button" class="edit-button">Edit</button>
                    <button type="button" class="delete-button">Delete</button>
                </td>
            `;

            orderSummaryTable.appendChild(row);
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
                    row.cells[4].innerText = `N ${newTotalPrice.toFixed(2)}`;

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
        // Check if the cart (order items) is empty before proceeding
        if (orderItems.length === 0) {
            alert('Your cart is empty. Please add items to your cart before proceeding to checkout.');
            return; // Stop further execution
        }
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


                // Save the order details in sessionStorage
                sessionStorage.setItem('order_items', JSON.stringify(orderItems));

                // Store total amount
                const totalAmount = orderItems.reduce((sum, item) => sum + item.total_price, 0);
                sessionStorage.setItem('total_amount', totalAmount.toFixed(2));

                // adding service and delivery fees 
                let serviceFee = 0.06 * totalAmount; // 6% service fee
                let deliveryFee = 0.10 * totalAmount; // 10% delivery fee
                sessionStorage.setItem('service_fee', serviceFee.toFixed(2));
                sessionStorage.setItem('delivery_fee', deliveryFee.toFixed(2));

                // Redirect to checkout page
                window.location.href = '../v1/checkout.php';
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
        totalAmountElement.textContent = `N ${totalAmount.toFixed(2)}`;
        totalAmountInput.value = totalAmount.toFixed(2);
    }
});
