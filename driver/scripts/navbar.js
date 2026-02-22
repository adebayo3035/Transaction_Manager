// Global variables
const inactivityTimeout = 60 * 60 * 1000; // 1 hour
let inactivityTimers = {};

// Helper function to set error message
function setError(elementId, message) {
    const elements = document.querySelectorAll(`[id="${elementId}"], [id="sidebar${elementId}"], [id="desktop${elementId}"]`);
    elements.forEach(element => {
        if (element) {
            element.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        }
    });
}

// Mobile sidebar functionality
function setupMobileSidebar() {
    const menuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('mobileSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const closeBtn = document.getElementById('sidebarClose');

    if (menuBtn && sidebar && overlay) {
        // Open sidebar
        menuBtn.addEventListener('click', () => {
            sidebar.classList.add('show');
            overlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        });

        // Close sidebar function
        const closeSidebar = () => {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        };

        // Close with close button
        if (closeBtn) {
            closeBtn.addEventListener('click', closeSidebar);
        }

        // Close with overlay click
        overlay.addEventListener('click', closeSidebar);

        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && sidebar.classList.contains('show')) {
                closeSidebar();
            }
        });
    }
}

// Function to load driver info
function loadDriverInfo() {
    // Set loading states for all elements
    const loadingElements = [
        'customerName', 'walletBalance', 'wallet',
        'sidebarCustomerName', 'sidebarWalletBalance', 'sidebarWallet',
        'desktopCustomerName', 'desktopWalletBalance', 'desktopWallet'
    ];

    loadingElements.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            if (id.includes('sidebarCustomerName')) {
                element.innerHTML = `
                    <span class="user-name loading">Loading...</span>
                    <span class="user-status"><i class="fas fa-circle"></i> <span class="loading">Loading...</span></span>
                `;
            } else if (id.includes('walletBalance') || id.includes('sidebarWalletBalance')) {
                element.innerHTML = `<i class="fas fa-circle"></i> <span class="loading">Loading...</span>`;
            } else if (id.includes('wallet') || id.includes('sidebarWallet')) {
                element.innerHTML = `<i class="fas fa-wallet"></i> <span class="loading">Loading...</span>`;
            } else if (element.tagName === 'DIV' && element.classList.contains('user-details')) {
                // Handle sidebar user details separately
            } else {
                element.innerHTML = '<span class="loading">Loading...</span>';
            }
        }
    });

    fetch('../v2/profile.php')
        .then(response => {
            if (!response.ok) throw new Error('Unauthorized or failed to fetch driver info');
            return response.json();
        })
        .then(data => {
            // Update desktop elements
            const desktopCustomer = document.getElementById('desktopCustomerName');
            if (desktopCustomer) {
                desktopCustomer.innerHTML = `
                    <i class="far fa-user-circle"></i>
                    <span>Welcome, ${data.firstname} ${data.lastname}</span>
                `;
            }

            const desktopBalance = document.getElementById('desktopWalletBalance');
            if (desktopBalance) {
                desktopBalance.innerHTML = `
                    <i class="fas fa-circle" style="color: ${data.status === 'active' ? '#4ade80' : '#fbbf24'}"></i>
                    <span>${data.status.charAt(0).toUpperCase() + data.status.slice(1)}</span>
                `;
            }

            const desktopWallet = document.getElementById('desktopWallet');
            if (desktopWallet) {
                desktopWallet.innerHTML = `
                    <i class="fas fa-wallet"></i>
                    <span>₦ ${parseFloat(data.wallet_balance).toLocaleString()}</span>
                `;
            }

            // Update sidebar elements
            const sidebarCustomer = document.getElementById('sidebarCustomerName');
            if (sidebarCustomer) {
                sidebarCustomer.innerHTML = `
                    <span class="user-name">${data.firstname} ${data.lastname}</span>
                    <span class="user-status">
                        <i class="fas fa-circle" style="color: ${data.status === 'active' ? '#4ade80' : '#fbbf24'}"></i>
                        ${data.status.charAt(0).toUpperCase() + data.status.slice(1)}
                    </span>
                `;
            }

            const sidebarWallet = document.getElementById('sidebarWallet');
            if (sidebarWallet) {
                sidebarWallet.innerHTML = `
                    <i class="fas fa-wallet"></i>
                    <span>₦ ${parseFloat(data.wallet_balance).toLocaleString()}</span>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            setError('customerName', 'Error loading');
            setError('walletBalance', 'Error');
            setError('wallet', 'Error');
        });
}

// Function to reset inactivity timer
function resetInactivityTimer(userId) {
    clearTimeout(inactivityTimers[userId]);
    inactivityTimers[userId] = setTimeout(() => {
        window.location.href = `../v2/logout.php?logout_id=${userId}`;
    }, inactivityTimeout);
}

// Function to get session data
function getSessionData() {
    fetch('../v2/session_data.php')
        .then(response => response.json())
        .then(data => {
            const userId = data.driver_id;
            if (userId) {
                resetInactivityTimer(userId);
                ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(event =>
                    document.addEventListener(event, () => resetInactivityTimer(userId))
                );
            } else {
                window.location.href = '../v1/index.php';
            }
        })
        .catch(error => {
            console.error('Error fetching session data:', error);
            window.location.href = '../v1/index.php';
        });
}

// Function to update date and time
function updateDateTime() {
    const now = new Date();
    const options = {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    };
    
    const formattedDateTime = now.toLocaleString('en-US', options);
    
    // Update desktop datetime
    const desktopDateTime = document.getElementById('desktopDateTime');
    if (desktopDateTime) {
        desktopDateTime.innerHTML = `
            <i class="far fa-calendar-alt"></i>
            <span>${formattedDateTime}</span>
        `;
    }
    
    // Update sidebar datetime
    const sidebarDateTime = document.getElementById('sidebarDateTime');
    if (sidebarDateTime) {
        sidebarDateTime.innerHTML = `
            <i class="far fa-calendar-alt"></i>
            <span>${formattedDateTime}</span>
        `;
    }
}

// Set active nav link
function setActiveNavLink() {
    const currentPage = window.location.pathname.split('/').pop();
    
    // Desktop nav links
    document.querySelectorAll('.nav-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage) {
            link.classList.add('active');
        }
    });
    
    // Sidebar nav links
    document.querySelectorAll('.sidebar-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage) {
            link.classList.add('active');
        }
    });
}

// Handle logout
function setupLogout() {
    const logoutButtons = [
        document.getElementById('desktopLogout'),
        document.getElementById('sidebarLogout')
    ];
    
    logoutButtons.forEach(btn => {
        if (btn) {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                if (confirm('Are you sure you want to logout?')) {
                    window.location.href = '../v2/logout.php';
                }
            });
        }
    });
}

// Initialize everything
document.addEventListener('DOMContentLoaded', function() {
    setupMobileSidebar();
    getSessionData();
    loadDriverInfo();
    setActiveNavLink();
    setupLogout();
    
    // Update date and time every second
    setInterval(updateDateTime, 1000);
    updateDateTime();
});