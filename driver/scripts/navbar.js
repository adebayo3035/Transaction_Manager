// Global variables
//const inactivityTimeout = 60 * 10000000; // 1 minute for testing; adjust as needed
const inactivityTimeout = 60 * 1000; // 1 minute for testing
let inactivityTimers = {};

// Helper function to set error message
function setError(elementId, message) {
    document.getElementById(elementId).textContent = message;
}

// Function to load driver info
function loadDriverInfo() {
    fetch('../v2/profile.php')
        .then(response => {
            if (!response.ok) throw new Error('Unauthorized or failed to fetch driver info');
            return response.json();
        })
        .then(data => {
            document.getElementById('customerName').textContent = `Welcome, ${data.firstname} - ${data.lastname}`;
            document.getElementById('walletBalance').textContent = `Your Current Status is: ${data.status}`;
            document.getElementById('wallet').textContent = `Wallet Balance: N ${data.wallet_balance}`;
        })
        .catch(error => {
            setError('customerName', 'Error loading Driver Name');
            setError('walletBalance', 'Error loading current Status');
            setError('wallet', 'Error loading Wallet Balance');
            console.error('Error:', error);
        });
}

// Function to reset inactivity timer and log out if timeout is reached
function resetInactivityTimer(userId) {
    clearTimeout(inactivityTimers[userId]);
    inactivityTimers[userId] = setTimeout(() => {
        window.location.href = `../v2/logout.php?logout_id=${userId}`;
    }, inactivityTimeout);
}

// Function to set up session data and initialize inactivity tracking
function getSessionData() {
    fetch('../v2/session_data.php')
        .then(response => response.json())
        .then(data => {
            const userId = data.driver_id;
            if (userId) {
                resetInactivityTimer(userId);
                // Add event listeners for user interaction to reset inactivity timer
                ['mousemove', 'keydown'].forEach(event =>
                    document.addEventListener(event, () => resetInactivityTimer(userId))
                );
            } else {
                window.location.href = '../v1/index.php'; // Redirect if no session
            }
        })
        .catch(error => {
            console.error('Error fetching session data:', error);
            window.location.href = '../v1/index.php'; // Redirect on error
        });
}
function updateDateTime() {
    const now = new Date();
    const formattedDateTime = now.toLocaleString('en-GB', { 
        year: 'numeric', 
        month: '2-digit', 
        day: '2-digit', 
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit',
        hour12: false // 24-hour format
    }).replace(',', ''); // Removes unwanted comma

    document.getElementById('dateTimeLabel').textContent = formattedDateTime;
}
// Load session data and driver info on page load
window.onload = function() {
    getSessionData();
    loadDriverInfo();
    // Update the date and time every second
setInterval(updateDateTime, 1000);
// Call the function immediately to avoid waiting 1 second
updateDateTime();
};
