document.addEventListener('DOMContentLoaded', () => {
    const notificationsContainer = document.getElementById('notifications-container');
    const refreshButton = document.getElementById('refresh-notifications');
    const notificationBadge = document.getElementById('notification-badge');

    // Function to fetch and display notifications and count
    function fetchNotificationsAndCount() {
        fetch('backend/notification.php') // Replace with your PHP endpoint
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Handle notifications
                    notificationsContainer.innerHTML = ''; // Clear existing notifications
                    if (Array.isArray(data.notifications) && data.notifications.length > 0) {
                        data.notifications.forEach(notification => {
                            const notificationElement = document.createElement('div');
                            notificationElement.className = 'notification';
                            notificationElement.style.cursor = "pointer";
                            notificationElement.innerHTML = `
                                <div class="event-title">${notification.event_title}</div>
                                <div class="event-details">${notification.event_details}</div>
                            `;
                            notificationElement.addEventListener('click', () => {
                                if (notification.event_type === 'New Food Order') {
                                    location.href = 'pending_orders.php';
                                }
                            });
                            notificationsContainer.appendChild(notificationElement);
                        });
                    } else {
                        notificationsContainer.innerHTML = '<p>No notifications available.</p>';
                    }

                    // Handle notification count
                    notificationBadge.textContent = data.totalNotifications || '0'; // Default to 0 if undefined
                } else {
                    notificationsContainer.innerHTML = '<p>Error fetching notifications. Please try again later.</p>';
                    notificationBadge.textContent = '0'; // Default to 0 on error
                }
            })
            .catch(error => {
                console.error('Error fetching notifications:', error);
                notificationsContainer.innerHTML = '<p>Error fetching notifications. Please try again later.</p>';
                notificationBadge.textContent = '0'; // Default to 0 on error
            });
    }

    // Initial fetch of notifications and count
    fetchNotificationsAndCount();

    // Set up refresh button event listener
    refreshButton.addEventListener('click', fetchNotificationsAndCount);

    // Optionally, set an interval to refresh the notifications and count periodically
    setInterval(fetchNotificationsAndCount, 60000); // Refresh every minute
});
