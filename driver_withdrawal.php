<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Withdrawal Management</title>
    <link rel="stylesheet" href="css/driver_withdrawal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- External Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
</head>

<body>
    <?php include('navbar.php'); ?>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-left">
                <h1><i class="fas fa-hand-holding-usd"></i> Withdrawal Management</h1>
                <p>Process and manage driver withdrawal requests</p>
            </div>
            <div class="header-right">
                <button class="btn btn-outline" id="refreshBtn">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <!-- <button class="btn btn-primary" id="exportBtn">
                    <i class="fas fa-download"></i> Export Report
                </button> -->
            </div>
            <div class="export-actions">
                <div class="export-dropdown">
                    <button id="exportBtn" class="btn btn-primary">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <div class="dropdown-menu" id="exportMenu">
                        <button onclick="adminManager.exportReport('csv')">
                            <i class="fas fa-file-csv"></i> CSV
                        </button>
                        <button onclick="adminManager.exportReport('excel')">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button onclick="adminManager.exportReport('pdf')">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                        <button onclick="adminManager.exportReport('print')">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="summary-grid" id="summaryContainer">
            <div class="summary-card total">
                <div class="summary-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="summary-content">
                    <span class="summary-label">Pending Requests</span>
                    <span class="summary-value" id="pendingCount">0</span>
                    <span class="summary-sub" id="pendingAmount">₦0.00</span>
                </div>
            </div>

            <div class="summary-card processing">
                <div class="summary-icon">
                    <i class="fas fa-spinner"></i>
                </div>
                <div class="summary-content">
                    <span class="summary-label">Processing</span>
                    <span class="summary-value" id="processingCount">0</span>
                    <span class="summary-sub" id="processingAmount">₦0.00</span>
                </div>
            </div>

            <div class="summary-card completed">
                <div class="summary-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="summary-content">
                    <span class="summary-label">Completed Today</span>
                    <span class="summary-value" id="completedToday">0</span>
                    <span class="summary-sub" id="completedAmount">₦0.00</span>
                </div>
            </div>

            <div class="summary-card total-all">
                <div class="summary-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="summary-content">
                    <span class="summary-label">Total Processed</span>
                    <span class="summary-value" id="totalProcessed">0</span>
                    <span class="summary-sub" id="totalAmount">₦0.00</span>
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
                <label for="driverSearch">Driver:</label>
                <input type="text" id="driverSearch" class="filter-input" placeholder="Search driver...">
            </div>

            <div class="filter-group">
                <label for="dateFrom">From:</label>
                <input type="date" id="dateFrom" class="filter-input">
            </div>

            <div class="filter-group">
                <label for="dateTo">To:</label>
                <input type="date" id="dateTo" class="filter-input">
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

        <!-- Bulk Actions Bar - Hidden by default -->
        <div class="bulk-actions-bar" id="bulkActionsBar" style="display: none;">
            <div class="bulk-info">
                <i class="fas fa-check-square"></i>
                <span id="selectedCount">0</span> withdrawals selected
            </div>
            <div class="bulk-buttons">
                <button class="btn btn-primary" onclick="adminManager.showBulkModal('approve')">
                    <i class="fas fa-check-circle"></i> Approve Selected
                </button>
                <button class="btn btn-warning" onclick="adminManager.showBulkModal('reject')">
                    <i class="fas fa-times-circle"></i> Reject Selected
                </button>
                <button class="btn btn-danger" onclick="adminManager.showBulkModal('mark_failed')">
                    <i class="fas fa-exclamation-circle"></i> Mark as Failed
                </button>
                <button class="btn btn-secondary" onclick="adminManager.clearSelection()">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <button class="action-btn" onclick="filterByStatus('pending')">
                <span class="badge pending">!</span>
                Show Pending
            </button>
            <button class="action-btn" onclick="filterByStatus('processing')">
                Show Processing
            </button>
            <button class="action-btn" onclick="filterByDate('today')">
                Today
            </button>
            <button class="action-btn" onclick="filterByDate('week')">
                This Week
            </button>
            <button class="action-btn" onclick="filterByDate('month')">
                This Month
            </button>
        </div>

        <!-- Withdrawals Table -->
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Withdrawal Requests</h3>
                <div class="table-info">
                    <span id="showingInfo">Showing 0 entries</span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="withdrawals-table" id="withdrawalsTable">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>Reference</th>
                            <th>Driver</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Bank Details</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="withdrawalsBody">
                        <tr id="loadingRow">
                            <td colspan="8" class="loading-cell">
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
                <p>No withdrawal requests match your criteria.</p>
            </div>

            <!-- Pagination -->
            <div class="pagination" id="pagination"></div>
        </div>
    </div>

    <!-- Process Withdrawal Modal -->
    <div id="processModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-tasks"></i> Process Withdrawal</h3>
                <button class="close-modal" onclick="closeProcessModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="withdrawal-details" id="processDetails">
                    <!-- Details will be loaded here -->
                </div>

                <div class="form-group">
                    <label for="processAction">Action:</label>
                    <select id="processAction" class="form-control" onchange="toggleProcessFields()">
                        <option value="approve">Approve & Process</option>
                        <option value="reject">Reject</option>
                        <option value="mark_failed">Mark as Failed</option>
                    </select>
                </div>

                <div id="approveFields">
                    <div class="form-group">
                        <label for="transactionReference">Transaction Reference:</label>
                        <input type="text" id="transactionReference" class="form-control"
                            placeholder="Enter bank transaction reference">
                    </div>
                </div>

                <div id="rejectFields" style="display: none;">
                    <div class="form-group">
                        <label for="rejectReason">Rejection Reason:</label>
                        <textarea id="rejectReason" class="form-control" rows="3"
                            placeholder="Explain why this withdrawal is being rejected"></textarea>
                    </div>
                </div>

                <div id="failedFields" style="display: none;">
                    <div class="form-group">
                        <label for="failedReason">Failure Reason:</label>
                        <textarea id="failedReason" class="form-control" rows="3"
                            placeholder="Explain why this withdrawal failed"></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label for="adminNotes">Admin Notes:</label>
                    <textarea id="adminNotes" class="form-control" rows="2"
                        placeholder="Additional notes (optional)"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeProcessModal()">Cancel</button>
                <button class="btn btn-primary" onclick="processWithdrawal()" id="processBtn">
                    <i class="fas fa-check"></i> Process
                </button>
            </div>
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

    <!-- Driver Details Modal -->
    <div id="driverModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user"></i> Driver Information</h3>
                <button class="close-modal" onclick="closeDriverModal()">&times;</button>
            </div>
            <div class="modal-body" id="driverContent">
                <!-- Driver details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDriverModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Bulk Action Modal -->
    <!-- Bulk Action Modal -->
    <div id="bulkModal" class="modal">
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3 id="bulkModalTitle"><i class="fas fa-tasks"></i> Bulk Action</h3>
                <button class="close-modal" onclick="closeBulkModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="bulk-summary"
                    style="background: #e8f4fd; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
                    <p>Processing <strong id="bulkCount">0</strong> selected withdrawals</p>
                </div>

                <div class="form-group">
                    <label for="bulkActionType">Action:</label>
                    <select id="bulkActionType" class="form-control" onchange="updateBulkModalTitle()">
                        <option value="approve">Approve Selected</option>
                        <option value="reject">Reject Selected</option>
                        <option value="mark_failed">Mark as Failed</option>
                    </select>
                </div>

                <div id="bulkApproveFields">
                    <div class="form-group">
                        <label for="bulkTransactionRef">Transaction Reference (optional):</label>
                        <input type="text" id="bulkTransactionRef" class="form-control"
                            placeholder="Common transaction reference">
                        <small style="color: #666; font-size: 12px;">Leave blank if different references</small>
                    </div>
                </div>

                <div id="bulkRejectFields" style="display: none;">
                    <div class="form-group">
                        <label for="bulkRejectReason">Rejection Reason:</label>
                        <textarea id="bulkRejectReason" class="form-control" rows="3"
                            placeholder="Reason for rejection"></textarea>
                    </div>
                </div>

                <div id="bulkFailedFields" style="display: none;">
                    <div class="form-group">
                        <label for="bulkFailedReason">Failure Reason:</label>
                        <textarea id="bulkFailedReason" class="form-control" rows="3"
                            placeholder="Reason for failure"></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label for="bulkAdminNotes">Admin Notes (optional):</label>
                    <textarea id="bulkAdminNotes" class="form-control" rows="2"
                        placeholder="Additional notes for all selected withdrawals"></textarea>
                </div>

                <div class="warning-box"
                    style="margin-top: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 12px 15px; display: flex; align-items: center; gap: 10px; color: #856404;">
                    <i class="fas fa-exclamation-triangle" style="color: #e17055;"></i>
                    <span>This action will be applied to all selected withdrawals and cannot be undone.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeBulkModal()">Cancel</button>
                <button class="btn btn-primary" onclick="processBulkAction()" id="bulkProcessBtn">
                    <i class="fas fa-check"></i> Process Selected
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
                <h3>Success!</h3>
                <p id="successMessage">Operation completed successfully.</p>
                <button class="btn btn-primary btn-block" onclick="closeSuccessModal()">
                    Continue
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>

    <script src="scripts/driver_withdrawals.js"></script>
</body>

</html>