// Global variables
//const inactivityTimeout = 60 * 1000000; // 10 minute for testing; adjust as needed
const inactivityTimeout = 60 * 1000; // 1 minute for testing
const inactivityTimers = {}; // Stores individual timers for each user

// Helper function to set text content and handle errors
function setTextContent(elementId, message) {
    const element = document.getElementById(elementId);
    if (element) element.textContent = message;
}

// Function to load customer info
function loadCustomerInfo() {
    fetch('../v2/wallet_balance.php')
        .then(response => {
            if (!response.ok) throw new Error('Unauthorized or failed to fetch balance');
            return response.json();
        })
        .then(data => {
            setTextContent('customerName', `Welcome, ${data.customer_name}`);
            setTextContent('walletBalance', `Wallet Balance: ₦ ${data.balance || 0}`);
        })
        .catch(error => {
            setTextContent('customerName', 'Error loading customer name');
            setTextContent('walletBalance', 'Error loading balance');
            console.error('Error:', error);
        });
}

// Function to reset inactivity timer for a specific user
function resetInactivityTimer(userId) {
    // Clear any existing timer for this user
    clearTimeout(inactivityTimers[userId]);

    // Set a new timeout for this user
    inactivityTimers[userId] = setTimeout(() => {
        window.location.href = `../v2/logout.php?logout_id=${userId}`;
    }, inactivityTimeout);
}

// Function to set up session data and initialize inactivity tracking
function getSessionData() {
    fetch('../v2/session_data.php')
        .then(response => response.json())
        .then(data => {
            const userId = data.customer_id;
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
            window.location.href = '../v1/profile.php'; // Redirect on error
        });
}

// Load session data and customer info on page load
window.onload = function() {
    getSessionData();
    loadCustomerInfo();
};

// Nav bar 
// document.addEventListener('DOMContentLoaded', () => {
//     const menuToggle = document.querySelector('.menu-toggle');
//     const navLinks = document.querySelector('.nav-links');

//     menuToggle.addEventListener('click', () => {
//         navLinks.classList.toggle('active');
//     });
// });

