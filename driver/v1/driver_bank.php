<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Bank Management</title>
    <link rel="stylesheet" href="../css/driver_bank.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include('driver_navbar.php'); ?>
    
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-left">
                <h1><i class="fas fa-university"></i> My Bank Accounts</h1>
                <p>Manage your bank accounts for withdrawals</p>
            </div>
            <div class="header-right">
                <button class="btn btn-outline" id="refreshBtn">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Bank Limit Info Card -->
        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-info-circle"></i>
            </div>
            <div class="info-content">
                <h4>Bank Account Limits</h4>
                <p>You can add up to <strong>3 bank accounts</strong> for withdrawals. 
                   The first account you add will be set as your default bank automatically.</p>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Left Column - Bank List -->
            <div class="bank-list-section">
                <div class="section-header">
                    <h3><i class="fas fa-list"></i> Your Saved Banks</h3>
                    <div class="bank-counter" id="bankCounter">
                        <span class="count">0/3</span>
                        <button class="btn btn-primary btn-sm" id="addBankBtn">
                            <i class="fas fa-plus"></i> Add Bank
                        </button>
                    </div>
                </div>

                <!-- Bank List Container -->
                <div class="bank-list-container">
                    <div class="bank-list" id="bankList">
                        <!-- Banks will be loaded here dynamically -->
                    </div>

                    <!-- Empty State -->
                    <div class="empty-state" id="emptyState">
                        <div class="empty-icon">
                            <i class="fas fa-university"></i>
                        </div>
                        <h4>No Banks Added Yet</h4>
                        <p>You haven't added any bank accounts. Add your first bank to start receiving withdrawals.</p>
                        <button class="btn btn-primary" id="emptyAddBankBtn">
                            <i class="fas fa-plus"></i> Add Your First Bank
                        </button>
                    </div>

                    <!-- Loading State -->
                    <div class="loading-state" id="loadingState">
                        <div class="spinner"></div>
                        <p>Loading your banks...</p>
                    </div>
                </div>

                <!-- Quick Tips -->
                <div class="tips-card">
                    <h5><i class="fas fa-lightbulb"></i> Quick Tips</h5>
                    <ul class="tips-list">
                        <li><i class="fas fa-check-circle text-success"></i> Your default bank will be preselected for withdrawals</li>
                        <li><i class="fas fa-check-circle text-success"></i> You can change your default bank anytime</li>
                        <li><i class="fas fa-check-circle text-success"></i> Secret answer is required for any changes</li>
                        <li><i class="fas fa-exclamation-circle text-warning"></i> Maximum of 3 banks allowed</li>
                    </ul>
                </div>
            </div>

            <!-- Right Column - Statistics & Info -->
            <div class="stats-section">
                <!-- Stats Card -->
                <div class="stats-card">
                    <h4><i class="fas fa-chart-pie"></i> Bank Statistics</h4>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-label">Total Banks</span>
                            <span class="stat-value" id="statTotal">0</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Default Bank</span>
                            <span class="stat-value" id="statDefault">-</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Banks Remaining</span>
                            <span class="stat-value" id="statRemaining">3</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Last Updated</span>
                            <span class="stat-value" id="statUpdated">-</span>
                        </div>
                    </div>
                </div>

                <!-- Recent Withdrawals Card (Optional) -->
                <div class="recent-card">
                    <h4><i class="fas fa-history"></i> Recent Withdrawals</h4>
                    <div class="recent-list" id="recentWithdrawals">
                        <!-- Recent withdrawals will be automatically populated here -->
                    </div>
                    <a href="payments.php" class="view-all-link">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <!-- Help Card -->
                <div class="help-card">
                    <i class="fas fa-headset"></i>
                    <h5>Need Help?</h5>
                    <p>Contact support if you're having issues with your bank accounts</p>
                    <button class="btn btn-outline btn-sm" onclick="showSupportModal()">
                        <i class="fas fa-envelope"></i> Contact Support
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Bank Modal -->
    <div id="bankModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-plus-circle"></i> Add New Bank</h3>
                <button class="close-modal" onclick="closeBankModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="bankForm" onsubmit="event.preventDefault();">
                    <input type="hidden" id="bankId" name="bank_id" value="">
                    
                    <!-- Step 1: Bank Selection -->
                    <div class="form-step" id="step1">
                        <div class="step-indicator">
                            <span class="step-number active">1</span>
                            <span class="step-label">Select Bank</span>
                        </div>
                        
                        <div class="form-group">
                            <label for="bankSelect">
                                <i class="fas fa-university"></i> Choose Bank <span class="required">*</span>
                            </label>
                            <select id="bankSelect" class="form-control" required>
                                <option value="">Loading banks...</option>
                            </select>
                            <div class="search-bank">
                                <i class="fas fa-search"></i>
                                <input type="text" id="bankSearch" placeholder="Search for your bank...">
                            </div>
                        </div>
                        
                        <button type="button" class="btn btn-primary btn-next" onclick="nextStep(2)">
                            Next <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>

                    <!-- Step 2: Account Details -->
                    <div class="form-step" id="step2" style="display: none;">
                        <div class="step-indicator">
                            <span class="step-number">2</span>
                            <span class="step-label">Enter Details</span>
                        </div>
                        
                        <div class="selected-bank-info" id="selectedBankInfo">
                            <!-- Selected bank will be shown here -->
                        </div>
                        
                        <div class="form-group">
                            <label for="accountNumber">
                                <i class="fas fa-hashtag"></i> Account Number <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="accountNumber" 
                                   class="form-control" 
                                   placeholder="Enter 10-digit account number"
                                   maxlength="10"
                                   pattern="\d{10}"
                                   required>
                            <div class="input-hint">
                                <i class="fas fa-info-circle"></i>
                                Enter the 10-digit account number
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="accountName">
                                <i class="fas fa-user"></i> Account Name <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="accountName" 
                                   class="form-control" 
                                   placeholder="Account name will appear here"
                                   readonly>
                            <div class="verify-status" id="verifyStatus"></div>
                        </div>

                        <div class="form-group">
                            <label for="secretAnswer">
                                <i class="fas fa-lock"></i> Secret Answer <span class="required">*</span>
                            </label>
                            <input type="password" 
                                   id="secretAnswer" 
                                   class="form-control" 
                                   placeholder="Enter your secret answer to authorize"
                                   required>
                            <div class="input-hint">
                                <i class="fas fa-shield-alt"></i>
                                Your secret answer is required for security
                            </div>
                        </div>

                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="setDefault" checked>
                                <span class="checkmark"></span>
                                Set as default bank account
                            </label>
                        </div>

                        <div class="warning-box" id="sameBankWarning" style="display: none;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>You already have an account with this bank. Adding another is allowed.</span>
                        </div>

                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary" onclick="prevStep(1)">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            <button type="button" class="btn btn-primary" id="saveBankBtn" onclick="saveBank()">
                                <i class="fas fa-save"></i> Save Bank
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Verification Loading -->
                <div class="verification-loading" id="verificationLoading" style="display: none;">
                    <div class="spinner-small"></div>
                    <p>Verifying account details...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Account Name Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Account Name</h3>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editForm" onsubmit="event.preventDefault();">
                    <input type="hidden" id="editBankId">
                    
                    <div class="form-group">
                        <label for="editAccountName">
                            <i class="fas fa-user"></i> Account Name <span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="editAccountName" 
                               class="form-control" 
                               placeholder="Enter correct account name"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="editSecretAnswer">
                            <i class="fas fa-lock"></i> Secret Answer <span class="required">*</span>
                        </label>
                        <input type="password" 
                               id="editSecretAnswer" 
                               class="form-control" 
                               placeholder="Enter your secret answer"
                               required>
                    </div>

                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="editSetDefault">
                            <span class="checkmark"></span>
                            Set as default bank account
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button class="btn btn-primary" onclick="updateBank()">
                    <i class="fas fa-save"></i> Update
                </button>
            </div>
        </div>
    </div>

    <!-- Set Default Confirmation Modal -->
    <div id="defaultModal" class="modal">
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3><i class="fas fa-star"></i> Set as Default</h3>
                <button class="close-modal" onclick="closeDefaultModal()">&times;</button>
            </div>
            <div class="modal-body text-center">
                <div class="confirm-icon">
                    <i class="fas fa-question-circle"></i>
                </div>
                <h4>Set as Default Bank?</h4>
                <p id="defaultBankInfo">Make this your default bank for withdrawals?</p>
                
                <div class="form-group">
                    <label for="defaultSecretAnswer">
                        <i class="fas fa-lock"></i> Enter Secret Answer to Confirm
                    </label>
                    <input type="password" 
                           id="defaultSecretAnswer" 
                           class="form-control" 
                           placeholder="Your secret answer">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDefaultModal()">Cancel</button>
                <button class="btn btn-primary" onclick="confirmSetDefault()">
                    <i class="fas fa-check"></i> Confirm
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3><i class="fas fa-trash-alt" style="color: #dc3545;"></i> Delete Bank</h3>
                <button class="close-modal" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body text-center">
                <div class="warning-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h4>Are you sure?</h4>
                <p id="deleteBankInfo">This action cannot be undone.</p>
                
                <div class="form-group">
                    <label for="deleteSecretAnswer">
                        <i class="fas fa-lock"></i> Enter Secret Answer to Confirm
                    </label>
                    <input type="password" 
                           id="deleteSecretAnswer" 
                           class="form-control" 
                           placeholder="Your secret answer">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <button class="btn btn-danger" onclick="confirmDelete()">
                    <i class="fas fa-trash"></i> Delete
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
                <button class="btn btn-primary btn-block" onclick="closeSuccessModal()">
                    Continue
                </button>
            </div>
        </div>
    </div>

    <!-- Support Modal -->
    <div id="supportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-headset"></i> Contact Support</h3>
                <button class="close-modal" onclick="closeSupportModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="supportForm" onsubmit="event.preventDefault();">
                    <div class="form-group">
                        <label for="supportSubject">Subject</label>
                        <input type="text" id="supportSubject" class="form-control" 
                               value="Bank Account Issue" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="supportMessage">Message</label>
                        <textarea id="supportMessage" class="form-control" rows="4" 
                                  placeholder="Describe your issue..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="supportEmail">Your Email</label>
                        <input type="email" id="supportEmail" class="form-control" 
                               placeholder="Enter your email" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeSupportModal()">Cancel</button>
                <button class="btn btn-primary" onclick="sendSupportMessage()">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toastContainer" class="toast-container"></div>

    <script src="../scripts/driver_bank.js"></script>
</body>
</html>