// Function to load customer info
function loadDriverInfo() {
    fetch('../v2/profile.php', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Unauthorized or failed to fetch balance');
        }
        return response.json();
    })
    .then(data => {
        document.getElementById('customerName').textContent = `Welcome, ${data.firstname} - ${data.lastname}`;
        document.getElementById('walletBalance').textContent = `Your Current Status is: ${data.status}`;
    })
    .catch(error => {
        document.getElementById('customerName').textContent = 'Error loading Driver Name';
        document.getElementById('walletBalance').textContent = 'Error loading current Status';
        console.error('Error:', error);
    });
}

const inactivityTimeout = 30 * 60 * 1000; // 3 minute in milliseconds
let inactivityTimer;

// Function to reset the inactivity timer
function resetInactivityTimer(userId) {
    clearTimeout(inactivityTimer);
    inactivityTimer = setTimeout(function () {
        // Session has timed out due to inactivity, redirect to logout
        window.location.href = '../v2/logout.php';
    }, inactivityTimeout);
}

// Fetch session data (customer_id) from the server
function getSessionData() {
    fetch('../v2/session_data.php')
        .then(response => response.json())
        .then(data => {
            const userId = data.driver_id;
            if (userId) {
                resetInactivityTimer(userId);

                // Add event listeners for user interaction to reset inactivity timer
                document.addEventListener('mousemove', function () {
                    resetInactivityTimer(userId);
                });
                document.addEventListener('keydown', function () {
                    resetInactivityTimer(userId);
                });
            } else {
                // No session: Redirect to login page
                window.location.href = '../v1/index.php';
            }
        })
        .catch(error => {
            console.error('Error fetching session data:', error);
            // If there's an error in fetching, redirect to login page as a fallback
            window.location.href = '../v1/index.php';
        });
}

// Combine both functions inside a single onload event
window.onload = function() {
    getSessionData();
    loadDriverInfo();
};
