<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Orders</title>
    <link rel="stylesheet" href="../css/view_orders.css">
</head>
<body>
<?php include('../customerNavBar.php'); ?>
    <h1>Your Orders</h1>
    <table id="ordersTable">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Order Date</th>
                <th>Total Amount (N)</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <!-- Orders will be dynamically inserted here -->
        </tbody>
    </table>

    <!-- The Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Order Details</h2>
            <table id="orderDetailsTable">
                <thead>
                    <tr>
                        <th>Food Name</th>
                        <th>Number of Portions</th>
                        <th>Price per Portion (N)</th>
                        <th>Total Price (N)</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Order details will be dynamically inserted here -->
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // document.addEventListener('DOMContentLoaded', function() {
        //     fetch('../v2/fetch_order_summary.php')
        //         .then(response => response.json())
        //         .then(data => {
        //             if (data.success) {
        //                 const ordersTable = document.getElementById('ordersTable').getElementsByTagName('tbody')[0];
        //                 data.orders.forEach(order => {
        //                     const row = ordersTable.insertRow();
        //                     row.insertCell(0).innerText = order.order_id;
        //                     row.insertCell(1).innerText = order.order_date;
        //                     row.insertCell(2).innerText = order.total_amount;
        //                     const actionCell = row.insertCell(3);
        //                     const viewButton = document.createElement('button');
        //                     viewButton.innerText = 'View Details';
        //                     viewButton.addEventListener('click', () => showOrderDetails(order.order_id));
        //                     actionCell.appendChild(viewButton);
        //                 });
        //             } else {
        //                 alert('Error fetching orders: ' + data.message);
        //             }
        //         })
        //         .catch(error => {
        //             console.error('Error:', error);
        //         });

        //     const modal = document.getElementById("orderModal");
        //     const span = document.getElementsByClassName("close")[0];

        //     function showOrderDetails(orderId) {
        //         fetch('../v2/fetch_order_details.php', {
        //             method: 'POST',
        //             headers: {
        //                 'Content-Type': 'application/json'
        //             },
        //             body: JSON.stringify({ order_id: orderId })
        //         })
        //         .then(response => response.json())
        //         .then(data => {
        //             if (data.success) {
        //                 const orderDetailsTable = document.getElementById('orderDetailsTable').getElementsByTagName('tbody')[0];
        //                 orderDetailsTable.innerHTML = '';
        //                 data.order_details.forEach(detail => {
        //                     const row = orderDetailsTable.insertRow();
        //                     row.insertCell(0).innerText = detail.food_name;
        //                     row.insertCell(1).innerText = detail.quantity;
        //                     row.insertCell(2).innerText = detail.price_per_unit.toFixed(2);
        //                     row.insertCell(3).innerText = detail.total_price.toFixed(2);
        //                 });
        //                 modal.style.display = "block";
        //             } else {
        //                 alert('Error fetching order details: ' + data.message);
        //             }
        //         })
        //         .catch(error => {
        //             console.error('Error:', error);
        //         });
        //     }

        //     span.onclick = function() {
        //         modal.style.display = "none";
        //     }

        //     window.onclick = function(event) {
        //         if (event.target == modal) {
        //             modal.style.display = "none";
        //         }
        //     }
        // });

        document.addEventListener('DOMContentLoaded', () => {
    // Fetch and display orders summary
    fetch('../V2/fetch_order_summary.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const ordersTableBody = document.querySelector('#ordersTable tbody');
            ordersTableBody.innerHTML = '';
            data.orders.forEach(order => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${order.order_id}</td>
                    <td>${order.order_date}</td>
                    <td>${order.total_amount}</td>
                    <td><button class="view-details-btn" data-order-id="${order.order_id}">View Details</button></td>
                `;
                ordersTableBody.appendChild(row);
            });

            // Attach event listeners to the view details buttons
            document.querySelectorAll('.view-details-btn').forEach(button => {
                button.addEventListener('click', (event) => {
                    const orderId = event.target.getAttribute('data-order-id');
                    fetchOrderDetails(orderId);
                });
            });
        } else {
            console.error('Failed to fetch orders:', data.message);
        }
    })
    .catch(error => {
        console.error('Error fetching orders:', error);
    });
});

function fetchOrderDetails(orderId) {
    fetch('../v2/fetch_order_details.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ order_id: orderId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const orderDetailsTableBody = document.querySelector('#orderDetailsTable tbody');
            orderDetailsTableBody.innerHTML = '';
            data.order_details.forEach(detail => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${detail.food_name}</td>
                    
                    <td>${detail.quantity}</td>
                    <td>${detail.price_per_unit}</td>
                    <td>${detail.total_price}</td>
                `;
                orderDetailsTableBody.appendChild(row);
            });
            // Display the modal
            document.getElementById('orderModal').style.display = 'block';
        } else {
            console.error('Failed to fetch order details:', data.message);
        }
    })
    .catch(error => {
        console.error('Error fetching order details:', error);
    });
}

// Close the modal when the close button is clicked
document.querySelector('.modal .close').addEventListener('click', () => {
    document.getElementById('orderModal').style.display = 'none';
});

// Close the modal when clicking outside of the modal content
window.addEventListener('click', (event) => {
    if (event.target === document.getElementById('orderDetailsModal')) {
        document.getElementById('orderDetailsModal').style.display = 'none';
    }
});

    </script>
</body>
</html>
