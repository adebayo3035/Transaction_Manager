<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Management</title>
    <link rel="stylesheet" href="css/bank.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include('navbar.php'); ?>
    
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <div class="header-left">
                <h1><i class="fas fa-university"></i> Bank Management</h1>
                <p>Manage bank codes and information for withdrawals</p>
            </div>
            <div class="header-actions">
                <a href="bank_upload.php" class="btn btn-secondary" style="margin-right: 10px;">
                    <i class="fas fa-upload"></i> Import Banks
                </a>
                <button class="btn btn-primary" id="addBankBtn">
                    <i class="fas fa-plus"></i> Add New Bank
                </button>
                <button class="btn btn-outline" id="refreshBtn">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total-banks">
                    <i class="fas fa-university"></i>
                </div>
                <div class="stat-content">
                    <h3 id="totalBanks">0</h3>
                    <p>Total Banks</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon active-banks">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3 id="activeBanks">0</h3>
                    <p>Active Banks</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon inactive-banks">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <h3 id="inactiveBanks">0</h3>
                    <p>Inactive Banks</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon used-banks">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3 id="usedBanks">0</h3>
                    <p>Used by Drivers</p>
                </div>
            </div>
        </div>

        <!-- Controls -->
        <div class="controls-card">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search by bank name or code...">
            </div>
            <div class="filter-options">
                <label class="checkbox-label">
                    <input type="checkbox" id="showInactive">
                    <span>Show Inactive Banks</span>
                </label>
                <select id="sortBy" class="sort-select">
                    <option value="name_asc">Name (A-Z)</option>
                    <option value="name_desc">Name (Z-A)</option>
                    <option value="code_asc">Code (A-Z)</option>
                    <option value="code_desc">Code (Z-A)</option>
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                </select>
            </div>
        </div>

        <!-- Banks Table -->
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Banks List</h3>
                <div class="table-actions">
                    <span id="selectedCount" class="selected-count">0 selected</span>
                    <button class="btn btn-sm btn-danger" id="bulkDeleteBtn" disabled>
                        <i class="fas fa-trash"></i> Delete Selected
                    </button>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="banks-table">
                    <thead>
                        <tr>
                            <th width="40px">
                                <input type="checkbox" id="selectAll">
                            </th>
                            <th>Bank Code</th>
                            <th>Bank Name</th>
                            <th>Longcode</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="banksTableBody">
                        <tr id="loadingRow">
                            <td colspan="8" class="loading-cell">
                                <div class="loading-spinner">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <span>Loading banks...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div id="emptyState" class="empty-state" style="display: none;">
                <i class="fas fa-university"></i>
                <h3>No Banks Found</h3>
                <p>No banks match your search criteria. Try adjusting your filters or add a new bank.</p>
                <button class="btn btn-primary" onclick="bankManager.openAddModal()">
                    <i class="fas fa-plus"></i> Add First Bank
                </button>
            </div>

            <!-- Pagination -->
            <div class="pagination" id="pagination"></div>
        </div>
    </div>

    <!-- Bank Modal (Add/Edit) -->
    <div id="bankModal" class="modal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-plus-circle"></i> Add New Bank</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="bankForm">
                    <input type="hidden" id="bankId" name="bank_id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bankCode">
                                <i class="fas fa-barcode"></i> Bank Code <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="bankCode" 
                                   name="bank_code" 
                                   maxlength="20" 
                                   placeholder="e.g., 058"
                                   required>
                            <small class="field-hint">Unique bank identifier code</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="bankName">
                                <i class="fas fa-university"></i> Bank Name <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="bankName" 
                                   name="bank_name" 
                                   placeholder="e.g., GTBank"
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="longcode">
                                <i class="fas fa-sort-numeric-up"></i> Longcode
                            </label>
                            <input type="text" 
                                   id="longcode" 
                                   name="longcode" 
                                   maxlength="50" 
                                   placeholder="e.g., 058">
                        </div>
                        
                        <div class="form-group">
                            <label for="slug">
                                <i class="fas fa-link"></i> Slug
                            </label>
                            <input type="text" 
                                   id="slug" 
                                   name="slug" 
                                   placeholder="e.g., gtbank-nigeria">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="gateway">
                                <i class="fas fa-network-wired"></i> Gateway
                            </label>
                            <input type="text" 
                                   id="gateway" 
                                   name="gateway" 
                                   placeholder="e.g., paystack">
                        </div>
                        
                        <div class="form-group">
                            <label for="bankType">
                                <i class="fas fa-tag"></i> Type
                            </label>
                            <input type="text" 
                                   id="bankType" 
                                   name="type" 
                                   placeholder="e.g., nuban"
                                   value="nuban">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="country">
                                <i class="fas fa-globe"></i> Country
                            </label>
                            <input type="text" 
                                   id="country" 
                                   name="country" 
                                   value="Nigeria"
                                   placeholder="Country">
                        </div>
                        
                        <div class="form-group">
                            <label for="currency">
                                <i class="fas fa-money-bill-wave"></i> Currency
                            </label>
                            <input type="text" 
                                   id="currency" 
                                   name="currency" 
                                   value="NGN"
                                   placeholder="Currency">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="payWithBank">
                                <i class="fas fa-credit-card"></i> Pay with Bank
                            </label>
                            <select id="payWithBank" name="pay_with_bank">
                                <option value="1">Yes</option>
                                <option value="0" selected>No</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="supportsTransfer">
                                <i class="fas fa-exchange-alt"></i> Supports Transfer
                            </label>
                            <select id="supportsTransfer" name="supports_transfer">
                                <option value="1" selected>Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="availableForDirectDebit">
                                <i class="fas fa-bolt"></i> Available for Direct Debit
                            </label>
                            <select id="availableForDirectDebit" name="available_for_direct_debit">
                                <option value="1">Yes</option>
                                <option value="0" selected>No</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="bankStatus">
                                <i class="fas fa-toggle-on"></i> Status
                            </label>
                            <select id="bankStatus" name="is_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelBankBtn">Cancel</button>
                <button class="btn btn-primary" id="saveBankBtn">
                    <i class="fas fa-save"></i> Save Bank
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> Confirm Delete</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body text-center">
                <div class="warning-icon">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <h4>Are you sure?</h4>
                <p id="deleteMessage">This action cannot be undone.</p>
                <div id="banksToDelete" class="banks-to-delete"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelDeleteBtn">Cancel</button>
                <button class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <!-- View Bank Details Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> Bank Details</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="details-grid">
                    <div class="details-section">
                        <h4>Basic Information</h4>
                        <div class="detail-row">
                            <span class="detail-label">ID:</span>
                            <span class="detail-value" id="viewId"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Bank Code:</span>
                            <span class="detail-value" id="viewCode"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Bank Name:</span>
                            <span class="detail-value" id="viewName"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Longcode:</span>
                            <span class="detail-value" id="viewLongcode"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Slug:</span>
                            <span class="detail-value" id="viewSlug"></span>
                        </div>
                    </div>
                    
                    <div class="details-section">
                        <h4>Configuration</h4>
                        <div class="detail-row">
                            <span class="detail-label">Gateway:</span>
                            <span class="detail-value" id="viewGateway"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Type:</span>
                            <span class="detail-value" id="viewType"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Country:</span>
                            <span class="detail-value" id="viewCountry"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Currency:</span>
                            <span class="detail-value" id="viewCurrency"></span>
                        </div>
                    </div>
                    
                    <div class="details-section">
                        <h4>Features</h4>
                        <div class="detail-row">
                            <span class="detail-label">Pay with Bank:</span>
                            <span class="detail-value" id="viewPayWithBank"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Supports Transfer:</span>
                            <span class="detail-value" id="viewSupportsTransfer"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Direct Debit:</span>
                            <span class="detail-value" id="viewDirectDebit"></span>
                        </div>
                    </div>
                    
                    <div class="details-section">
                        <h4>Status & Timeline</h4>
                        <div class="detail-row">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value" id="viewStatus"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Created:</span>
                            <span class="detail-value" id="viewCreated"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Last Updated:</span>
                            <span class="detail-value" id="viewUpdated"></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="bankManager.closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Import Banks Modal (Optional) -->
    <div id="importModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-upload"></i> Import Banks</h3>
                <button class="close-modal" onclick="closeImportModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Upload a JSON file containing bank data from Paystack.</p>
                <form id="importForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="importFile">Select JSON File:</label>
                        <input type="file" id="importFile" accept=".json" required>
                    </div>
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="truncateExisting" checked>
                            <span>Truncate existing data before import</span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" id="updateExisting" checked>
                            <span>Update existing records</span>
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeImportModal()">Cancel</button>
                <button class="btn btn-primary" onclick="startImport()">
                    <i class="fas fa-upload"></i> Import
                </button>
            </div>
        </div>
    </div>

    <script src="scripts/bank.js"></script>
    <script>
        // Import modal functions (if needed)
        function closeImportModal() {
            document.getElementById('importModal').classList.remove('show');
        }
        
        function startImport() {
            window.location.href = 'bank_upload.html';
        }
    </script>
</body>
</html>