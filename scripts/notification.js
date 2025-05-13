document.addEventListener('DOMContentLoaded', () => {
    const notificationsContainer = document.getElementById('notifications-container');
    const refreshButton = document.getElementById('refresh-notifications');
    const prevPageButton = document.getElementById('prev-page');
    const nextPageButton = document.getElementById('next-page');
    const currentPageDisplay = document.getElementById('current-page');
    const markAllButton = document.getElementById('mark-all')


    let currentPage = 1;
    const limit = 5; // Number of notifications per page
    
    function fetchNotificationsAndCount(page = 1) {
        fetch(`backend/notification.php?page=${page}&limit=${limit}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear existing notifications
                    notificationsContainer.innerHTML = '';
    
                    // Handle cases where totalPages is 0 or invalid
                    const totalPages = Math.max(data.totalPages || 1, 1); // Ensure at least 1 page
                    const currentPage = Math.min(Math.max(data.currentPage || 1, 1), totalPages); // Clamp current page
    
                    // Update the current page display
                    currentPageDisplay.textContent = `Page ${currentPage} of ${totalPages}`;
    
                    // Populate notifications
                    if (Array.isArray(data.notifications)) {
                        if (data.notifications.length > 0) {
                            data.notifications.forEach(notification => {
                                const notificationElement = document.createElement('div');
                                notificationElement.className = 'notification';
                                notificationElement.style.cursor = 'pointer';
    
                                notificationElement.innerHTML = `
                                    <div class="event-title">${notification.event_title}</div>
                                    <div class="event-details">${notification.event_details}</div>
                                `;
    
                                if (data.role === 'Admin') {
                                    const markAsReadButton = document.createElement('button');
                                    markAsReadButton.className = 'mark-as-read-btn';
                                    markAsReadButton.textContent = 'Mark as Read';
                                    markAsReadButton.dataset.id = notification.id;
                                    markAsReadButton.addEventListener('click', () => markNotificationAsRead(notification.id));
                                    notificationElement.appendChild(markAsReadButton);
                                }
    
                                notificationsContainer.appendChild(notificationElement);
                            });
                        } else {
                            notificationsContainer.innerHTML = '<p>No notifications available.</p>';
                        }
                    }
    
                    // Handle pagination controls
                    prevPageButton.disabled = (currentPage === 1);
                    nextPageButton.disabled = (currentPage >= totalPages || totalPages <= 1);
                    
                } else {
                    notificationsContainer.innerHTML = '<p>Error fetching notifications. Please try again later.</p>';
                    // Disable both buttons on error
                    prevPageButton.disabled = true;
                    nextPageButton.disabled = true;
                }
            })
            .catch(error => {
                console.error('Error fetching notifications:', error);
                notificationsContainer.innerHTML = '<p>Error fetching notifications. Please try again later.</p>';
                // Disable both buttons on error
                prevPageButton.disabled = true;
                nextPageButton.disabled = true;
            });
    }
    
    // Initial fetch
    fetchNotificationsAndCount(currentPage);
    // Event listener for refresh button
    refreshButton.addEventListener('click', () => fetchNotificationsAndCount(currentPage));
     // Event listener for mark all notifications as read
     markAllButton.addEventListener('click', () => markAllNotificationAsRead());

    // Event listeners for pagination buttons
    prevPageButton.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            fetchNotificationsAndCount(currentPage);
        }
    });

    nextPageButton.addEventListener('click', () => {
        currentPage++;
        fetchNotificationsAndCount(currentPage);
    });

    // refresh the notifications and count periodically
    const oneHour = 60 * 60 * 1000;
    setInterval(() => fetchNotificationsAndCount(currentPage), oneHour); // refresh page automatically every one hour

    // Function to mark notification as read
    function markNotificationAsRead(notificationId) {
        if(!confirm("Continue to mark notifications as read?")){
            return;
        }
        fetch('backend/update_notification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notification_id: notificationId })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Refresh the notifications list
                    alert(data.message);
                    fetchNotificationsAndCount();
                    location.reload();
                } else {
                    console.error('Error marking notification as read:', data.message);
                }
            })
            .catch(error => {
                console.error('Error marking notification as read:', error);
            });
    }

    // Function to mark all notifications as read with confirmation
    function markAllNotificationAsRead() {
        // Display a confirmation modal
        if (confirm("Are you sure you want to mark all notifications as read?")) {
            // If the user clicks "Yes", proceed with the fetch request
            fetch('backend/update_all_notification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Refresh the notifications list
                        alert(data.message);
                        fetchNotificationsAndCount();
                        location.reload();
                    } else {
                        console.error('Error marking notification as read:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error marking notification as read:', error);
                });
        } else {
            // If the user clicks "No", do nothing
            console.log("Action cancelled by user.");
        }
    }

});
