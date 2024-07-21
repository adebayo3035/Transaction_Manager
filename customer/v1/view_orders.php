<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Orders</title>
    <link rel="stylesheet" href="../css/view_orders.css">
</head>

<body>
    <?php include ('../customerNavBar.php'); ?>
    <div class="container">
        <div class="livesearch">

            <input type="text" id="liveSearch" placeholder="Search for Order...">
            <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>

        </div>
        <h1>Your Orders</h1>
    </div>
    <!-- Live search input -->

    <table id="ordersTable">
        <thead>
            <tr>
                <!-- <th>Order ID</th> -->
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

        </div>
    </div>

    <script>
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


         // function to filter table row based on Search Query
    function filterTable() {
      // Get input value and convert to lowercase
      var input = document.getElementById("liveSearch").value.toLowerCase();
      // Get table rows
      var rows = document.getElementById("ordersTable").getElementsByTagName("tr");

      // Loop through table rows
      for (var i = 1; i < rows.length; i++) {
        // Get cells in current row
        var cells = rows[i].getElementsByTagName("td");
        var found = false;
        // Loop through cells
        for (var j = 0; j < cells.length; j++) {
          // Check if cell text matches search query
          if (cells[j]) {
            var cellText = cells[j].textContent.toLowerCase();
            if (cellText.indexOf(input) > -1) {
              found = true;
              break;
            }
          }
        }
        // Show or hide row based on search result
        if (found) {
          rows[i].style.display = "";
        } else {
          rows[i].style.display = "none";
        }
      }
    }

    // Add event listener to input field
    document.getElementById("liveSearch").addEventListener("input", filterTable);
    </script>
</body>

</html>