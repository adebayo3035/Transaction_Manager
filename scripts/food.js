function toggleModal(modalId) {
    let modal = document.getElementById(modalId);
    modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";

}
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
document.addEventListener('DOMContentLoaded', ()=>{
    function fetchfoods() {
        fetch(`backend/fetch_foods.php`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateTable(data.foods);
                } else {
                    console.error('Failed to fetch food Records:', data.message);
                }
            })
            .catch(error => console.error('Error fetching data:', error));
    }

    function updateTable(foods) {
        const ordersTableBody = document.querySelector('#ordersTable tbody');
        ordersTableBody.innerHTML = '';
        foods.forEach(food => {

            const row = document.createElement('tr');
            let status = "";
            if(food.availability_status == 1){
                status = "Available";
            }
            else{
                status = "Not Available"
            }
            row.innerHTML = `
                <td>${food.food_id}</td>
                <td>${food.food_name}</td>
                <td>${food.food_price}</td>
                <td>${food.available_quantity}</td>
                <td>${status}</td>
                <td> <span class='edit-icon' data-food-id = "${food.food_id}">&#9998;</span></td>
                <td> <span class='delete-icon' data-food-id = "${food.food_id}">&#128465;</span></td>
                
            `;
            ordersTableBody.appendChild(row);
        });

        // Attach event listeners to the edit icon buttons
        document.querySelectorAll('.edit-icon').forEach(span => {
            span.addEventListener('click', (event) => {
                const foodId = event.target.getAttribute('data-food-id');
                fetchfoodDetails(foodId);
            });
        });

         // Attach event listeners to the delete icon buttons
         document.querySelectorAll('.delete-icon').forEach(span => {
            span.addEventListener('click', (event) => {
                const foodId = event.target.getAttribute('data-food-id');
                deletefood(foodId);
            });
        });
    }
    fetchfoods();

    // Display Food Details
    function fetchfoodDetails(foodId) {
        fetch(`backend/fetch_food_details.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ food_id: foodId })
        })
            .then(response => response.json())
            .then(data => {
                console.log(data); // Log the entire response to the console
                if (data.success) {
                    populatefoodDetails(data.food_details);
                    console.log(data.logged_in_user_role);
                    document.getElementById('orderModal').style.display = 'block';
                } else {
                    console.error('Failed to fetch food details:', data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching food details:', error);
            });
    }

    function populatefoodDetails(food_details){
        const orderDetailsTable = document.querySelector('#orderDetailsTable tbody');
        // const isSuperAdmin = logged_in_user_role === 'Super Admin';
        // const disableAttribute = isSuperAdmin ? '' : 'disabled';
        orderDetailsTable.innerHTML = `
        <tr>
            <td>food ID</td>
            <td><input type="text" id="food_id" value="${food_details.food_id}" disabled></td>
        </tr>
        <tr>
            <td>food Name</td>
            <td><input type="text" id="food_name" value="${food_details.food_name}"></td>
        </tr>
         <tr>
            <td>Price</td>
            <td><input type="number" id="food_price" value="${food_details.food_price}"></td>
        </tr>
        <tr>
            <td>Price</td>
            <td><input type="text" id="food_description" value="${food_details.food_description}"></td>
        </tr>
         
         <tr>
            <td>Available Quantity</td>
            <td><input type="number" id="food_quantity" value="${food_details.available_quantity}"> </textarea></td>
        </tr>
        <tr>
            <td>Availability Status</td>
            <td>
                <select id="availableStatus" class="availableStatus">
                    <option value="">--Select Status--</option>
                    <option value="1">Available</option>
                    <option value="0">Not Available</option>
                    
                </select>
            </td>
        </tr>
        <tr>
                <td colspan="2" style="text-align: center;">
                    <button id="updatefoodBtn">Update</button>
                </td>
            </tr>
            
        `;
        // Event listeners for update and delete buttons
        document.getElementById('updatefoodBtn').addEventListener('click', () => {
            updatefood(food_details.food_id);
        });
    }

    function updatefood(foodId) {
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
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(foodData)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('food Details has been updated successfully.');
                    document.getElementById('orderModal').style.display = 'none';
                   location.reload();
                } else {
                    alert('Failed to update food Data: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error updating food Details:', error);
            });
    }

    // function to delete food item
    function deletefood(foodId) {
        if (confirm('Are you sure you want to delete this food?')) {
            fetch('backend/delete_food.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ food_id: foodId })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('food has been successfully deleted!');
                        
                       location.reload();
                    } else {
                        console.error('Failed to delete food:', data.message);
                        alert('Failed to delete food:' + data.message)
                    }
                })
                .catch(error => {
                    console.error('Error deleting food:', error);
                });
        }
    }

     // function to add new food
function addNewfood(form) {
    form.addEventListener('submit', function (event){
        event.preventDefault();
        const foodData = {
            food_name: document.getElementById('add_food_name').value,
            food_price: document.getElementById('add_food_price').value,
            food_description: document.getElementById('add_food_description').value,
            food_quantity: document.getElementById('add_food_quantity').value,
            available_status: document.getElementById('available').value
        };
        const messageDiv = document.getElementById('addFoodMessage');
        fetch('backend/add_food.php', {
            method: 'POST',
            body: JSON.stringify(foodData),
        })
        .then(response => response.json())
        .then(data =>{
            if(data.success){
                console.log('Success:', data.message);
                        messageDiv.textContent = 'New food has been successfully added!';
                        alert('New food has been successfully added!')
                        location.reload();
            }
            else{
                console.log('Error:', data.message);
                        messageDiv.textContent = data.message;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            messageDiv.textContent = 'Error: ' + error.message;
            alert('An error occurred. Please Try Again Later')
        });
    })
}
const addFoodForm = document.getElementById('addFoodForm');
addNewfood(addFoodForm);

    // live search to filter table
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


})