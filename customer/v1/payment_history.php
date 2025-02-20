<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Spending Habits Dashboard</title>
  <link rel="stylesheet" href="../css/payment_history.css">
  
</head>
<body>
<?php include ('customer_navbar.php'); ?>
  <div class="dashboard-container">
    <h1 class="header-text">Payment Management Dashboard</h1>

    <!-- Filter Section -->
    <div class="filters">
      <label for="start-date">Start Date:</label>
      <input type="date" id="start-date">

      <label for="end-date">End Date:</label>
      <input type="date" id="end-date">

      <label for="transaction-type">Transaction Type:</label>
      <select id="transaction-type">
        <option value="">--Select Transaction Type --</option>
        <option value="all">All</option>
        <option value="credit">Credit</option>
        <option value="debit">Debit</option>
      </select>

      <label for="payment-method">Payment Method:</label>
      <select id="payment-method">
        <option value="">--Select Payment Method --</option>
        <option value="all">All</option>
        <option value="Card">Card Payment</option>
        <option value="Direct Debit">Direct Debit Payment</option>
        <option value="Wallet Funding">Wallet Funding</option>
        <option value="Order Cancellation Fee">Order Cancellation Fee</option>
        <option value="Transaction Refund">Transaction Refund</option>
        <option value="Wallet">Wallet Payment</option>
        <option value="Not Applicable">Other Transactions</option>
      </select>

      <label for="description">Description</label>
      <input type="text" id="transaction_description">

      <button id="apply-filters">Apply Filters</button>
      
    </div>
    <div class="statementContainer">
        <div class="download-format">
            <p>Select Statement Format</p>
            <input type="radio" name="format" value="csv" id="csv">
            <label for="csv">Excel</label>
            <input type="radio" name="format" value="pdf" id="pdf">
            <label for="pdf">Pdf</label>
        </div>
        <button id="download-statement">Download Statement</button>
    </div>

    <!-- Transaction Table -->
    <div class="transaction-table">
      <table id="transactions">
        <thead>
          <tr>
                <th>Trans ID</th>
                <th>Transaction Reference</th>
                <th>Customer ID</th>
                <th>Transaction Date</th>
                <th>Transaction Type</th>
                <th>Payment Method</th>
                <th>Total Amount (N)</th>
                <th>Description</th>
          </tr>
        </thead>
        <tbody>
          <!-- Transactions will be populated here dynamically -->
        </tbody>
      </table>
      <div id="pagination" class="pagination"></div>
    </div>
  </div>

  <script src="../scripts/payment_history.js"></script>
</body>
</html>