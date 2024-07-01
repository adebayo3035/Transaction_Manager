
function deleteFood(foodId) {
    if (confirm('Are you sure you want to delete this food item?')) {
        fetch(`backend/delete_food.php?id=${foodId}`, { method: 'GET' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Food item deleted successfully!');
                    // Reload the page or update the food list
                    location.reload();  // Or you can call a function to refresh the food list
                } else {
                    alert('Failed to delete food item: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error deleting food item:', error);
                alert('An error occurred. Please try again.');
            });
    }
}

