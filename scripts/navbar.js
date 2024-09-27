// Fetch user data from the server when the page loads
document.addEventListener('DOMContentLoaded', function() {
    fetchUserData();
    fetchNotificationsAndCount();
});

// Function to fetch user data
let userId; // Declare a variable to store the unique_id

function fetchUserData() {
    fetch('backend/navbar.php') // Replace with your actual PHP file for fetching user data
        .then(response => response.json())
        .then(data => {
            if (data) {
                // Store unique_id directly from the fetched data
                userId = data.unique_id;

                // Populate welcome message and other UI elements
                const welcomeMessage = document.getElementById('welcomeMessage');
                welcomeMessage.innerHTML = `Welcome, ${data.firstname} ${data.lastname} <i class="fa fa-caret-down"></i>`;

                // Other user data handling can be done here
                // console.log('User Data:', data); // For debugging, you can remove this later
            } else {
                console.error('No user data found.');
                // Optionally redirect to login if no data found
                window.location.href = 'login.php';
            }
        })
        .catch(error => {
            console.error('Error fetching user data:', error);
            // Optionally redirect to login on error
            window.location.href = 'login.php';
        });
}

// Handle logout
const logoutButton = document.getElementById('logoutButton');
logoutButton.addEventListener('click', function() {
    fetch('backend/logout.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ logout_id: userId }), // Use userId fetched from the backend
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Redirect to login page
            window.location.href = 'index.php';
        } else {
            console.error('Logout failed:', data.message);
        }
    })
    .catch(error => {
        console.error('Error during logout:', error);
    });
});

// Function to toggle the responsive navigation menu
function myFunction() {
    var x = document.getElementById("myTopnav");
    if (x.className === "topnav") {
        x.className += " responsive";
    } else {
        x.className = "topnav";
    }
}

const notificationBadge = document.getElementById('notification-badge');
    function fetchNotificationsAndCount() {
        fetch('backend/notification.php') // Replace with your PHP endpoint
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    notificationBadge.textContent = data.totalNotifications || '0';
                } else {
                    console.error('Error fetching notifications:', data.message);
                    notificationBadge.textContent = 0;
                }
            
            })
            .catch(error => {
                console.error('Error fetching notifications:', error);
               
                notificationBadge.textContent = '0';
            });
    }
   