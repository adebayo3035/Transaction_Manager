<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/navbar.css">
    <link rel="icon" href="../images/trans_manager.png">
</head>
<body>
    <!-- Mobile Header (Visible on small screens) -->
    <div class="mobile-header">
        <div class="mobile-logo">
            <a href="dashboard.php">
                <i class="fas fa-truck"></i>
                <span>KaraKata</span>
            </a>
        </div>
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <!-- Sidebar Navigation (Mobile) -->
    <div class="mobile-sidebar" id="mobileSidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-truck"></i>
                <span>KaraKata</span>
            </div>
            <button class="sidebar-close" id="sidebarClose">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="sidebar-user">
            <div class="user-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="user-details" id="sidebarCustomerName">
                <span class="user-name">Loading...</span>
                <span class="user-status" id="sidebarWalletBalance">
                    <i class="fas fa-circle"></i> Loading...
                </span>
            </div>
        </div>

        <div class="sidebar-wallet">
            <i class="fas fa-wallet"></i>
            <span id="sidebarWallet">Loading Balance...</span>
        </div>

        <ul class="sidebar-menu">
            <li class="sidebar-item">
                <a href="dashboard.php" class="sidebar-link">
                    <i class="fas fa-sync-alt"></i>
                    <span>Update Order Status</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="view_orders.php" class="sidebar-link">
                    <i class="fas fa-shopping-cart"></i>
                    <span>My Orders</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="profile.php" class="sidebar-link">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="account_history.php" class="sidebar-link">
                    <i class="fas fa-history"></i>
                    <span>Account History</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="driver_bank.php" class="sidebar-link">
                    <i class="fas fa-university"></i>
                    <span>Manage Banks</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="withdrawal.php" class="sidebar-link">
                    <i class="fas fa-hand-holding-usd"></i>
                    <span>Withdraw Fund</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="payments.php" class="sidebar-link">
                    <i class="fas fa-credit-card"></i>
                    <span>Manage Withdrawals</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <button class="sidebar-logout" id="sidebarLogout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </button>
            <div class="sidebar-datetime" id="sidebarDateTime">
                <i class="far fa-calendar-alt"></i>
                <span>Loading...</span>
            </div>
        </div>
    </div>

    <!-- Overlay for mobile sidebar -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Desktop Navigation (Hidden on mobile) -->
    <div class="desktop-nav">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="container">
                <div class="datetime" id="desktopDateTime">
                    <i class="far fa-calendar-alt"></i>
                    <span>Loading Time...</span>
                </div>
                <div class="user-status">
                    <span class="status-badge" id="desktopWalletBalance">
                        <i class="fas fa-circle"></i>
                        Loading Status...
                    </span>
                </div>
            </div>
        </div>

        <!-- Main Header -->
        <header class="main-header">
            <div class="container">
                <div class="logo">
                    <a href="dashboard.php">
                        <i class="fas fa-truck"></i>
                        <span>KaraKata</span>
                    </a>
                </div>

                <div class="user-section">
                    <div class="user-greeting" id="desktopCustomerName">
                        <i class="far fa-user-circle"></i>
                        <span>Loading...</span>
                    </div>
                    <div class="wallet-info" id="desktopWallet">
                        <i class="fas fa-wallet"></i>
                        <span>Loading Balance...</span>
                    </div>
                    <button class="logout-btn" id="desktopLogout">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- Navigation Menu -->
        <nav class="main-nav">
            <div class="container">
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">
                            <i class="fas fa-sync-alt"></i>
                            <span>Update Order</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="view_orders.php" class="nav-link">
                            <i class="fas fa-shopping-cart"></i>
                            <span>My Orders</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="profile.php" class="nav-link">
                            <i class="fas fa-user"></i>
                            <span>Profile</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="account_history.php" class="nav-link">
                            <i class="fas fa-history"></i>
                            <span>History</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="driver_bank.php" class="nav-link">
                            <i class="fas fa-university"></i>
                            <span>Banks</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="withdrawal.php" class="nav-link">
                            <i class="fas fa-hand-holding-usd"></i>
                            <span>Withdraw</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="payments.php" class="nav-link">
                            <i class="fas fa-credit-card"></i>
                            <span>Manage</span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
    </div>

    <script src="../scripts/navbar.js"></script>
</body>
</html>