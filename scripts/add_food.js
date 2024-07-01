// script.js
document.getElementById('food-form').addEventListener('submit', function(event) {
    event.preventDefault();
    
    const formData = new FormData(this);
    const data = {};
    formData.forEach((value, key) => data[key] = value);

    fetch('backend/add_food.php', {  // Update with the actual path to your PHP script
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
            message.textContent = 'Food item added successfully!';
            document.getElementById('food-form').reset();
        } else {
            message.style.color = 'red';
            message.textContent = 'Failed to add food item: ' + data.message;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('message').textContent = 'An error occurred. Please try again.';
    });
});
