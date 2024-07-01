// document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('DOMContentLoaded', () => {
        const availableSelect = document.getElementById('available');
        const quantityInput = document.getElementById('quantity');
        const quantity2 = document.getElementById('quantity2');
    
        // Event listener to update quantity based on availability
        availableSelect.addEventListener('change', function() {
            if (this.value === '0') { // '0' corresponds to "No"
                quantityInput.value = 0;
                quantityInput.setAttribute('readonly', true); // Make quantity readonly if unavailable
            } else {
                quantityInput.removeAttribute('readonly'); // Allow editing if available
                quantityInput.value = quantity2.value;
            }
        });


    document.getElementById('Updatefood-form').addEventListener('submit', function(event) {
        event.preventDefault();
        const formData = new FormData(this);
        const data = {};
        formData.forEach((value, key) => data[key] = value);
        data.id = document.getElementById('food_id').value; // Assuming you have an input with the id 'food-id'

        fetch('backend/update_food.php', {  // Update with the actual path to your PHP script
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            const message = document.getElementById('message');
            if (data.success) {
                message.style.color = 'green';
                message.textContent = 'Food item Updated successfully!';
                alert('Food item updated successfully!');
                document.getElementById('Updatefood-form').reset();
                location.href="food.php";
                // this.reset();
            }
            else{
                message.style.color = 'red';
            message.textContent = 'Failed to Update food item: ' + data.message;
            alert('Failed o Update Food Item:' + data.message)
            }
        })
        .catch(error => {
            // showMessage(false, 'An error occurred. Please try again.');
            console.error('Error:', error);
            document.getElementById('message').textContent = 'An error occurred. Please try again.';
            alert('An Error Occured, Please try again');
            
        });
    });
});
