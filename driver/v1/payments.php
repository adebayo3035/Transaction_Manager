<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Withdrawals</title>
    <link rel="stylesheet" href="../css/payments.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include('driver_navbar.php'); ?>
    
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-left">
                <h1><i class="fas fa-hand-holding-usd"></i> My Withdrawals</h1>
                <p>Track and manage your withdrawal requests</p>
            </div>
            <div class="header-right">
                <button class="btn btn-outline" id="refreshBtn">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <a href="withdrawal.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Withdrawal
                </a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="summary-grid" id="summaryContainer">
            <div class="summary-card wallet-balance">
                <div class="summary-icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="summary-content">
                    <span class="summary-label">Wallet Balance</span>
                    <span class="summary-value" id="walletBalance">₦0.00</span>
                </div>
            </div>
            
            <div class="summary-card total-withdrawals">
                <div class="summary-icon">
                    <i class="fas fa-history"></i>
                </div>
                <div class="summary-content">
                    <span class="summary-label">Total Withdrawals</span>
                    <span class="summary-value" id="totalWithdrawals">0</span>
                    <span class="summary-sub" id="totalAmount">₦0.00</span>
                </div>
            </div>
            
            <div class="summary-card pending">
                <div class="summary-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="summary-content">
                    <span class="summary-label">Pending</span>
                    <span class="summary-value" id="pendingCount">0</span>
                    <span class="summary-sub" id="pendingAmount">₦0.00</span>
                </div>
            </div>
            
            <div class="summary-card completed">
                <div class="summary-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="summary-content">
                    <span class="summary-label">Completed</span>
                    <span class="summary-value" id="completedCount">0</span>
                    <span class="summary-sub" id="completedAmount">₦0.00</span>
                </div>
            </div>
            
            <div class="summary-card failed">
                <div class="summary-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="summary-content">
                    <span class="summary-label">Failed</span>
                    <span class="summary-value" id="failedCount">0</span>
                    <span class="summary-sub" id="failedAmount">₦0.00</span>
                </div>
            </div>
            
            <div class="summary-card last-withdrawal">
                <div class="summary-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="summary-content">
                    <span class="summary-label">Last Withdrawal</span>
                    <span class="summary-value" id="lastWithdrawal">Never</span>
                </div>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="filters-card">
            <div class="filter-group">
                <label for="statusFilter">Status:</label>
                <select id="statusFilter" class="filter-select">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="completed">Completed</option>
                    <option value="failed">Failed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="dateFrom">From:</label>
                <input type="date" id="dateFrom" class="filter-input">
            </div>
            
            <div class="filter-group">
                <label for="dateTo">To:</label>
                <input type="date" id="dateTo" class="filter-input">
            </div>
            
            <div class="filter-group">
                <label for="searchFilter">Search:</label>
                <input type="text" id="searchFilter" class="filter-input" placeholder="Reference or Bank...">
            </div>
            
            <div class="filter-actions">
                <button class="btn btn-secondary" id="clearFiltersBtn">
                    <i class="fas fa-times"></i> Clear
                </button>
                <button class="btn btn-primary" id="applyFiltersBtn">
                    <i class="fas fa-filter"></i> Apply
                </button>
            </div>
        </div>

        <!-- Withdrawals Table -->
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Withdrawal History</h3>
                <div class="table-info">
                    <span id="showingInfo">Showing 0 entries</span>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="withdrawals-table" id="withdrawalsTable">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Bank</th>
                            <th>Account</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="withdrawalsBody">
                        <tr id="loadingRow">
                            <td colspan="7" class="loading-cell">
                                <div class="loading-spinner">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <span>Loading withdrawals...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div id="emptyState" class="empty-state" style="display: none;">
                <i class="fas fa-receipt"></i>
                <h3>No Withdrawals Found</h3>
                <p>You haven't made any withdrawals yet.</p>
                <a href="withdrawal.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Request Withdrawal
                </a>
            </div>

            <!-- Pagination -->
            <div class="pagination" id="pagination"></div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> Withdrawal Details</h3>
                <button class="close-modal" onclick="closeDetailsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="details-grid" id="detailsContent">
                    <!-- Details will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDetailsModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Cancel Withdrawal Modal -->
    <div id="cancelModal" class="modal">
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3><i class="fas fa-ban" style="color: #dc3545;"></i> Cancel Withdrawal</h3>
                <button class="close-modal" onclick="closeCancelModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="warning-box">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Are you sure you want to cancel this withdrawal? The amount will be refunded to your wallet.</p>
                </div>
                
                <div class="withdrawal-info" id="cancelWithdrawalInfo">
                    <!-- Withdrawal info will be shown here -->
                </div>
                
                <div class="form-group">
                    <label for="cancelReason">
                        <i class="fas fa-comment-alt"></i> Reason (Optional)
                    </label>
                    <textarea id="cancelReason" class="form-control" rows="2" 
                              placeholder="Tell us why you're cancelling..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="cancelSecretAnswer">
                        <i class="fas fa-lock"></i> Secret Answer <span class="required">*</span>
                    </label>
                    <input type="password" id="cancelSecretAnswer" class="form-control" 
                           placeholder="Enter your secret answer" required>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeCancelModal()">Close</button>
                <button class="btn btn-danger" onclick="confirmCancel()" id="confirmCancelBtn">
                    <i class="fas fa-ban"></i> Cancel Withdrawal
                </button>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="modal">
        <div class="modal-content modal-sm">
            <div class="modal-body text-center">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 id="successTitle">Success!</h3>
                <p id="successMessage">Operation completed successfully.</p>
                <p id="successDetails" class="success-details"></p>
                <button class="btn btn-primary btn-block" onclick="closeSuccessModal()">
                    Continue
                </button>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div id="errorModal" class="modal">
        <div class="modal-content modal-sm">
            <div class="modal-body text-center">
                <div class="error-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <h3>Error</h3>
                <p id="errorMessage">An error occurred.</p>
                <button class="btn btn-primary btn-block" onclick="closeErrorModal()">
                    OK
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>

    <script src="../scripts/payments.js"></script>
</body>
</html>