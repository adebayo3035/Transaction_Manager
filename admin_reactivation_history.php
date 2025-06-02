<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Customer History</title>
   <link rel="stylesheet" href="css/view_orders.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    body {
      background-color: #f8f9fa;
      padding: 2rem;
    }
    .history-table th, .history-table td {
      vertical-align: middle;
    }
    .spinner-border {
      display: block;
      margin: 2rem auto;
    }
  </style>
</head>
<body>
   <?php include('navbar.php'); ?>
    <div class="container">
        <!-- Add your dashboard navigation if applicable -->
        <?php include('dashboard_navbar.php'); ?>
        <div class="livesearch">
            <input type="text" id="liveSearch" placeholder="Search for Transaction...">
            <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>
        </div>
        <h1>All Transactions</h1>
    </div>
  <div class="container2">
    <h3 class="mb-4">Customer History</h3>

    <ul class="nav nav-tabs mb-3" id="historyTabs">
      <li class="nav-item">
        <button class="nav-link active" id="deactivation-tab" data-bs-toggle="tab" data-bs-target="#deactivation">Deactivation History</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" id="reactivation-tab" data-bs-toggle="tab" data-bs-target="#reactivation">Reactivation History</button>
      </li>
    </ul>

    <div class="tab-content">
      <div class="tab-pane fade show active" id="deactivation">
        <div id="deactivation-content"></div>
      </div>
      <div class="tab-pane fade" id="reactivation">
        <div id="reactivation-content"></div>
      </div>
    </div>
  </div>

  <script>
    const endpoints = {
      deactivation: 'backend/admin_deactivation_history.php',
      reactivation: 'backend/admin_reactivation_history.php'
    };

    async function fetchData(endpoint, page = 1, limit = 10) {
      const url = `${endpoint}?page=${page}&limit=${limit}`;
      const res = await fetch(url);
      if (!res.ok) throw new Error(`Failed to fetch ${endpoint}`);
      return res.json();
    }

    function renderDeactivation(data) {
      let html = `
        <table class="table table-bordered history-table">
          <thead class="table-light">
            <tr>
              <th>Date</th>
              <th>Reason</th>
              <th>Status</th>
              <th>Deactivated By</th>
            </tr>
          </thead>
          <tbody>
            ${data.data.map(row => `
              <tr>
                <td>${row.date}</td>
                <td>${row.reason}</td>
                 <td>${row.status}</td>
                <td>${row.deactivated_by.name}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      `;
      html += renderPagination(data.pagination, 'deactivation');
      document.getElementById('deactivation-content').innerHTML = html;
    }

    function renderReactivation(data) {
      let html = `
        <table class="table table-bordered history-table">
          <thead class="table-light">
            <tr>
              <th>Date</th>
              <th>Status</th>
              <th>Reason</th>
              <th>Deactivation Reason</th>
              <th>Processed By</th>
            </tr>
          </thead>
          <tbody>
            ${data.data.map(row => `
              <tr>
                <td>${row.date}</td>
                <td>${row.status}</td>
                <td>${row.reason}</td>
                <td>${row.deactivation_details.reason || '-'}</td>
                <td>${row.processed_by?.name || 'Pending'}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      `;
      html += renderPagination(data.pagination, 'reactivation');
      document.getElementById('reactivation-content').innerHTML = html;
    }

    function renderPagination({ total, page, limit }, type) {
      const totalPages = Math.ceil(total / limit);
      if (totalPages <= 1) return '';

      let html = `<nav><ul class="pagination justify-content-end">`;
      for (let i = 1; i <= totalPages; i++) {
        html += `<li class="page-item ${i === page ? 'active' : ''}">
          <button class="page-link" onclick="loadHistory('${type}', ${i})">${i}</button>
        </li>`;
      }
      html += `</ul></nav>`;
      return html;
    }

    async function loadHistory(type, page = 1) {
      const container = document.getElementById(`${type}-content`);
      container.innerHTML = `<div class="spinner-border" role="status"></div>`;
      try {
        const data = await fetchData(endpoints[type], page);
        type === 'deactivation' ? renderDeactivation(data) : renderReactivation(data);
      } catch (err) {
        container.innerHTML = `<div class="alert alert-danger">${err.message}</div>`;
      }
    }

    // Initial load
    loadHistory('deactivation');
    document.getElementById('deactivation-tab').addEventListener('click', () => loadHistory('deactivation'));
    document.getElementById('reactivation-tab').addEventListener('click', () => loadHistory('reactivation'));
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
