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
                    // Clear existing notifications
                    notificationsContainer.innerHTML = '';

                    if (Array.isArray(data.notifications) && data.notifications.length > 0) {
                        data.notifications.forEach(notification => {
                            const notificationElement = document.createElement('div');
                            notificationElement.className = 'notification';
                            notificationElement.style.cursor = 'pointer';

                            // Create notification content
                            notificationElement.innerHTML = `
                                <div class="event-title">${notification.event_title}</div>
                                <div class="event-details">${notification.event_details}</div>
                                <button class="mark-as-read-btn" data-id="${notification.id}">Mark as Read</button>
                            `;

                            // Event listener for notification redirection (if needed)
                            notificationElement.querySelector('.event-title').addEventListener('click', () => {
                                if (notification.event_type === 'New Food Order') {
                                    location.href = 'pending_orders.php';
                                }
                            });

                            // Event listener for marking as read
                            const markAsReadButton = notificationElement.querySelector('.mark-as-read-btn');
                            markAsReadButton.addEventListener('click', () => markNotificationAsRead(notification.id));

                            notificationsContainer.appendChild(notificationElement);
                        });
                    } else {
                        notificationsContainer.innerHTML = '<p>No notifications available.</p>';
                    }

                    // Update notification count
                    notificationBadge.textContent = data.totalNotifications || '0';
                } else {
                    notificationsContainer.innerHTML = '<p>Error fetching notifications. Please try again later.</p>';
                    notificationBadge.textContent = '0';
                }
            })
            .catch(error => {
                console.error('Error fetching notifications:', error);
                notificationsContainer.innerHTML = '<p>Error fetching notifications. Please try again later.</p>';
                notificationBadge.textContent = '0';
            });
    }

    // Function to mark notification as read
    function markNotificationAsRead(notificationId) {
        fetch('backend/update_notification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notification_id: notificationId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Refresh the notifications list
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

    // Initial fetch of notifications and count
    fetchNotificationsAndCount();

    // Set up refresh button event listener
    refreshButton.addEventListener('click', fetchNotificationsAndCount);

    // Optionally, refresh the notifications and count periodically
    setInterval(fetchNotificationsAndCount, 60000); // Refresh every minute
});
