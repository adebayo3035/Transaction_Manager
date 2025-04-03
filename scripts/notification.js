document.addEventListener('DOMContentLoaded', () => {
    const notificationsContainer = document.getElementById('notifications-container');
    const refreshButton = document.getElementById('refresh-notifications');
    const prevPageButton = document.getElementById('prev-page');
    const nextPageButton = document.getElementById('next-page');
    const currentPageDisplay = document.getElementById('current-page');
    const markAllButton = document.getElementById('mark-all')


    let currentPage = 1;
    const limit = 5; // Number of notifications per page

    // Function to fetch and display notifications and count with pagination
    function fetchNotificationsAndCount(page = 1) {
        fetch(`backend/notification.php?page=${page}&limit=${limit}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear existing notifications
                    notificationsContainer.innerHTML = '';

                    // Update the current page display
                    currentPageDisplay.textContent = `Page ${data.currentPage} of ${data.totalPages} `;

                    // Populate notifications
                    if (Array.isArray(data.notifications) && data.notifications.length > 0) {
                        data.notifications.forEach(notification => {
                            const notificationElement = document.createElement('div');
                            notificationElement.className = 'notification';
                            notificationElement.style.cursor = 'pointer';

                            // Create notification content
                            notificationElement.innerHTML = `
                                <div class="event-title">${notification.event_title}</div>
                                <div class="event-details">${notification.event_details}</div>
                            `;

                            // Add "Mark as Read" button only for Admin users
                            if (data.role === 'Admin') {
                                const markAsReadButton = document.createElement('button');
                                
                                markAsReadButton.className = 'mark-as-read-btn';
                                markAsReadButton.textContent = 'Mark as Read';
                                markAsReadButton.dataset.id = notification.id;

                                // Event listener for marking as read
                                markAsReadButton.addEventListener('click', () => markNotificationAsRead(notification.id));
                                // Append the button to the notification
                                notificationElement.appendChild(markAsReadButton);
                            }

                            // Append the notification to the container
                            notificationsContainer.appendChild(notificationElement);
                        });

                        // Handle pagination controls
                        prevPageButton.disabled = (data.currentPage === 1);
                        nextPageButton.disabled = (data.currentPage >= data.totalPages);
                    } else {
                        notificationsContainer.innerHTML = '<p>No notifications available.</p>';
                    }
                } else {
                    notificationsContainer.innerHTML = '<p>Error fetching notifications. Please try again later.</p>';
                }
            })
            .catch(error => {
                console.error('Error fetching notifications:', error);
                notificationsContainer.innerHTML = '<p>Error fetching notifications. Please try again later.</p>';
            });
    }

    // Initial fetch of notifications and count
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

    // Optionally, refresh the notifications and count periodically
    setInterval(() => fetchNotificationsAndCount(currentPage), 60000); // Refresh every minute

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
