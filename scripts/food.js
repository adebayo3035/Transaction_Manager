// Modal Functions
function toggleModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";
}

function setupModalCloseListeners() {
    document.querySelector('.modal .modal-content .close2').addEventListener('click', () => {
        document.getElementById('addNewFoodModal').style.display = 'none';
    });

    document.querySelector('.modal .close').addEventListener('click', () => {
        document.getElementById('orderModal').style.display = 'none';
    });

    window.addEventListener('click', (event) => {
        document.querySelectorAll('.modal').forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });
}

// Food Management Functions
class FoodManager {
    constructor() {
        this.limit = 10;
        this.currentPage = 1;
        this.init();
    }

    init() {
        setupModalCloseListeners();
        this.fetchFoods();
        this.setupSearchListener();
    }

    fetchFoods(page = 1) {
        const tableBody = document.querySelector('#ordersTable tbody');
        this.showLoadingSpinner(tableBody);

        // Get raw filter values
        let availability = document.querySelector('#availabilityFilter').value;
        let minPrice = document.querySelector('#minPrice').value.trim();
        let maxPrice = document.querySelector('#maxPrice').value.trim();

        // ---- Validation ----
        // Ensure availability is only "" (all), "1", or "0"
        if (!['', '0', '1'].includes(availability)) {
            alert("Invalid availability filter");
            availability = '';
        }

        // Ensure min/max are numeric & >= 0
        if (minPrice !== '' && (!/^\d+$/.test(minPrice) || parseInt(minPrice) < 0)) {
            alert("Invalid Min Price");
            minPrice = '';
        }
        if (maxPrice !== '' && (!/^\d+$/.test(maxPrice) || parseInt(maxPrice) < 0)) {
            alert("Invalid Max Price");
            maxPrice = '';
        }

        // Ensure min ≤ max (if both are set)
        if (minPrice !== '' && maxPrice !== '' && parseInt(minPrice) > parseInt(maxPrice)) {
            alert("Min Price cannot be greater than Max Price");
            location.reload()
            return; // stop fetch
        }

        // Build query string
        const queryParams = new URLSearchParams({
            page,
            limit: this.limit
        });
        if (availability !== '') queryParams.append('availability_status', availability);
        if (minPrice !== '') queryParams.append('price_min', minPrice);
        if (maxPrice !== '') queryParams.append('price_max', maxPrice);

        // Fetch with filters
        fetch(`backend/fetch_foods.php?${queryParams.toString()}`, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.foods.length > 0) {
                    this.updateTable(data.foods, data.user_role);
                    this.updatePagination(data.pagination.total, data.pagination.page, data.pagination.limit);
                } else {
                    this.showNoDataMessage(tableBody);
                }
            })
            .catch(error => this.handleFetchError(tableBody, error));
    }



    showLoadingSpinner(tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align:center;">
                    <div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: auto;"></div>
                </td>
            </tr>
        `;
    }

    showNoDataMessage(tableBody) {
        tableBody.innerHTML = `<tr><td colspan="7" style="text-align:center;">No Food at the moment</td></tr>`;
    }

    handleFetchError(tableBody, error) {
        console.error('Error fetching data:', error);
        tableBody.innerHTML = `<tr><td colspan="7" style="text-align:center; color:red;">Error loading food data</td></tr>`;
    }

    updatePagination(totalItems, currentPage, itemsPerPage) {
        const paginationContainer = document.getElementById('pagination');
        paginationContainer.innerHTML = '';
        const totalPages = Math.ceil(totalItems / itemsPerPage);

        this.createPaginationButton('« First', 1, currentPage === 1, paginationContainer);
        this.createPaginationButton('‹ Prev', currentPage - 1, currentPage === 1, paginationContainer);

        const maxVisible = 2;
        const start = Math.max(1, currentPage - maxVisible);
        const end = Math.min(totalPages, currentPage + maxVisible);

        for (let i = start; i <= end; i++) {
            const btn = document.createElement('button');
            btn.textContent = i;
            if (i === currentPage) btn.classList.add('active');
            btn.addEventListener('click', () => this.fetchFoods(i));
            paginationContainer.appendChild(btn);
        }

        this.createPaginationButton('Next ›', currentPage + 1, currentPage === totalPages, paginationContainer);
        this.createPaginationButton('Last »', totalPages, currentPage === totalPages, paginationContainer);
    }

    createPaginationButton(label, page, disabled, container) {
        const btn = document.createElement('button');
        btn.textContent = label;
        if (disabled) btn.disabled = true;
        btn.addEventListener('click', () => this.fetchFoods(page));
        container.appendChild(btn);
    }

    updateTable(foods, userRole) {
        const ordersTableBody = document.querySelector('#ordersTable tbody');
        ordersTableBody.innerHTML = '';

        foods.forEach(food => {
            const row = document.createElement('tr');
            const status = food.availability_status == 1 ? "Available" : "Not Available";

            // Start building the row HTML
            let rowHTML = `
            <td>${food.food_id}</td>
            <td>${food.food_name}</td>
            <td>${food.food_price}</td>
            <td>${food.available_quantity}</td>
            <td>${status}</td>
            <td><span class='edit-icon' data-food-id="${food.food_id}">&#9998;</span></td>
        `;

            // Add delete icon only for Super Admin
            if (userRole === "Super Admin") {
                rowHTML += `<td><span class='delete-icon' data-food-id="${food.food_id}">&#128465;</span></td>`;
            } else {
                rowHTML += `<td></td>`; // keep table structure consistent
            }

            row.innerHTML = rowHTML;
            ordersTableBody.appendChild(row);
        });

        this.setupEditDeleteListeners();
    }


    setupEditDeleteListeners() {
        document.querySelectorAll('.edit-icon').forEach(span => {
            span.addEventListener('click', (event) => {
                const foodId = event.target.getAttribute('data-food-id');
                this.fetchFoodDetails(foodId);
            });
        });

        document.querySelectorAll('.delete-icon').forEach(span => {
            span.addEventListener('click', (event) => {
                const foodId = event.target.getAttribute('data-food-id');
                this.deleteFood(foodId);
            });
        });
    }

    fetchFoodDetails(foodId) {
        fetch(`backend/fetch_food_details.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ food_id: foodId })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.populateFoodDetails(data.food_details);
                    document.getElementById('orderModal').style.display = 'block';
                } else {
                    console.error('Failed to fetch food details:', data.message);
                }
            })
            .catch(error => console.error('Error fetching food details:', error));
    }

    populateFoodDetails(foodDetails) {
        const orderDetailsTable = document.querySelector('#orderDetailsTable tbody');
        orderDetailsTable.innerHTML = `
            <tr>
                <td>Food ID</td>
                <td><input type="text" id="food_id" value="${foodDetails.food_id}" disabled></td>
            </tr>
            <tr>
                <td>Food Name</td>
                <td><input type="text" id="food_name" value="${foodDetails.food_name}"></td>
            </tr>
            <tr>
                <td>Price</td>
                <td><input type="number" id="food_price" value="${foodDetails.food_price}"></td>
            </tr>
            <tr>
                <td>Description</td>
                <td><input type="text" id="food_description" value="${foodDetails.food_description}"></td>
            </tr>
            <tr>
                <td>Available Quantity</td>
                <td><input type="number" id="food_quantity" value="${foodDetails.available_quantity}"></td>
            </tr>
            <tr>
                <td>Availability Status</td>
                <td>
                    <select id="availableStatus" class="availableStatus">
                        <option value="">--Select Status--</option>
                        <option value="1" ${foodDetails.availability_status == 1 ? 'selected' : ''}>Available</option>
                        <option value="0" ${foodDetails.availability_status == 0 ? 'selected' : ''}>Not Available</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="text-align: center;">
                    <button id="updatefoodBtn">Update</button>
                </td>
            </tr>
        `;

        document.getElementById('updatefoodBtn').addEventListener('click', (event) => {
            event.preventDefault();
            if (confirm('Are you sure you want to Update food Details?')) {
                this.updateFood(foodDetails.food_id);
            }
        });
    }

    updateFood(foodId) {
        const foodData = {
            food_id: foodId,
            food_name: document.getElementById('food_name').value,
            food_price: document.getElementById('food_price').value,
            food_description: document.getElementById('food_description').value,
            food_quantity: document.getElementById('food_quantity').value,
            available_status: document.getElementById('availableStatus').value
        };

        fetch('backend/update_food.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(foodData)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Food Details has been successfully Updated.');
                    document.getElementById('orderModal').style.display = 'none';
                    location.reload();
                } else {
                    alert('Failed to update food Data: ' + data.message);
                }
            })
            .catch(error => console.error('Error updating food Details:', error));
    }

    deleteFood(foodId) {
        if (confirm('Are you sure you want to delete this food Item?')) {
            fetch('backend/delete_food.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ food_id: foodId })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Food has been successfully deleted!');
                        location.reload();
                    } else {
                        console.error('Failed to delete food:', data.message);
                        alert('Failed to delete food:' + data.message);
                    }
                })
                .catch(error => console.error('Error deleting food:', error));
        }
    }

    addNewFood(form) {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const foodData = {
                food_name: document.getElementById('add_food_name').value,
                food_price: document.getElementById('add_food_price').value,
                food_description: document.getElementById('add_food_description').value,
                food_quantity: document.getElementById('add_food_quantity').value,
                available_status: document.getElementById('available').value
            };

            const messageDiv = document.getElementById('addFoodMessage');

            if (confirm('Are you sure you want to add new Food Item?')) {
                fetch('backend/add_food.php', {
                    method: 'POST',
                    body: JSON.stringify(foodData),
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            messageDiv.textContent = 'New food has been successfully added!';
                            alert('New food has been successfully added!');
                            location.reload();
                        } else {
                            messageDiv.textContent = data.message;
                        }
                    })
                    .catch(error => {
                        messageDiv.textContent = 'Error: ' + error.message;
                        alert('An error occurred. Please Try Again Later');
                    });
            }
        });
    }

    setupSearchListener() {
        document.getElementById("liveSearch").addEventListener("input", () => this.filterTable());
    }

    filterTable() {
        const searchTerm = document.getElementById("liveSearch").value.toLowerCase();
        const rows = document.querySelectorAll("#ordersTable tbody tr");

        rows.forEach(row => {
            const cells = row.getElementsByTagName("td");
            let matchFound = false;

            for (let i = 0; i < cells.length; i++) {
                if (cells[i].textContent.toLowerCase().includes(searchTerm)) {
                    matchFound = true;
                    break;
                }
            }

            row.style.display = matchFound ? "" : "none";
        });
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    const foodManager = new FoodManager();
    const addFoodForm = document.getElementById('addFoodForm');
    foodManager.addNewFood(addFoodForm);

    document.querySelector('#applyFiltersBtn').addEventListener('click', () => {
        foodManager.fetchFoods(1); // reload with page 1 and filters
    });

});