document.addEventListener('DOMContentLoaded', function () {
    // Dummy data, replace with real data from the server
    const customerData = {
        name: 'John Doe',
        walletBalance: 100.00,
        orders: [
            { orderId: 1, date: '2023-06-25', items: 'Burger, Fries', total: 15.00, status: 'Delivered' },
            { orderId: 2, date: '2023-06-26', items: 'Pizza', total: 20.00, status: 'Pending' }
        ]
    };

    // Populate customer information
    document.getElementById('customerName').textContent = `Welcome, ${customerData.name}`;
    document.getElementById('walletBalance').textContent = `Wallet Balance: $${customerData.walletBalance.toFixed(2)}`;

    // Populate order history
    const orderHistoryBody = document.getElementById('orderHistoryBody');
    customerData.orders.forEach(order => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${order.orderId}</td>
            <td>${order.date}</td>
            <td>${order.items}</td>
            <td>$${order.total.toFixed(2)}</td>
            <td>${order.status}</td>
        `;
        orderHistoryBody.appendChild(row);
    });

    // Navigation logic
    const sections = {
        orderFood: document.getElementById('orderSection'),
        viewOrders: document.getElementById('orderHistory'),
        fundWallet: document.getElementById('fundSection')
    };

    document.querySelectorAll('nav ul li a').forEach(link => {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            const sectionId = this.id;
            for (let key in sections) {
                sections[key].classList.remove('active');
            }
            sections[sectionId].classList.add('active');
        });
    });

    // Default section
    sections.orderFood.classList.add('active');

    // Fund wallet form submission
    document.getElementById('fundForm').addEventListener('submit', function (event) {
        event.preventDefault();
        const amount = parseFloat(document.getElementById('amount').value);
        if (isNaN(amount) || amount <= 0) {
            alert('Please enter a valid amount');
            return;
        }
        // Add logic to update wallet balance
        customerData.walletBalance += amount;
        document.getElementById('walletBalance').textContent = `Wallet Balance: $${customerData.walletBalance.toFixed(2)}`;
        alert('Wallet funded successfully!');
        this.reset();
    });

    // Logout button
    document.getElementById('logoutButton').addEventListener('click', function () {
        // Add logic to handle logout
        alert('Logged out');
        window.location.href = 'customer_login.html'; // Redirect to login page
    });

    // ORDER FOOD SCRIPT
    const foodItemsContainer = document.getElementById('foodItemsContainer');
    const addFoodItemButton = document.getElementById('addFoodItemButton');
    const totalPriceElement = document.getElementById('totalPrice');
    let totalPrice = 0;

    const foodItems = [
        { id: 1, name: 'Jollof Rice', price: 5.00 },
        { id: 2, name: 'Pizza', price: 10.00 },
        { id: 3, name: 'Burger', price: 7.50 },
        { id: 4, name: 'Fries', price: 3.00 },
        // Add more food items here
    ];

    function createFoodItem() {
        const foodItemDiv = document.createElement('div');
        foodItemDiv.className = 'food-item';

        const foodSelect = document.createElement('select');
        foodSelect.name = 'food_id[]';
        foodItems.forEach(food => {
            const option = document.createElement('option');
            option.value = food.id;
            option.textContent = `${food.name} - $${food.price.toFixed(2)}`;
            option.dataset.price = food.price;
            foodSelect.appendChild(option);
        });

        const quantityInput = document.createElement('input');
        quantityInput.type = 'number';
        quantityInput.name = 'quantity[]';
        quantityInput.min = 1;
        quantityInput.value = 1;

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.textContent = 'Remove';
        removeButton.addEventListener('click', () => {
            foodItemsContainer.removeChild(foodItemDiv);
            updateTotalPrice();
        });

        foodSelect.addEventListener('change', updateTotalPrice);
        quantityInput.addEventListener('input', updateTotalPrice);

        foodItemDiv.appendChild(foodSelect);
        foodItemDiv.appendChild(quantityInput);
        foodItemDiv.appendChild(removeButton);
        foodItemsContainer.appendChild(foodItemDiv);

        updateTotalPrice();
    }

    function updateTotalPrice() {
        totalPrice = 0;
        const foodItemDivs = foodItemsContainer.querySelectorAll('.food-item');
        foodItemDivs.forEach(div => {
            const select = div.querySelector('select');
            const quantityInput = div.querySelector('input');
            const price = parseFloat(select.options[select.selectedIndex].dataset.price);
            const quantity = parseInt(quantityInput.value, 10);
            totalPrice += price * quantity;
        });
        totalPriceElement.textContent = totalPrice.toFixed(2);
    }

    addFoodItemButton.addEventListener('click', createFoodItem);

    document.getElementById('orderForm').addEventListener('submit', function (event) {
        event.preventDefault();
        const formData = new FormData(this);
        const data = {};
        formData.forEach((value, key) => {
            if (!data[key]) {
                data[key] = [];
            }
            data[key].push(value);
        });
        // Send order data to the server
        fetch('backend/place_order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Order placed successfully!');
                this.reset();
                foodItemsContainer.innerHTML = '';
                createFoodItem(); // Add one food item by default
                updateTotalPrice();
            } else {
                alert('Failed to place order: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    });

    // Initialize with one food item
    createFoodItem();
});
