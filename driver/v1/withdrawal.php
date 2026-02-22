<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Wallet Withdrawal</title>
    <link rel="stylesheet" href="../css/withdrawal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <?php include('driver_navbar.php'); ?>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-wallet"></i> Wallet Withdrawal</h1>
            <p>Withdraw your earnings to your bank account</p>
        </div>

        <!-- Wallet Balance Card -->
        <div class="balance-card">
            <div class="balance-icon">
                <i class="fas fa-coins"></i>
            </div>
            <div class="balance-info">
                <div class="balance-label">Available Balance</div>
                <div class="balance-amount" id="balance_amount">₦12,450.00</div>
                <div class="balance-updated">Last updated: Today, 10:30 AM</div>
            </div>
            <div class="balance-actions">
                <button class="btn btn-outline" id="refreshBalance">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Withdrawal Form Card -->
            <div class="card withdrawal-form-card">
                <div class="card-header">
                    <h3><i class="fas fa-money-bill-wave"></i> Request Withdrawal</h3>
                </div>

                <div class="card-body">
                    <form id="withdrawalForm">
                        <!-- Amount Input -->
                        <div class="form-group">
                            <label for="withdrawalAmount">
                                <i class="fas fa-naira-sign"></i> Amount to Withdraw
                            </label>
                            <div class="amount-input-group">
                                <span class="currency">₦</span>
                                <input type="number" id="withdrawalAmount" name="amount" placeholder="0.00" min="100"
                                    max="12450" step="100" required>
                            </div>
                            <div class="amount-hints">
                                <span id = "min_hint">Min: ₦100</span>
                                <span id = "max_hint">Max: ₦12,450</span>
                                <button type="button" class="btn-link" id="withdrawAll">Withdraw All</button>
                            </div>
                        </div>

                        <!-- Bank Selection -->
                        <div class="form-group">
                            <label for="bankName">
                                <i class="fas fa-university"></i> Select Bank
                            </label>
                            <select id="bankName" name="bank_name" required>
                                <option value="">-- Choose your bank --</option>

                            </select>
                        </div>

                        <!-- Account Number -->
                        <div class="form-group">
                            <label for="accountNumber">
                                <i class="fas fa-hashtag"></i> Account Number
                            </label>
                            <input type="text" id="accountNumber" name="account_number"
                                placeholder="Enter 10-digit account number" maxlength="10" pattern="\d{10}" readonly
                                class="readonly-field" required>
                        </div>

                        <!-- Account Name (Auto-filled) -->
                        <div class="form-group">
                            <label for="accountName">
                                <i class="fas fa-user"></i> Account Name
                            </label>
                            <input type="text" id="accountName" name="account_name"
                                placeholder="Account name will appear here" readonly class="readonly-field">
                            <small class="field-note">Account name will be verified automatically</small>
                        </div>

                        <!-- Secret Answer -->
                        <div class="form-group">
                            <label for="secretAnswer">
                                <i class="fas fa-lock"></i> Secret Answer
                            </label>
                            <input type="password" id="secretAnswer" name="secret_answer"
                                placeholder="Input your Secret Answer here">
                        </div>

                        <!-- Withdrawal Note -->
                        <div class="form-group">
                            <label for="withdrawalNote">
                                <i class="fas fa-sticky-note"></i> Note (Optional)
                            </label>
                            <textarea id="withdrawalNote" name="note" placeholder="Add any additional information..."
                                rows="2"></textarea>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-primary btn-block" id="submitWithdrawal">
                            <i class="fas fa-paper-plane"></i> Request Withdrawal
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right Side Cards -->
            <div class="side-cards">
                <!-- Saved Banks Card -->
                <div class="card saved-banks-card">
                    <div class="card-header">
                        <h4><i class="fas fa-bookmark"></i> Saved Banks</h4>
                        <button class="btn-icon" id="addBankBtn" title="Add new bank">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="saved-bank-list" id="savedBankList">
                            <!-- Sample saved bank -->
                            <div class="saved-bank-item">
                                <div class="bank-info">
                                    <span class="bank-name">GTBank</span>
                                    <span class="bank-details">0123456789 - JOHN DOE</span>
                                </div>
                                <button class="btn-use" onclick="useSavedBank('GTBank', '0123456789', 'JOHN DOE')">
                                    <i class="fas fa-check"></i> Use
                                </button>
                            </div>
                            <div class="saved-bank-item">
                                <div class="bank-info">
                                    <span class="bank-name">First Bank</span>
                                    <span class="bank-details">9876543210 - JANE SMITH</span>
                                </div>
                                <button class="btn-use"
                                    onclick="useSavedBank('First Bank', '9876543210', 'JANE SMITH')">
                                    <i class="fas fa-check"></i> Use
                                </button>
                            </div>
                        </div>
                        <div class="empty-banks" id="emptyBanks" style="display: none;">
                            <i class="fas fa-bank"></i>
                            <p>No saved banks yet</p>
                            <small>Add a bank to make withdrawals faster</small>
                        </div>
                    </div>
                </div>

                <!-- Withdrawal History Card -->
                <div class="card history-card">
                    <div class="card-header">
                        <h4><i class="fas fa-history"></i> Recent Withdrawals</h4>
                        <a href="payments.php" class="btn-link">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="history-list" id="historyList">
                            <!-- Dynamic content will be loaded here -->
                        </div>
                        <div class="empty-history" id="emptyHistory" style="display: none;">
                            <i class="fas fa-receipt"></i>
                            <p>No withdrawal history</p>
                        </div>
                    </div>
                </div>

                <!-- Withdrawal Info Card -->
                <div class="card info-card">
                    <div class="card-body">
                        <h5><i class="fas fa-info-circle"></i> Withdrawal Information</h5>
                        <ul class="info-list">
                            <li>
                                <i class="fas fa-check-circle text-success"></i>
                                <span>Minimum withdrawal: ₦100</span>
                            </li>
                            <li>
                                <i class="fas fa-check-circle text-success"></i>
                                <span>Processing time: 24-48 hours</span>
                            </li>
                            <li>
                                <i class="fas fa-check-circle text-success"></i>
                                <span>No withdrawal fees</span>
                            </li>
                            <li>
                                <i class="fas fa-exclamation-circle text-warning"></i>
                                <span>Ensure account details are correct</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Bank Modal -->
        <div id="addBankModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-plus-circle"></i> Add New Bank</h3>
                    <button class="close-modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="addBankForm">
                        <div class="form-group">
                            <label for="modalBankName">Bank Name</label>
                            <select id="modalBankName" required>
                                <option value="">Select bank</option>
                                <option value="Access Bank">Access Bank</option>
                                <option value="GTBank">GTBank</option>
                                <option value="First Bank">First Bank</option>
                                <option value="UBA">UBA</option>
                                <option value="Zenith Bank">Zenith Bank</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="modalAccountNumber">Account Number</label>
                            <input type="text" id="modalAccountNumber" maxlength="10" pattern="\d{10}" required>
                        </div>
                        <div class="form-group">
                            <label for="modalAccountName">Account Name</label>
                            <input type="text" id="modalAccountName" readonly class="readonly-field">
                            <small>Account name will be verified</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelAddBank">Cancel</button>
                    <button class="btn btn-primary" id="saveBank">Save Bank</button>
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
                    <h3>Withdrawal Requested!</h3>
                    <p>Your withdrawal request has been submitted successfully.</p>
                    <div class="withdrawal-summary">
                        <div class="summary-row">
                            <span>Amount:</span>
                            <strong id="successAmount">₦0</strong>
                        </div>
                        <div class="summary-row">
                            <span>Bank:</span>
                            <strong id="successBank">-</strong>
                        </div>
                        <div class="summary-row">
                            <span>Reference:</span>
                            <strong id="successRef">WDV-20240216-001</strong>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary btn-block" id="closeSuccessModal">Done</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../scripts/withdrawal.js"></script>
</body>

</html>