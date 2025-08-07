document.addEventListener('DOMContentLoaded', () => {
    fetchData('backend/fetch_rating_data.php', (data) => {
        // Update summary cards (unchanged)
        document.getElementById('totalRating').textContent = data.totalRating;
        document.getElementById('topRating').textContent = data.topRating;
        document.getElementById('averageRating').textContent = data.averageRating;
        document.getElementById('lowestRating').textContent = data.lowRating;

        // Update rating cards with new structure
        updateRatingCard('averageDriverRating', data.averageDriverRating);
        updateRatingCard('averageFoodRating', data.averageFoodRating);
        updateRatingCard('averagePackageRating', data.averagePackageRating);
        updateRatingCard('averagedeliveryTimeRating', data.averagedeliveryTimeRating);

        updateRatingCard('topDriverRating', data.topDriverRating);
        updateRatingCard('topFoodRating', data.topFoodRating);
        updateRatingCard('topPackageRating', data.topPackageRating);
        updateRatingCard('topDeliveryTimeRating', data.topDeliveryTimeRating);

        updateRatingCard('lowestDriverRating', data.lowestDriverRating);
        updateRatingCard('lowestFoodRating', data.lowestFoodRating);
        updateRatingCard('lowestPackageRating', data.lowestPackageRating);
        updateRatingCard('lowestDeliveryTimeRating', data.lowestDeliveryTimeRating);

        // Create charts (unchanged)
        createPieChart(data);
        createLineChart(data);
        createBarChart(data);
        createDoughnutChart(data);

        // Update rating table (unchanged)
        const ratingTableBody = document.querySelector('#ratingTable tbody');
        ratingTableBody.innerHTML = '';
        data.recentRatings.forEach(rating => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${rating.order_id}</td>
                <td>${rating.driver_id}</td>
                <td>${rating.customer_id}</td>
                <td>${rating.driver_rating}</td>
                <td>${rating.food_rating}</td>
                <td>${rating.packaging_rating}</td>
                <td>${rating.delivery_time_rating}</td>
                <td>${rating.rated_at}</td>
            `;
            ratingTableBody.appendChild(row);
        });

        // Status styling (unchanged)
        document.querySelectorAll('.status').forEach(cell => {
            switch (cell.textContent) {
                case 'Average Ratings':
                    cell.style.color = 'orange';
                    cell.style.padding = '5px';
                    break;
                case 'Top Ratings':
                    cell.style.color = 'green';
                    cell.style.padding = '5px';
                    break;
                case 'Low Ratings':
                    cell.style.color = 'red';
                    cell.style.padding = '5px';
                    break;
            }
        });
    });
});

// Helper function to update rating cards with order IDs
function updateRatingCard(elementId, ratingData) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    // Handle both old (number) and new (object) data formats
    if (typeof ratingData === 'object' && ratingData.value !== undefined) {
        // New format with order ID
        element.textContent = ratingData.value;
        element.setAttribute('title', `Order #${ratingData.order_id}`);
        element.innerHTML = `${ratingData.value}<br><small>Order: #${ratingData.order_id}</small>`;
        
        // Optional: Add click handler for more details
        element.style.cursor = 'pointer';
        element.addEventListener('click', () => {
            // You could show more details in a modal or alert
            console.log(`Order #${ratingData.order_id} - Rating: ${ratingData.value}`);
        });
    } else {
        // Fallback for simple numeric values
        element.textContent = ratingData;
    }
}

// Rest of your existing functions remain unchanged
function fetchData(url, callback) {
    fetch(url)
        .then(response => response.json())
        .then(data => callback(data))
        .catch(error => console.error('Error fetching data:', error));
}

function createPieChart(data) {
    const ctx = document.getElementById('ratingPieChart').getContext('2d');
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Top Rating', 'Average Rating', 'Low Rating'],
            datasets: [{
                data: [data.topRating, data.averageRating, data.lowRating],
                backgroundColor: ['green', 'orange', 'red']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Rating Distribution'
                }
            }
        }
    });
}

function createLineChart(data) {
    // Get the last 5 ratings
    const lastFiveRatings = data.recentRatings.slice(0,5);
    
    const ctx = document.getElementById('ratingLineChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: lastFiveRatings.map(rating => rating.rated_at),
            datasets: [{
                label: 'Total Rating',
                data: lastFiveRatings.map(rating => rating.dailyAverageRating),
                borderColor: 'blue',
                fill: false
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Rating Over Time (Last 5 Transactions)'
                }
            }
        }
    });
}

function createBarChart(data) {
    //Get last 5 transactions
     const lastFiveRatings = data.recentRatings.slice(0,5);
    const ctx = document.getElementById('ratingBarChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: lastFiveRatings.map(rating => rating.rated_at),
            datasets: [{
                label: 'Total Ratings',
                data: lastFiveRatings.map(rating => rating.dailyAverageRating),
                backgroundColor: 'purple'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Revenue per Transaction'
                }
            }
        }
    });
}

function createDoughnutChart(data) {
    const ctx = document.getElementById('ratingDoughnutChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Top Rating', 'Average Rating', 'Low Rating'],
            datasets: [{
                data: [data.topRating, data.averageRating, data.lowRating],
                backgroundColor: ['green', 'orange', 'red']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Rating Distribution'
                }
            }
        }
    });
}
const openRatings = () => {
    window.location.href = 'ratings.php';
};