document.addEventListener('DOMContentLoaded', () => {
    fetchData('backend/fetch_revenue_data.php', (data) => {
        document.getElementById('totalRevenue').textContent = data.totalRevenue;
        document.getElementById('pendingOrders').textContent = data.pendingOrders;
        document.getElementById('approvedOrders').textContent = data.approvedOrders;
        document.getElementById('declinedOrders').textContent = data.declinedOrders;
        document.getElementById('transitOrders').textContent = data.transitOrders;
        document.getElementById('deliveredOrders').textContent = data.deliveredOrders;
        document.getElementById('cancelledOrders').textContent = data.cancelledOrders;
        document.getElementById('assignedOrders').textContent = data.assignedOrders;

        // Create charts
        createPieChart(data);
        createLineChart(data);
        createBarChart(data);
        createDoughnutChart(data);

        const revenueTableBody = document.querySelector('#revenueTable tbody');
        revenueTableBody.innerHTML = '';
        data.recentTransactions.forEach(transaction => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${transaction.order_id}</td>
                <td>${transaction.customer_id}</td>
                <td>${transaction.total_amount}</td>
                <td>${transaction.transaction_date}</td>
                <td class="status">${transaction.status}</td>
                <td>${transaction.delivery_status || 'N/A'}</td>
                <td>${transaction.firstname && transaction.lastname ? `${transaction.firstname} ${transaction.lastname}` : 'No Driver Assigned'}</td>
                <td>${transaction.updated_at}</td>
            `;
            revenueTableBody.appendChild(row);
        });

        document.querySelectorAll('.status').forEach(cell => {
            switch (cell.textContent) {
                case 'Pending':
                    cell.style.color = 'orange';
                    cell.style.padding = '5px';
                    break;
                case 'Approved':
                    cell.style.color = 'green';
                    cell.style.padding = '5px';
                    break;
                case 'Declined':
                    cell.style.color = 'red';
                    cell.style.padding = '5px';
                    break;
            }
        });
    });
});

function fetchData(url, callback) {
    fetch(url)
        .then(response => response.json())
        .then(data => callback(data))
        .catch(error => console.error('Error fetching data:', error));
}

function createPieChart(data) {
    const ctx = document.getElementById('revenuePieChart').getContext('2d');
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Pending', 'Approved', 'Declined', 'Cancelled'],
            datasets: [{
                data: [data.pendingOrders, data.approvedOrders, data.declinedOrders, data.cancelledOrders],
                backgroundColor: ['orange', 'green', 'red', 'black']
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
                    text: 'Order Status Distribution'
                }
            }
        }
    });
}

function createLineChart(data) {
    const ctx = document.getElementById('revenueLineChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.recentTransactions.map(transaction => transaction.transaction_date),
            datasets: [{
                label: 'Total Amount',
                data: data.recentTransactions.map(transaction => transaction.total_amount),
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
                    text: 'Revenue Over Time'
                }
            }
        }
    });
}

function createBarChart(data) {
    const ctx = document.getElementById('revenueBarChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.recentTransactions.map(transaction => transaction.transaction_date),
            datasets: [{
                label: 'Total Amount',
                data: data.recentTransactions.map(transaction => transaction.total_amount),
                backgroundColor: 'blue'
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
    const ctx = document.getElementById('revenueDoughnutChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'Approved', 'Declined', 'Cancelled'],
            datasets: [{
                data: [data.pendingOrders, data.approvedOrders, data.declinedOrders, data.cancelledOrders],
                backgroundColor: ['orange', 'green', 'red', 'black']
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
                    text: 'Order Status Doughnut Chart'
                }
            }
        }
    });

}
function openModal() {
    document.getElementById('revenueTypeModal').style.display = 'block';
}
function openModal2(url) {
    location.href = url;
}
function closeModal() {
    document.getElementById('revenueTypeModal').style.display = 'none';
}

// Close modal if user clicks outside of it
window.onclick = function(event) {
    const modal = document.getElementById('revenueTypeModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}

document.getElementById("newRevenueTypeForm").addEventListener('submit', (event) => {
    event.preventDefault();

    // Use event.target to refer to the form element
    const formData = new FormData(event.target);

    fetch('backend/create_revenue_type.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('New Revenue Type added Successfully!');
            closeModal();
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error occurred while adding revenue type');
    });
});
