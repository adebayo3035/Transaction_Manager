<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Orders</title>
    <style>
         h1 {
            text-align: center;
            margin-top: 20px;
            color: #333;
        }

        table {
            width: 90%;
            margin: 20px auto;
            border-collapse: collapse;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #333;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            padding-top: 60px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgb(0, 0, 0);
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover, .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        .btn {
            padding: 10px 20px;
            margin: 5px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .btn-approve {
            background-color: #4CAF50;
            color: white;
        }

        .btn-approve:hover {
            background-color: #45a049;
        }

        .btn-decline {
            background-color: #f44336;
            color: white;
        }

        .btn-decline:hover {
            background-color: #da190b;
        }

        .view-details-btn {
            background-color: #008CBA;
            color: white;
        }

        .view-details-btn:hover {
            background-color: #007B9E;
        }

        @media (max-width: 600px) {
            table, th, td {
                width: 100%;
                display: block;
            }

            th, td {
                padding: 10px;
                text-align: right;
            }

            th::before {
                content: attr(data-title);
                float: left;
                font-weight: bold;
            }
        }
    </style>
    <link rel="stylesheet" href="customer/css/view_orders.css">
</head>
<body>
<?php include('navbar.php'); ?>
    <h1>Your Orders</h1>
    <table id="ordersTable">
        <thead>
            <tr>
                <th>Order Date</th>
                <th>Total Amount (N)</th>
                <th>Status</th>
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
            <div id="modalButtons">
                <button id="approveButton">Approve</button>
                <button id="declineButton">Decline</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Fetch and display orders summary
            fetch('backend/fetch_pending_order.php', {
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
                            <td>${order.order_date}</td>
                            <td>${order.total_amount}</td>
                            <td>${order.status}</td>
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
            fetch('backend/fetch_pending_order_details.php', {
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

                    // Store the current order ID for later use
                    document.getElementById('approveButton').dataset.orderId = orderId;
                    document.getElementById('declineButton').dataset.orderId = orderId;

                    // Display the modal
                    document.getElementById('orderModal').style.display = 'block';
                } else {
                    console.error('Failed to fetch order details:', data.message);
                    alert('Failed to fetch Order Details' , data.message)
                }
            })
            .catch(error => {
                console.error('Error fetching order details:', error);
            });
        }

        // Approve order
        document.getElementById('approveButton').addEventListener('click', () => {
            const orderId = document.getElementById('approveButton').dataset.orderId;
            updateOrderStatus(orderId, 'Approved');
        });

        // Decline order
        document.getElementById('declineButton').addEventListener('click', () => {
            const orderId = document.getElementById('declineButton').dataset.orderId;
            updateOrderStatus(orderId, 'Declined');
        });

        function updateOrderStatus(orderId, status) {
            fetch('backend/update_order_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ order_id: orderId, status: status })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if(status = "Declined"){
                        alert('Order status updated to ' + status + " and Customer has been refunded");
                    }
                    else{
                        alert("Order Status updated to " + status);
                    }
                   
                    location.reload(); // Refresh the page to reflect changes
                } else {
                    console.error('Failed to update order status:', data.message);
                }
            })
            .catch(error => {
                console.error('Error updating order status:', error);
            });
        }

        // Close the modal when the close button is clicked
        document.querySelector('.modal .close').addEventListener('click', () => {
            document.getElementById('orderModal').style.display = 'none';
        });

        // Close the modal when clicking outside of the modal content
        window.addEventListener('click', (event) => {
            if (event.target === document.getElementById('orderModal')) {
                document.getElementById('orderModal').style.display = 'none';
            }
        });
    </script>
</body>
</html>
