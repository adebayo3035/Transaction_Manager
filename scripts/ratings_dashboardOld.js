document.addEventListener('DOMContentLoaded', function() {
    // Global variables
    let currentPage = 1;
    let currentTimeframe = 'week';
    let currentFilters = {};
    
    // Chart instances
    let avgRatingChart;
    let ratingDistributionChart;
    
    // Initialize the dashboard
    initDashboard();
    
    // Event listeners
    document.getElementById('timeframe-week').addEventListener('click', () => updateTimeframe('week'));
    document.getElementById('timeframe-month').addEventListener('click', () => updateTimeframe('month'));
    document.getElementById('timeframe-year').addEventListener('click', () => updateTimeframe('year'));
    document.getElementById('applyFilters').addEventListener('click', applyFilters);
    
    // Initialize dashboard
    function initDashboard() {
        loadAnalytics();
        loadRatings();
    }
    
    // Load analytics data
    function loadAnalytics() {
        fetch(`backend/rating_api.php?action=get_analytics&timeframe=${currentTimeframe}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateCharts(data.data);
                    updateDriverLists(data.data.driver_performance);
                }
            })
            .catch(error => console.error('Error loading analytics:', error));
    }
    
    // Load ratings table data
    function loadRatings(page = 1) {
        currentPage = page;
        let url = `backend/rating_api.php?action=get_ratings&page=${page}`;
        
        // Add filters to URL
        if (currentFilters.driver_id) {
            url += `&driver_id=${currentFilters.driver_id}`;
        }
        if (currentFilters.date_from) {
            url += `&date_from=${currentFilters.date_from}`;
        }
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateRatingsTable(data.data);
                    updatePagination(data.pagination);
                }
            })
            .catch(error => console.error('Error loading ratings:', error));
    }
    
    // Update charts with new data
    function updateCharts(analyticsData) {
        // Average Rating Over Time Chart
        const avgCtx = document.getElementById('avgRatingChart').getContext('2d');
        const avgLabels = analyticsData.average_ratings.map(item => item.date);
        const avgData = analyticsData.average_ratings.map(item => item.avg_rating);
        
        if (avgRatingChart) {
            avgRatingChart.destroy();
        }
        
        avgRatingChart = new Chart(avgCtx, {
            type: 'line',
            data: {
                labels: avgLabels,
                datasets: [{
                    label: 'Average Rating',
                    data: avgData,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        min: 0,
                        max: 5,
                        ticks: {
                            stepSize: 0.5
                        }
                    }
                }
            }
        });
        
        // Rating Distribution Chart
        const distCtx = document.getElementById('ratingDistributionChart').getContext('2d');
        const distData = [0, 0, 0, 0, 0]; // Initialize for ratings 1-5
        
        analyticsData.rating_distribution.forEach(item => {
            distData[item.rating - 1] = item.count;
        });
        
        if (ratingDistributionChart) {
            ratingDistributionChart.destroy();
        }
        
        ratingDistributionChart = new Chart(distCtx, {
            type: 'bar',
            data: {
                labels: ['1 Star', '2 Stars', '3 Stars', '4 Stars', '5 Stars'],
                datasets: [{
                    label: 'Number of Ratings',
                    data: distData,
                    backgroundColor: [
                        'rgba(220, 53, 69, 0.7)',
                        'rgba(253, 126, 20, 0.7)',
                        'rgba(255, 193, 7, 0.7)',
                        'rgba(40, 167, 69, 0.7)',
                        'rgba(25, 135, 84, 0.7)'
                    ],
                    borderColor: [
                        'rgba(220, 53, 69, 1)',
                        'rgba(253, 126, 20, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(40, 167, 69, 1)',
                        'rgba(25, 135, 84, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    // Update driver performance lists
    function updateDriverLists(drivers) {
        const topDrivers = drivers.slice(0, 5);
        const bottomDrivers = [...drivers].reverse().slice(0, 5);
        
        // Update top drivers
        const topList = document.getElementById('topDriversList');
        topList.innerHTML = '';
        
        topDrivers.forEach(driver => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${driver.firstname} ${driver.lastname}</td>
                <td>${driver.avg_rating}</td>
                <td>${driver.rating_count || 0}</td>
            `;
            //? driver.avg_rating.toFixed(1) : 'N/A'
            topList.appendChild(row);
        });
        
        // Update bottom drivers
        const bottomList = document.getElementById('bottomDriversList');
        bottomList.innerHTML = '';
        
        bottomDrivers.forEach(driver => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${driver.firstname} ${driver.lastname}</td>
                <td>${driver.avg_rating ? driver.avg_rating.toFixed(1) : 'N/A'}</td>
                <td>${driver.rating_count || 0}</td>
            `;
            bottomList.appendChild(row);
        });
    }
    
    // Update ratings table
    function updateRatingsTable(ratings) {
        const tbody = document.getElementById('ratingsTableBody');
        tbody.innerHTML = '';
        
        ratings.forEach(rating => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${rating.order_id}</td>
                <td>${rating.driver_firstname} ${rating.driver_lastname}</td>
                <td><span class="rating-badge bg-rating-${rating.rating}">${rating.rating}</span></td>
                <td>${rating.comments ? rating.comments.substring(0, 50) + (rating.comments.length > 50 ? '...' : '') : ''}</td>
                <td>${new Date(rating.rating_date).toLocaleDateString()}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary view-detail" data-id="${rating.id}">View</button>
                </td>
            `;
            tbody.appendChild(row);
        });
        
        // Add event listeners to view buttons
        document.querySelectorAll('.view-detail').forEach(btn => {
            btn.addEventListener('click', function() {
                const ratingId = this.getAttribute('data-id');
                viewRatingDetails(ratingId);
            });
        });
    }
    
    // Update pagination
    function updatePagination(pagination) {
        const paginationEl = document.getElementById('pagination');
        paginationEl.innerHTML = '';
        
        const pagesToShow = 5;
        let startPage = Math.max(1, currentPage - Math.floor(pagesToShow / 2));
        let endPage = Math.min(pagination.pages, startPage + pagesToShow - 1);
        
        // Adjust if we're at the beginning or end
        if (endPage - startPage + 1 < pagesToShow) {
            if (currentPage < pagination.pages / 2) {
                endPage = Math.min(pagination.pages, startPage + pagesToShow - 1);
            } else {
                startPage = Math.max(1, endPage - pagesToShow + 1);
            }
        }
        
        // Previous button
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link" href="#" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a>`;
        prevLi.addEventListener('click', (e) => {
            e.preventDefault();
            if (currentPage > 1) loadRatings(currentPage - 1);
        });
        paginationEl.appendChild(prevLi);
        
        // Page numbers
        for (let i = startPage; i <= endPage; i++) {
            const pageLi = document.createElement('li');
            pageLi.className = `page-item ${i === currentPage ? 'active' : ''}`;
            pageLi.innerHTML = `<a class="page-link" href="#">${i}</a>`;
            pageLi.addEventListener('click', (e) => {
                e.preventDefault();
                loadRatings(i);
            });
            paginationEl.appendChild(pageLi);
        }
        
        // Next button
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${currentPage === pagination.pages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link" href="#" aria-label="Next"><span aria-hidden="true">&raquo;</span></a>`;
        nextLi.addEventListener('click', (e) => {
            e.preventDefault();
            if (currentPage < pagination.pages) loadRatings(currentPage + 1);
        });
        paginationEl.appendChild(nextLi);
    }
    
    // View rating details
    function viewRatingDetails(ratingId) {
        fetch(`rating_api.php?action=get_rating_details&id=${ratingId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modalContent = document.getElementById('ratingDetailContent');
                    const rating = data.data;
                    
                    modalContent.innerHTML = `
                        <div class="mb-3">
                            <h6>Order #${rating.order_id}</h6>
                            <p class="mb-1"><strong>Driver:</strong> ${rating.driver_firstname} ${rating.driver_lastname}</p>
                            <p class="mb-1"><strong>Customer ID:</strong> ${rating.customer_id}</p>
                        </div>
                        <div class="mb-3">
                            <p><strong>Rating:</strong> <span class="rating-badge bg-rating-${rating.rating}">${rating.rating}</span></p>
                            <p><strong>Date:</strong> ${new Date(rating.rating_date).toLocaleString()}</p>
                        </div>
                        <div class="mb-3">
                            <h6>Comments</h6>
                            <p class="p-2 bg-light rounded">${rating.comments || 'No comments provided'}</p>
                        </div>
                    `;
                    
                    const modal = new bootstrap.Modal(document.getElementById('ratingDetailModal'));
                    modal.show();
                }
            })
            .catch(error => console.error('Error loading rating details:', error));
    }
    
    // Update timeframe
    function updateTimeframe(timeframe) {
        currentTimeframe = timeframe;
        document.querySelectorAll('[id^="timeframe-"]').forEach(btn => {
            btn.classList.remove('active');
        });
        document.getElementById(`timeframe-${timeframe}`).classList.add('active');
        loadAnalytics();
    }
    
    // Apply filters
    function applyFilters() {
        currentFilters = {
            driver_id: document.getElementById('driverFilter').value,
            date_from: document.getElementById('dateFromFilter').value
        };
        loadRatings(1); // Reset to first page with new filters
    }
});