<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Customer Account Control </title>
  <link rel="stylesheet" href="../../css/view_orders.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="../../css/staff_account_history.css">
  
</head>

<body>
  <?php include('customer_navbar.php'); ?>
  <div class="container">
    <!-- Add your dashboard navigation if applicable -->

    <div class="livesearch">
      <input type="text" id="liveSearch" placeholder="Search for Transaction...">
      <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>
    </div>
   
    <h2 id="restrictionText">Restriction History</h2>
    <h2 id="unrestrictionText">UnRestriction History</h2>
    <h2 id="deactivationText">Deactivation History</h2>
    <h2 id="reactivationText">Reactivation History</h2>
  </div>
  <div class="container2">
    <!-- <h3 class="mb-4">Customer History</h3> -->

    <ul class="nav nav-tabs mb-3" id="historyTabs">
      <li class="nav-item">
        <button class="nav-link active" id="restriction-tab" data-bs-toggle="tab"
          data-bs-target="#restriction">Restriction History</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" id="unrestriction-tab" data-bs-toggle="tab" data-bs-target="#unrestriction">UnRestriction
          History</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" id="deactivation-tab" data-bs-toggle="tab"
          data-bs-target="#deactivation">Deactivation History</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" id="reactivation-tab" data-bs-toggle="tab" data-bs-target="#reactivation">Reactivation
          History</button>
      </li>
    </ul>

    <div class="tab-content">
      <div class="tab-pane fade show active" id="restriction">
        <div id="restriction-content"></div>
      </div>
      <div class="tab-pane fade" id="unrestriction">
        <div id="unrestriction-content"></div>
      </div>
      <div class="tab-pane fade" id="deactivation">
        <div id="deactivation-content"></div>
      </div>
      <div class="tab-pane fade" id="reactivation">
        <div id="reactivation-content"></div>
      </div>
    </div>
  </div>

  <script src="../scripts/account_history.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>