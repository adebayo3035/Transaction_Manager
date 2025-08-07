document.addEventListener('DOMContentLoaded', () => {
  const limit = 10;
  let currentPage = 1;

  const ratingsTableBody = document.querySelector('#ordersTable tbody');
  const ratingDetailsTableBody = document.querySelector('#orderDetailsTable tbody');
  const paginationContainer = document.getElementById('pagination');
  const liveSearchInput = document.getElementById("liveSearch");
  const ratingModal = document.getElementById('orderModal');
  // const ratingDetailsTableBodyHeader = document.querySelector('#orderDetailsTable thead tr');
  // ratingDetailsTableBodyHeader.style.color = "#000";

  

  // Fetch ratings with pagination
  function fetchRatings(page = 1) {
    const ratingsTableBody = document.getElementById('ordersTableBody');

    // Inject spinner
    ratingsTableBody.innerHTML = `
        <tr>
            <td colspan="10" style="text-align:center; padding: 20px;">
                <div class="spinner"
                    style="border: 4px solid #f3f3f3; border-top: 4px solid #34108db; border-radius: 100%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: auto;">
                </div>
            </td>
        </tr>
        `;

    const minDelay = new Promise(resolve => setTimeout(resolve, 1000));
    const fetchData = fetch(`backend/fetch_rating_summary.php?page=${page}&limit=${limit}`)
      .then(res => res.json());

    Promise.all([fetchData, minDelay])
      .then(([data]) => {
        if (data.success && data.ratings.length > 0) {
          updateRatingsTable(data.ratings);
          updatePagination(data.pagination.total, data.pagination.page, data.pagination.limit);
        } else {
          ratingsTableBody.innerHTML = `
                    <tr><td colspan="10" style="text-align:center;">No ratings found</td></tr>
                `;
        }
      })
      .catch(error => {
        console.error('Error fetching ratings:', error);
        ratingsTableBody.innerHTML = `
                <tr><td colspan="10" style="text-align:center; color:red;">Error loading ratings</td></tr>
            `;
      });
  }

  // Update ratings table
  function updateRatingsTable(ratings) {
    ratingsTableBody.innerHTML = '';
    const fragment = document.createDocumentFragment();

    ratings.forEach(rating => {
      const row = document.createElement('tr');
      row.innerHTML = `
                <td>${rating.rating_id}</td>
                <td>${rating.order_id}</td>
                <td>${rating.customer_id}</td>
                <td>${rating.driver_id}</td>
                <td>${rating.food_rating}</td>
                <td>${rating.packaging_rating}</td>
                <td>${rating.driver_rating}</td>
                <td>${rating.delivery_time_rating}</td>
                <td>${rating.rated_at}</td>
                <td><button class="view-details-btn" data-rating-id="${rating.rating_id}">View Details</button></td>
            `;
      fragment.appendChild(row);
    });

    ratingsTableBody.appendChild(fragment);
  }

  // Delegate event listener for view details buttons
  ratingsTableBody.addEventListener('click', (event) => {
    if (event.target.classList.contains('view-details-btn')) {
      const ratingId = event.target.getAttribute('data-rating-id');
      fetchRatingDetails(ratingId);
    }
  });

  // Fetch rating details
  function fetchRatingDetails(ratingId) {
    fetch('backend/fetch_rating_details.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ rating_id: ratingId })
    })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          document.getElementById('ratingID').textContent = ratingId;
          populateRatingDetails(data.rating_details);
          ratingModal.style.display = 'block';
        } else {
          console.error('Failed to fetch rating details:', data.message);
        }
      })
      .catch(error => console.error('Error fetching rating details:', error));
  }

  // Populate rating details table
  function populateRatingDetails(details) {
    const detail = details[0]; // Get the rating details
    ratingDetailsTableBody.innerHTML = '';

    // Create a container for the two-column layout
    const container = document.createElement('div');
    container.className = 'rating-details-container';

    // 1. LEFT COLUMN - Structured Ratings Table
    const ratingsTable = document.createElement('table');
    ratingsTable.className = 'ratings-data-table';

    // Table Header
    ratingsTable.innerHTML = `
        <thead>
            <tr>
                <th colspan="2" class="section-header">Rating Details</th>
            </tr>
        </thead>
        <tbody>
            ${createTableRow('Rating ID', detail.rating_id)}
            ${createTableRow('Order ID', detail.order_id)}
            ${createTableRow('Food Rating', createStarRating(detail.food_rating))}
            ${createTableRow('Packaging', createStarRating(detail.packaging_rating))}
            ${createTableRow('Driver Service', createStarRating(detail.driver_rating))}
            ${createTableRow('Delivery Time', createStarRating(detail.delivery_time_rating))}
            ${createTableRow('Date Rated', formatDate(detail.rated_at))}
        </tbody>
    `;

    // 2. RIGHT COLUMN - Visual User Details Fragment
    const userDetailsFragment = document.createElement('div');
    userDetailsFragment.className = 'user-details-fragment';

    // Customer Card
    userDetailsFragment.innerHTML += `
        <div class="user-card customer-card">
            <h3>Customer Details</h3>
            <div class="user-avatar">
                <img src="backend/customer_photos/${detail.customer_photo || 'default-user.jpg'}" alt="Customer Photo">
            </div>
            <div class="user-info">
                <p><strong>Name:</strong> ${detail.customer_name}</p>
                <p><strong>ID:</strong> ${detail.customer_id}</p>
                <p><strong>Contact:</strong> ${detail.customer_phone || 'N/A'}</p>
            </div>
        </div>
    `;

    // Driver Card (if available)
    if (detail.driver_id) {
      userDetailsFragment.innerHTML += `
            <div class="user-card driver-card">
                <h3>Driver Details</h3>
                <div class="user-avatar">
                    <img src="backend/driver_photos/${detail.driver_photo || 'default-driver.jpg'}" alt="Driver Photo">
                </div>
                <div class="user-info">
                    <p><strong>Name:</strong> ${detail.driver_name || 'N/A'}</p>
                    <p><strong>ID:</strong> ${detail.driver_id}</p>
                    <p><strong>Vehicle:</strong> ${detail.driver_vehicle || 'N/A'}</p>
                </div>
            </div>
        `;
    }

    // Comments Section
    userDetailsFragment.innerHTML += `
        <div class="comments-section">
            <h3>Feedback</h3>
            <div class="comment-box">
                <h4>Driver Comment:</h4>
                <p>${detail.driver_comment || 'No comments provided'}</p>
            </div>
            <div class="comment-box">
                <h4>Order Comment:</h4>
                <p>${detail.order_comment || 'No comments provided'}</p>
            </div>
        </div>
    `;

    // Append both sections to container
    container.appendChild(ratingsTable);
    container.appendChild(userDetailsFragment);

    // Clear and rebuild the table body
    ratingDetailsTableBody.innerHTML = '';
    ratingDetailsTableBody.appendChild(container);
  }

  // Helper function to create star ratings
  function createStarRating(rating) {
    const fullStars = '★'.repeat(Math.floor(rating));
    const halfStar = rating % 1 >= 0.5 ? '½' : '';
    const emptyStars = '☆'.repeat(5 - Math.ceil(rating));
    return `<span class="star-rating">${fullStars}${halfStar}${emptyStars} ${rating.toFixed(1)}</span>`;
  }

  // Helper function to format dates
  function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const options = {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    };
    return new Date(dateString).toLocaleString('en-US', options);
  }

  // Helper function to create table rows
  function createTableRow(label, value) {
    return `
        <tr>
            <td class="detail-label">${label}</td>
            <td class="detail-value">${value}</td>
        </tr>
    `;
  }

  // Update pagination (same as before)
  function updatePagination(totalItems, currentPage, itemsPerPage) {
    paginationContainer.innerHTML = '';
    const totalPages = Math.ceil(totalItems / itemsPerPage);

    const createButton = (label, page, disabled = false) => {
      const btn = document.createElement('button');
      btn.textContent = label;
      if (disabled) btn.disabled = true;
      btn.addEventListener('click', () => fetchRatings(page));
      paginationContainer.appendChild(btn);
    };

    // First and Previous buttons
    createButton('« First', 1, currentPage === 1);
    createButton('‹ Prev', currentPage - 1, currentPage === 1);

    // Page numbers
    const maxVisible = 2;
    const start = Math.max(1, currentPage - maxVisible);
    const end = Math.min(totalPages, currentPage + maxVisible);

    for (let i = start; i <= end; i++) {
      const btn = document.createElement('button');
      btn.textContent = i;
      if (i === currentPage) btn.classList.add('active');
      btn.addEventListener('click', () => fetchRatings(i));
      paginationContainer.appendChild(btn);
    }

    // Next and Last buttons
    createButton('Next ›', currentPage + 1, currentPage === totalPages);
    createButton('Last »', totalPages, currentPage === totalPages);
  }

  // Search functionality
  let searchTimeout;
  liveSearchInput.addEventListener('input', () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(filterTable, 300);
  });

  function filterTable() {
    const input = liveSearchInput.value.toLowerCase();
    const rows = ratingsTableBody.getElementsByTagName("tr");

    Array.from(rows).forEach(row => {
      const cells = row.getElementsByTagName("td");
      const found = Array.from(cells).some(cell => cell.textContent.toLowerCase().includes(input));
      row.style.display = found ? "" : "none";
    });
  }

  // Close modal
  document.querySelector('.modal .close').addEventListener('click', () => {
    ratingModal.style.display = 'none';
  });
  window.addEventListener('click', (event) => {
    if (event.target === ratingModal) {
      ratingModal.style.display = 'none';
    }
  });

  // Initial fetch
  fetchRatings(currentPage);
});
const openRatingDashboard = () =>{
    window.location.href = "ratings_dashboard.php";
}