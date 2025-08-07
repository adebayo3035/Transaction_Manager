<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rating History</title>
  <link rel="stylesheet" href="customer/css/view_orders.css">
  <link rel="stylesheet" href="customer/css/checkout.css">
  <link rel="stylesheet" href="customer/css/cards.css">
  <link rel="stylesheet" href="css/view_driver.css">
  <link rel="stylesheet" href="css/ratings.css"> 
</head>

<body>
  <?php include('navbar.php'); ?>
  <div class="container">
    <h1>Order Ratings</h1>

    <!-- Separate row for "Add New Customer" button -->
    <div id="customer-form">
      <button onclick="openRatingDashboard()"><i class="fa fa-eye" aria-hidden="true"></i> Rating
        Dashboard</button>
    </div>

    <div class="livesearch">
      <input type="text" id="liveSearch" placeholder="Search for Order...">
      <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>
    </div>


  </div>

  <table id="ordersTable" class="ordersTable">
    <thead>
      <tr>

        <th>Rating ID</th>
        <th>Order ID</th>
        <th>Customer ID</th>
        <th>Driver ID</th>
        <th>Food Rating</th>
        <th>Package Rating</th>
        <th>Driver Rating</th>
        <th>Delivery Rating</th>
        <th>Rating Date</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody id="ordersTableBody">
      <!-- Promo Information will be dynamically inserted here -->
    </tbody>

  </table>
  <div id="pagination" class="pagination"></div>


  <div id="orderModal" class="modal">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h2 class = "rating-header">Rating Details for Rating ID #: <span id="ratingID"> </span></h2>
      <table id="orderDetailsTable" class="ordersTable ratingTable">
        <tbody>
          <!-- Promo details will be automatically populated here -->
        </tbody>
      </table>
    </div>
  </div>

  <script src="scripts/ratings.js"></script>
</body>

</html>