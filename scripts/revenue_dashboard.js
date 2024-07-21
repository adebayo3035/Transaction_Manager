document.addEventListener('DOMContentLoaded', () => {
    fetchData('backend/fetch_revenue_data.php', (data) => {
        document.getElementById('totalRevenue').textContent = data.totalRevenue;
        document.getElementById('pendingOrders').textContent = data.pendingOrders;
        document.getElementById('approvedOrders').textContent = data.approvedOrders;
        document.getElementById('declinedOrders').textContent = data.declinedOrders;

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
            labels: ['Pending', 'Approved', 'Declined'],
            datasets: [{
                data: [data.pendingOrders, data.approvedOrders, data.declinedOrders],
                backgroundColor: ['orange', 'green', 'red']
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
            labels: ['Pending', 'Approved', 'Declined'],
            datasets: [{
                data: [data.pendingOrders, data.approvedOrders, data.declinedOrders],
                backgroundColor: ['orange', 'green', 'red']
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

