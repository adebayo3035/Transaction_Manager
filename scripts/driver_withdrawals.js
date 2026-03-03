class AdminWithdrawalManager {
  constructor() {
    this.apiUrl = "backend/driver_withdrawal_api.php";
    this.currentPage = 1;
    this.limit = 10;
    this.filters = {
      status: "",
      driver: "",
      from_date: "",
      to_date: "",
    };
    this.selectedWithdrawals = new Set();
    this.currentWithdrawalId = null;
    this.init();
  }

  async init() {
    this.showLoading();
    await this.loadSummary();
    await this.loadWithdrawals();
    this.setupEventListeners();
    this.setupModals();
    this.hideLoading();
    this.exportReport();
  }

  async loadSummary() {
    try {
      const response = await fetch(`${this.apiUrl}?action=summary`);
      const data = await response.json();

      if (data.success) {
        this.updateSummaryUI(data.summary);
      }
    } catch (error) {
      console.error("Error loading summary:", error);
    }
  }

  async loadWithdrawals() {
    const loadingRow = document.getElementById("loadingRow");
    const emptyState = document.getElementById("emptyState");
    const tableBody = document.getElementById("withdrawalsBody");

    loadingRow.style.display = "";
    emptyState.style.display = "none";

    Array.from(tableBody.children).forEach((row) => {
      if (row.id !== "loadingRow") {
        row.remove();
      }
    });

    try {
      const params = new URLSearchParams({
        action: "list",
        page: this.currentPage,
        limit: this.limit,
        ...this.filters,
      });

      const response = await fetch(`${this.apiUrl}?${params}`);
      const data = await response.json();

      if (data.success) {
        this.renderWithdrawals(data.withdrawals);
        this.renderPagination(data.pagination);

        if (data.withdrawals.length === 0) {
          loadingRow.style.display = "none";
          emptyState.style.display = "block";
        } else {
          loadingRow.style.display = "none";
        }

        this.updateTableInfo(data.pagination);
        this.updateSelectAll();
      }
    } catch (error) {
      console.error("Error loading withdrawals:", error);
      loadingRow.style.display = "none";
      emptyState.style.display = "block";
    }
  }

  async processWithdrawal() {
    // Get the stored withdrawal ID
    const withdrawalId = this.currentWithdrawalId;

    if (!withdrawalId) {
      this.showToast("error", "No withdrawal selected");
      return;
    }

    // Get form values
    const action = document.getElementById("processAction").value;
    const adminNotes = document.getElementById("adminNotes").value;
    const transactionRef = document.getElementById(
      "transactionReference",
    ).value;
    const rejectReason = document.getElementById("rejectReason").value;
    const failedReason = document.getElementById("failedReason").value;

    // Validate based on action
    if (action === "approve" && !transactionRef) {
      this.showToast("error", "Transaction reference is required for approval");
      return;
    }

    if (action === "reject" && !rejectReason) {
      this.showToast("error", "Rejection reason is required");
      return;
    }

    if (action === "mark_failed" && !failedReason) {
      this.showToast("error", "Failure reason is required");
      return;
    }

    // Prepare data
    const data = {
      admin_notes: adminNotes,
    };

    if (action === "approve") {
      data.transaction_ref = transactionRef;
    } else if (action === "reject") {
      data.reject_reason = rejectReason;
    } else if (action === "mark_failed") {
      data.failed_reason = failedReason;
    }

    // Disable button and show loading
    const processBtn = document.getElementById("processBtn");
    processBtn.disabled = true;
    processBtn.innerHTML =
      '<i class="fas fa-spinner fa-spin"></i> Processing...';

    try {
      const response = await fetch(this.apiUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "process",
          withdrawal_id: withdrawalId,
          process_action: action,
          ...data,
        }),
      });

      const result = await response.json();

      if (result.success) {
        this.closeProcessModal();
        this.currentWithdrawalId = null; // Clear the stored ID
        this.showSuccess("Withdrawal processed successfully!");
        await this.loadSummary();
        await this.loadWithdrawals();
      } else {
        this.showToast(
          "error",
          result.message || "Failed to process withdrawal",
        );
      }
    } catch (error) {
      console.error("Error processing withdrawal:", error);
      this.showToast("error", "Error processing withdrawal");
    } finally {
      processBtn.disabled = false;
      processBtn.innerHTML = '<i class="fas fa-check"></i> Process';
    }
  }

  async processBulkAction() {
    // Get the action from the correct element
    const actionSelect = document.getElementById("bulkActionType");
    if (!actionSelect) {
      this.showToast("error", "Bulk action form not found");
      return;
    }

    const action = actionSelect.value;
    const notes = document.getElementById("bulkAdminNotes")?.value || "";
    const withdrawalIds = Array.from(this.selectedWithdrawals);

    if (withdrawalIds.length === 0) {
      this.showToast("error", "No withdrawals selected");
      return;
    }

    // Validate based on action
    if (action === "reject") {
      const rejectReason = document.getElementById("bulkRejectReason")?.value;
      if (!rejectReason) {
        this.showToast("error", "Rejection reason is required");
        return;
      }
    }

    if (action === "mark_failed") {
      const failedReason = document.getElementById("bulkFailedReason")?.value;
      if (!failedReason) {
        this.showToast("error", "Failure reason is required");
        return;
      }
    }

    // Prepare data with all fields
    const data = {
      action: "bulk_process",
      withdrawal_ids: withdrawalIds,
      process_action: action,
      admin_notes: notes,
    };

    // Add action-specific fields
    if (action === "approve") {
      const transactionRef =
        document.getElementById("bulkTransactionRef")?.value;
      if (transactionRef) {
        data.transaction_ref = transactionRef;
      }
    } else if (action === "reject") {
      data.reject_reason = document.getElementById("bulkRejectReason")?.value;
    } else if (action === "mark_failed") {
      data.failed_reason = document.getElementById("bulkFailedReason")?.value;
    }

    // Disable button and show loading
    const processBtn = document.getElementById("bulkProcessBtn");
    if (processBtn) {
      processBtn.disabled = true;
      processBtn.innerHTML =
        '<i class="fas fa-spinner fa-spin"></i> Processing...';
    }

    try {
      const response = await fetch(this.apiUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });

      const result = await response.json();

      if (result.success) {
        this.closeBulkModal();
        this.selectedWithdrawals.clear();
        this.clearSelection(); // This will hide the bulk bar

        const message =
          result.message ||
          `Successfully processed ${result.processed} withdrawals`;
        this.showSuccess(message);

        if (result.failed > 0) {
          this.showToast(
            "warning",
            `${result.failed} withdrawals failed to process`,
          );
        }

        await this.loadSummary();
        await this.loadWithdrawals();
      } else {
        this.showToast(
          "error",
          result.message || "Failed to process withdrawals",
        );
      }
    } catch (error) {
      console.error("Error in bulk process:", error);
      this.showToast("error", "Error processing withdrawals");
    } finally {
      if (processBtn) {
        processBtn.disabled = false;
        processBtn.innerHTML = '<i class="fas fa-check"></i> Process Selected';
      }
    }
  }

  // Alternative version with animation
  clearSelection() {
    console.log("Clearing all selections");

    // Uncheck all checkboxes
    document.querySelectorAll(".withdrawal-checkbox").forEach((checkbox) => {
      checkbox.checked = false;
    });

    // Uncheck select all
    const selectAll = document.getElementById("selectAll");
    if (selectAll) {
      selectAll.checked = false;
      selectAll.indeterminate = false;
    }

    // Clear the Set
    this.selectedWithdrawals.clear();

    // Animate the bulk bar hiding
    const bulkBar = document.getElementById("bulkActionsBar");
    if (bulkBar) {
      // Add a fade-out animation
      bulkBar.style.transition = "opacity 0.3s ease";
      bulkBar.style.opacity = "0";

      setTimeout(() => {
        bulkBar.style.display = "none";
        bulkBar.style.opacity = "1"; // Reset for next time
      }, 300);
    }

    // Reset count
    const selectedCount = document.getElementById("selectedCount");
    if (selectedCount) {
      selectedCount.textContent = "0";
    }
  }
  async viewDetails(withdrawalId) {
    try {
      // Show loading state in the modal
      const detailsContent = document.getElementById("detailsContent");
      detailsContent.innerHTML = `
            <div class="loading-spinner" style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin fa-3x"></i>
                <p style="margin-top: 15px;">Loading withdrawal details...</p>
            </div>
        `;

      // Show the modal immediately with loading state
      this.showModal("detailsModal");

      // Fetch withdrawal details
      const response = await fetch(
        `${this.apiUrl}?action=details&withdrawal_id=${withdrawalId}`,
      );
      const data = await response.json();

      if (data.success && data.withdrawal) {
        this.displayWithdrawalDetails(data.withdrawal);
      } else {
        // Show error in modal
        detailsContent.innerHTML = `
                <div class="error-state" style="text-align: center; padding: 40px; color: #dc3545;">
                    <i class="fas fa-exclamation-circle fa-3x"></i>
                    <p style="margin-top: 15px;">${data.message || "Failed to load withdrawal details"}</p>
                    <button class="btn btn-secondary" style="margin-top: 20px;" onclick="adminManager.closeDetailsModal()">
                        Close
                    </button>
                </div>
            `;
      }
    } catch (error) {
      console.error("Error loading withdrawal details:", error);
      document.getElementById("detailsContent").innerHTML = `
            <div class="error-state" style="text-align: center; padding: 40px; color: #dc3545;">
                <i class="fas fa-exclamation-circle fa-3x"></i>
                <p style="margin-top: 15px;">Error loading withdrawal details. Please try again.</p>
                <button class="btn btn-secondary" style="margin-top: 20px;" onclick="adminManager.closeDetailsModal()">
                    Close
                </button>
            </div>
        `;
    }
  }

  displayWithdrawalDetails(withdrawal) {
    const detailsContent = document.getElementById("detailsContent");

    // Format dates if not already formatted
    const createdDate =
      withdrawal.created_at_formatted ||
      new Date(withdrawal.created_at).toLocaleString();
    const processedDate =
      withdrawal.processed_at_formatted ||
      (withdrawal.processed_at
        ? new Date(withdrawal.processed_at).toLocaleString()
        : null);

    // Determine status badge class
    const statusClass = this.getStatusClass(withdrawal.status);

    detailsContent.innerHTML = `
        <div class="detail-section">
            <h4><i class="fas fa-receipt"></i> Transaction Information</h4>
            <div class="detail-row">
                <span class="detail-label">Reference:</span>
                <span class="detail-value reference">${withdrawal.reference}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Amount:</span>
                <span class="detail-value amount">${withdrawal.formatted_amount}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status:</span>
                <span class="detail-value">
                    <span class="status-badge ${statusClass}">${withdrawal.status_text || withdrawal.status}</span>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Driver Note:</span>
                <span class="detail-value">${withdrawal.note || "No note provided"}</span>
            </div>
        </div>
        
        <div class="detail-section">
            <h4><i class="fas fa-user"></i> Driver Information</h4>
            <div class="detail-row">
                <span class="detail-label">Name:</span>
                <span class="detail-value">${withdrawal.driver_name}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email:</span>
                <span class="detail-value">${withdrawal.driver_email}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Phone:</span>
                <span class="detail-value">${withdrawal.driver_phone || "N/A"}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Wallet Balance:</span>
                <span class="detail-value">${withdrawal.driver_wallet_formatted || "₦0.00"}</span>
            </div>
        </div>
        
        <div class="detail-section">
            <h4><i class="fas fa-university"></i> Bank Details</h4>
            <div class="detail-row">
                <span class="detail-label">Bank:</span>
                <span class="detail-value">${withdrawal.bank_name}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Account Number:</span>
                <span class="detail-value">${withdrawal.account_number}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Account Name:</span>
                <span class="detail-value">${withdrawal.account_name}</span>
            </div>
        </div>
        
        <div class="detail-section">
            <h4><i class="fas fa-clock"></i> Timeline</h4>
            <div class="detail-row">
                <span class="detail-label">Requested:</span>
                <span class="detail-value">${createdDate}</span>
            </div>
            ${
              processedDate
                ? `
            <div class="detail-row">
                <span class="detail-label">Processed:</span>
                <span class="detail-value">${processedDate}</span>
            </div>
            `
                : ""
            }
            ${
              withdrawal.processed_by_name
                ? `
            <div class="detail-row">
                <span class="detail-label">Processed By:</span>
                <span class="detail-value">${withdrawal.processed_by_name}</span>
            </div>
            `
                : ""
            }
        </div>
        
        ${
          withdrawal.admin_notes
            ? `
        <div class="detail-section">
            <h4><i class="fas fa-sticky-note"></i> Admin Notes</h4>
            <div class="admin-notes">
                ${withdrawal.admin_notes.replace(/\n/g, "<br>")}
            </div>
        </div>
        `
            : ""
        }
        
        ${
          withdrawal.status === "pending"
            ? `
        <div class="detail-section action-section">
            <h4><i class="fas fa-tasks"></i> Actions</h4>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button class="btn btn-primary" onclick="adminManager.showProcessModal(${withdrawal.id})">
                    <i class="fas fa-check-circle"></i> Process Now
                </button>
            </div>
        </div>
        `
            : ""
        }
    `;
  }

  // Make sure you also have the closeDetailsModal method
  closeDetailsModal() {
    this.hideModal("detailsModal");
  }

  // Also add this helper method if not present
  getStatusClass(status) {
    const classes = {
      pending: "status-pending",
      processing: "status-processing",
      completed: "status-completed",
      failed: "status-failed",
      cancelled: "status-cancelled",
    };
    return classes[status] || "status-pending";
  }

  renderWithdrawals(withdrawals) {
    const tableBody = document.getElementById("withdrawalsBody");

    withdrawals.forEach((w) => {
      const row = document.createElement("tr");
      row.dataset.id = w.id;

      const statusClass = this.getStatusClass(w.status);

      // Define which statuses can have checkboxes (editable)
      const editableStatuses = ["pending", "processing"];
      const isEditable = editableStatuses.includes(w.status);

      row.innerHTML = `
                <td>
                    ${
                      isEditable
                        ? `<input type="checkbox" class="withdrawal-checkbox" value="${w.id}">`
                        : ""
                    }
                </td>
                <td>
                    <span class="reference">${w.reference}</span>
                </td>
                <td>
                    <span class="driver-name">${w.driver_name}</span>
                    <span class="driver-email">${w.driver_email}</span>
                    
                </td>
                <td>
                    <span class="date">${w.date_formatted}</span>
                    <span class="time">${w.time_formatted}</span>
                </td>
                <td>
                    <span class="amount">${w.formatted_amount}</span>
                </td>
                <td>
                    <span class="bank">${w.bank_name}</span>
                    <span class="account">${w.masked_account}</span>
                    <span class="account-name">${w.account_name}</span>
                </td>
                <td>
                    <span class="status-badge status-${w.status}">${w.status_text}</span>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-icon view" onclick="adminManager.viewDetails(${w.id})" 
                                title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${
                          w.status === "pending"
                            ? `
                            <button class="btn-icon process" onclick="adminManager.showProcessModal(${w.id})" 
                                    title="Process">
                                <i class="fas fa-check-circle"></i>
                            </button>
                        `
                            : ""
                        }
                        ${
                          w.status === "processing"
                            ? `
                            <button class="btn-icon complete" onclick="adminManager.showProcessModal(${w.id})" 
                                    title="Complete">
                                <i class="fas fa-check-double"></i>
                            </button>
                        `
                            : ""
                        }
                    </div>
                </td>
            `;

      tableBody.appendChild(row);
    });

    this.setupCheckboxListeners();
  }
  renderPagination(pagination) {
    const container = document.getElementById("pagination");

    if (pagination.total_pages <= 1) {
      container.innerHTML = "";
      return;
    }

    let buttons = "";

    if (this.currentPage > 1) {
      buttons += `
                <button class="page-btn" data-page="${this.currentPage - 1}">
                    <i class="fas fa-chevron-left"></i>
                </button>
            `;
    }

    const maxVisible = 5;
    let startPage = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(pagination.total_pages, startPage + maxVisible - 1);

    if (endPage - startPage + 1 < maxVisible) {
      startPage = Math.max(1, endPage - maxVisible + 1);
    }

    for (let i = startPage; i <= endPage; i++) {
      buttons += `
                <button class="page-btn ${i === this.currentPage ? "active" : ""}" 
                        data-page="${i}">
                    ${i}
                </button>
            `;
    }

    if (this.currentPage < pagination.total_pages) {
      buttons += `
                <button class="page-btn" data-page="${this.currentPage + 1}">
                    <i class="fas fa-chevron-right"></i>
                </button>
            `;
    }

    container.innerHTML = buttons;

    document.querySelectorAll(".page-btn[data-page]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const page = parseInt(btn.dataset.page);
        if (page !== this.currentPage) {
          this.currentPage = page;
          this.loadWithdrawals();
          window.scrollTo({ top: 0, behavior: "smooth" });
        }
      });
    });
  }

  setupCheckboxListeners() {
    // Only set up listeners on checkboxes that actually exist
    document.querySelectorAll(".withdrawal-checkbox").forEach((checkbox) => {
      checkbox.addEventListener("change", (e) => {
        const id = e.target.value;

        if (e.target.checked) {
          this.selectedWithdrawals.add(id);
        } else {
          this.selectedWithdrawals.delete(id);
        }

        this.updateSelectAll();
        this.updateBulkActionsBar();
      });
    });
  }
  updateSelectAll() {
    const selectAll = document.getElementById("selectAll");
    const checkboxes = document.querySelectorAll(".withdrawal-checkbox");

    // If there are no editable withdrawals, hide or disable select all
    if (checkboxes.length === 0) {
      if (selectAll) {
        selectAll.checked = false;
        selectAll.disabled = true;
        selectAll.indeterminate = false;
      }
      return;
    }

    if (selectAll) {
      selectAll.disabled = false;
      selectAll.checked =
        checkboxes.length > 0 &&
        Array.from(checkboxes).every((cb) => cb.checked);

      selectAll.indeterminate =
        Array.from(checkboxes).some((cb) => cb.checked) &&
        !Array.from(checkboxes).every((cb) => cb.checked);
    }
  }

  // Add this to your updateBulkActionsBar method
  updateBulkActionsBar() {
    const bulkBar = document.getElementById("bulkActionsBar");
    const selectedCount = document.getElementById("selectedCount");

    console.log("updateBulkActionsBar called"); // Debug
    console.log("bulkBar exists:", !!bulkBar);
    console.log("selectedCount exists:", !!selectedCount);

    if (!bulkBar || !selectedCount) {
      console.error("Bulk bar elements not found in DOM!");
      return;
    }

    const count = this.selectedWithdrawals.size;
    console.log("Selected count:", count);

    if (count > 0) {
      selectedCount.textContent = count;
      bulkBar.style.display = "flex";
      console.log("Bulk bar should now be visible");
    } else {
      bulkBar.style.display = "none";
      console.log("Bulk bar hidden");
    }
  }

  updateBulkButton() {
    const count = this.selectedWithdrawals.size;
    // You can show/hide a bulk actions button here
  }

  updateSummaryUI(summary) {
    document.getElementById("pendingCount").textContent = summary.pending.count;
    document.getElementById("pendingAmount").textContent =
      summary.pending.formatted_amount;

    document.getElementById("processingCount").textContent =
      summary.processing.count;
    document.getElementById("processingAmount").textContent =
      summary.processing.formatted_amount;

    document.getElementById("completedToday").textContent =
      summary.completed_today.count;
    document.getElementById("completedAmount").textContent =
      summary.completed_today.formatted_amount;

    document.getElementById("totalProcessed").textContent = summary.total.count;
    document.getElementById("totalAmount").textContent =
      summary.total.formatted_amount;
  }

  updateTableInfo(pagination) {
    const start = (pagination.page - 1) * pagination.limit + 1;
    const end = Math.min(pagination.page * pagination.limit, pagination.total);
    document.getElementById("showingInfo").textContent =
      `Showing ${start} to ${end} of ${pagination.total} entries`;
  }

  getStatusClass(status) {
    const classes = {
      pending: "status-pending",
      processing: "status-processing",
      completed: "status-completed",
      failed: "status-failed",
      cancelled: "status-cancelled",
    };
    return classes[status] || "";
  }

  showProcessModal(withdrawalId) {
    this.currentWithdrawalId = withdrawalId; // Store the ID
    this.loadWithdrawalDetails(withdrawalId, "process"); //load withdrawal details
  }

  async loadWithdrawalDetails(withdrawalId, mode = "view") {
    try {
      const response = await fetch(
        `${this.apiUrl}?action=details&withdrawal_id=${withdrawalId}`,
      );
      const data = await response.json();

      if (data.success) {
        if (mode === "view") {
          this.showDetailsModal(data.withdrawal);
        } else {
          this.showProcessModalWithData(data.withdrawal);
        }
      }
    } catch (error) {
      console.error("Error loading details:", error);
    }
  }

  showProcessModalWithData(withdrawal) {
    this.currentWithdrawalId = withdrawal.id; // Store the ID
    const details = document.getElementById("processDetails");

    details.innerHTML = `
        <div class="info-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <div class="info-row" style="display: flex; justify-content: space-between; padding: 5px 0;">
                <span><strong>Reference:</strong></span>
                <span>${withdrawal.reference}</span>
            </div>
            <div class="info-row" style="display: flex; justify-content: space-between; padding: 5px 0;">
                <span><strong>Driver:</strong></span>
                <span>${withdrawal.driver_name}</span>
            </div>
            <div class="info-row" style="display: flex; justify-content: space-between; padding: 5px 0;">
                <span><strong>Amount:</strong></span>
                <span style="color: #28a745; font-weight: bold;">${withdrawal.formatted_amount}</span>
            </div>
            <div class="info-row" style="display: flex; justify-content: space-between; padding: 5px 0;">
                <span><strong>Bank:</strong></span>
                <span>${withdrawal.bank_name}</span>
            </div>
            <div class="info-row" style="display: flex; justify-content: space-between; padding: 5px 0;">
                <span><strong>Account:</strong></span>
                <span>${withdrawal.masked_account || "****" + withdrawal.account_number.slice(-4)}</span>
            </div>
        </div>
    `;

    // Reset form fields
    document.getElementById("processAction").value = "approve";
    document.getElementById("transactionReference").value = "";
    document.getElementById("rejectReason").value = "";
    document.getElementById("failedReason").value = "";
    document.getElementById("adminNotes").value = "";

    // Show appropriate fields
    toggleProcessFields();

    this.showModal("processModal");
    this.hideModal("detailsModal");
  }
  showDetailsModal(withdrawal) {
    const detailsContent = document.getElementById("detailsContent");

    detailsContent.innerHTML = `
            <div class="detail-section">
                <h4>Transaction Information</h4>
                <div class="detail-row">
                    <span class="detail-label">Reference:</span>
                    <span class="detail-value">${withdrawal.reference}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Amount:</span>
                    <span class="detail-value amount">${withdrawal.formatted_amount}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value">
                        <span class="status-badge status-${withdrawal.status}">${withdrawal.status_text}</span>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Driver Note:</span>
                    <span class="detail-value">${withdrawal.note || "None"}</span>
                </div>
            </div>
            
            <div class="detail-section">
                <h4>Driver Information</h4>
                <div class="detail-row">
                    <span class="detail-label">Name:</span>
                    <span class="detail-value">${withdrawal.driver_name}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value">${withdrawal.driver_email}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Phone:</span>
                    <span class="detail-value">${withdrawal.driver_phone}</span>
                </div>
            </div>
            
            <div class="detail-section">
                <h4>Bank Details</h4>
                <div class="detail-row">
                    <span class="detail-label">Bank:</span>
                    <span class="detail-value">${withdrawal.bank_name}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Account Number:</span>
                    <span class="detail-value">${withdrawal.account_number}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Account Name:</span>
                    <span class="detail-value">${withdrawal.account_name}</span>
                </div>
            </div>
            
            <div class="detail-section">
                <h4>Timeline</h4>
                <div class="detail-row">
                    <span class="detail-label">Requested:</span>
                    <span class="detail-value">${withdrawal.created_at_formatted}</span>
                </div>
                ${
                  withdrawal.processed_at_formatted
                    ? `
                <div class="detail-row">
                    <span class="detail-label">Processed:</span>
                    <span class="detail-value">${withdrawal.processed_at_formatted}</span>
                </div>
                `
                    : ""
                }
            </div>
            
            ${
              withdrawal.admin_notes
                ? `
            <div class="detail-section">
                <h4>Admin Notes</h4>
                <div class="admin-notes">${withdrawal.admin_notes}</div>
            </div>
            `
                : ""
            }
        `;

    this.showModal("detailsModal");
  }

  viewDriver(driverId) {
    // Load and show driver details
    this.showModal("driverModal");
  }

  // Add this method to your AdminWithdrawalManager class
  showBulkModal(action) {
    console.log("showBulkModal called with action:", action); // Debug log

    const count = this.selectedWithdrawals.size;

    if (count === 0) {
      this.showToast("error", "No withdrawals selected");
      return;
    }

    this.currentBulkAction = action;

    // Update modal title
    const titles = {
      approve: "Approve Withdrawals",
      reject: "Reject Withdrawals",
      mark_failed: "Mark as Failed",
    };

    const modalTitle = document.getElementById("bulkModalTitle");
    if (modalTitle) {
      modalTitle.innerHTML = `<i class="fas fa-tasks"></i> ${titles[action]}`;
    }

    // Update count
    const bulkCount = document.getElementById("bulkCount");
    if (bulkCount) {
      bulkCount.textContent = count;
    }

    // Show/hide appropriate fields
    const actionSelect = document.getElementById("bulkActionType");
    if (actionSelect) {
      actionSelect.value = action;
    }

    // Handle field visibility
    document.getElementById("bulkApproveFields").style.display =
      action === "approve" ? "block" : "none";
    document.getElementById("bulkRejectFields").style.display =
      action === "reject" ? "block" : "none";
    document.getElementById("bulkFailedFields").style.display =
      action === "mark_failed" ? "block" : "none";

    // Clear previous values
    const transactionRef = document.getElementById("bulkTransactionRef");
    const rejectReason = document.getElementById("bulkRejectReason");
    const failedReason = document.getElementById("bulkFailedReason");
    const adminNotes = document.getElementById("bulkAdminNotes");

    if (transactionRef) transactionRef.value = "";
    if (rejectReason) rejectReason.value = "";
    if (failedReason) failedReason.value = "";
    if (adminNotes) adminNotes.value = "";

    // Show the modal
    this.showModal("bulkModal");
  }

  toggleProcessFields() {
    const action = document.getElementById("processAction").value;

    document.getElementById("approveFields").style.display =
      action === "approve" ? "block" : "none";
    document.getElementById("rejectFields").style.display =
      action === "reject" ? "block" : "none";
    document.getElementById("failedFields").style.display =
      action === "mark_failed" ? "block" : "none";
  }

  setActiveFilter(type, value) {
    // Remove active class from all action buttons
    document.querySelectorAll(".action-btn").forEach((btn) => {
      btn.classList.remove("active");
    });

    // Add active class to the clicked button
    if (type === "status") {
      const buttons = document.querySelectorAll(
        `.action-btn[onclick*="filterByStatus('${value}')"]`,
      );
      buttons.forEach((btn) => btn.classList.add("active"));
    } else if (type === "date") {
      const buttons = document.querySelectorAll(
        `.action-btn[onclick*="filterByDate('${value}')"]`,
      );
      buttons.forEach((btn) => btn.classList.add("active"));
    }
  }

  // Update your filter methods to set active state
  filterByStatus(status) {
    this.filters.status = status;
    this.filters.from_date = "";
    this.filters.to_date = "";
    this.currentPage = 1;
    this.setActiveFilter("status", status);
    this.loadWithdrawals();
  }

  filterByDate(period) {
    const today = new Date();
    let fromDate = new Date();

    if (period === "today") {
      // Today
      this.setActiveFilter("date", "today");
    } else if (period === "week") {
      fromDate.setDate(today.getDate() - 7);
      this.setActiveFilter("date", "week");
    } else if (period === "month") {
      fromDate.setMonth(today.getMonth() - 1);
      this.setActiveFilter("date", "month");
    }

    this.filters.from_date = fromDate.toISOString().split("T")[0];
    this.filters.to_date = today.toISOString().split("T")[0];
    this.filters.status = "";

    document.getElementById("dateFrom").value = this.filters.from_date;
    document.getElementById("dateTo").value = this.filters.to_date;

    this.currentPage = 1;
    this.loadWithdrawals();
  }
  setupEventListeners() {
    document.getElementById("refreshBtn").addEventListener("click", () => {
      this.loadSummary();
      this.loadWithdrawals();
    });

    document.getElementById("applyFiltersBtn").addEventListener("click", () => {
      this.filters = {
        status: document.getElementById("statusFilter").value,
        driver: document.getElementById("driverSearch").value,
        from_date: document.getElementById("dateFrom").value,
        to_date: document.getElementById("dateTo").value,
      };
      this.currentPage = 1;
      this.loadWithdrawals();
    });

    document.getElementById("clearFiltersBtn").addEventListener("click", () => {
      document.getElementById("statusFilter").value = "";
      document.getElementById("driverSearch").value = "";
      document.getElementById("dateFrom").value = "";
      document.getElementById("dateTo").value = "";
      this.filters = { status: "", driver: "", from_date: "", to_date: "" };
      this.currentPage = 1;
      this.loadWithdrawals();
    });

    document.getElementById("selectAll").addEventListener("change", (e) => {
      document.querySelectorAll(".withdrawal-checkbox").forEach((cb) => {
        cb.checked = e.target.checked;
        if (e.target.checked) {
          this.selectedWithdrawals.add(cb.value);
        } else {
          this.selectedWithdrawals.delete(cb.value);
        }
      });
    });

    document.getElementById("exportBtn").addEventListener("click", () => {
      this.exportReport();
    });
  }

  setupModals() {
    const modals = [
      "processModal",
      "detailsModal",
      "driverModal",
      "bulkModal",
      "successModal",
    ];
    modals.forEach((modalId) => {
      const modal = document.getElementById(modalId);
      modal.addEventListener("click", (e) => {
        if (e.target === modal) {
          this.hideModal(modalId);
        }
      });
    });
  }

  showModal(modalId) {
    document.getElementById(modalId).classList.add("show");
    document.body.style.overflow = "hidden";
  }

  hideModal(modalId) {
    document.getElementById(modalId).classList.remove("show");
    document.body.style.overflow = "";
  }

  closeProcessModal() {
    this.hideModal("processModal");
  }
  closeDetailsModal() {
    this.hideModal("detailsModal");
  }
  closeDriverModal() {
    this.hideModal("driverModal");
  }
  closeBulkModal() {
    this.hideModal("bulkModal");
  }
  closeSuccessModal() {
    this.hideModal("successModal");
  }

  showSuccess(message) {
    document.getElementById("successMessage").textContent = message;
    this.showModal("successModal");
  }

  showToast(type, message) {
    const container = document.getElementById("toastContainer");
    const toast = document.createElement("div");
    toast.className = `toast toast-${type}`;

    const icons = {
      success: "check-circle",
      error: "exclamation-circle",
      warning: "exclamation-triangle",
      info: "info-circle",
    };

    toast.innerHTML = `
            <i class="fas fa-${icons[type]}"></i>
            <span>${message}</span>
        `;

    container.appendChild(toast);
    setTimeout(() => toast.classList.add("show"), 10);
    setTimeout(() => {
      toast.classList.remove("show");
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }

  showLoading() {
    document.getElementById("loadingRow").style.display = "";
  }

  hideLoading() {}

  // Export function
  /**
   * Export withdrawals report based on current filters and selection
   * @param {string} format - export format: 'csv', 'excel', 'pdf', 'print'
   */
  async exportReport(format = "csv") {
    try {
      // Show loading state
      this.showExportLoading(true);

      // Get current filters and selection
      const selectedIds = this.getSelectedWithdrawalIds();
      const exportType = selectedIds.length > 0 ? "selected" : "filtered";

      // Prepare export parameters
      const params = new URLSearchParams({
        action: "export",
        format: format,
        export_type: exportType,
        page: this.currentPage,
        limit: this.limit,
        ...this.filters,
      });

      // Add selected IDs if any
      if (selectedIds.length > 0) {
        params.append("ids", selectedIds.join(","));
      }

      // Get date range for filename
      const dateStr = new Date().toISOString().slice(0, 10);
      const filename = `withdrawals_${dateStr}_${Date.now()}`;

      // Handle different export formats
      switch (format) {
        case "csv":
          await this.exportToCSV(params, filename);
          break;
        case "excel":
          await this.exportToExcel(params, filename);
          break;
        case "pdf":
          await this.exportToPDF(params, filename);
          break;
        case "print":
          await this.printReport();
          break;
        default:
          await this.exportToCSV(params, filename);
      }

      // Log export action
      this.logExportAction(format, selectedIds.length, exportType);
    } catch (error) {
      console.error("Export failed:", error);
      this.showExportError("Failed to export report. Please try again.");
    } finally {
      this.showExportLoading(false);
    }
  }

  /**
   * Export to CSV format
   */
  async exportToCSV(params, filename) {
    try {
      const response = await fetch(`${this.apiUrl}?${params.toString()}`);
      const data = await response.json();

      if (!data.success || !data.data) {
        throw new Error(data.message || "Failed to fetch export data");
      }

      // Convert data to CSV
      const csv = this.convertToCSV(data.data);

      // Create and download CSV file
      const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
      const link = document.createElement("a");

      if (navigator.msSaveBlob) {
        // IE 10+
        navigator.msSaveBlob(blob, `${filename}.csv`);
      } else {
        link.href = URL.createObjectURL(blob);
        link.download = `${filename}.csv`;
        link.style.display = "none";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
      }

      this.showExportSuccess("CSV file downloaded successfully");
    } catch (error) {
      console.error("CSV export error:", error);
      throw error;
    }
  }

  /**
   * Convert data to CSV format
   */
  convertToCSV(data) {
    if (!data || !data.length) return "";

    // Define headers
    const headers = [
      "Reference",
      "Driver Name",
      "Driver Email",
      "Date",
      "Time",
      "Amount",
      "Bank",
      "Account Number",
      "Account Name",
      "Status",
    ];

    // Create CSV rows
    const rows = data.map((item) => {
      return [
        this.escapeCSV(item.reference || ""),
        this.escapeCSV(item.driver_name || ""),
        this.escapeCSV(item.driver_email || ""),
        this.escapeCSV(item.date_formatted || ""),
        this.escapeCSV(item.time_formatted || ""),
        this.escapeCSV(item.amount || ""),
        this.escapeCSV(item.bank_name || ""),
        this.escapeCSV(item.masked_account || ""),
        this.escapeCSV(item.account_name || ""),
        this.escapeCSV(item.status_text || ""),
      ].join(",");
    });

    return [headers.join(","), ...rows].join("\n");
  }

  /**
   * Escape CSV field (handle commas, quotes, newlines)
   */
  escapeCSV(field) {
    if (field === null || field === undefined) return "";

    const stringField = String(field);

    // If field contains comma, quote, or newline, wrap in quotes
    if (
      stringField.includes(",") ||
      stringField.includes('"') ||
      stringField.includes("\n") ||
      stringField.includes("\r")
    ) {
      return `"${stringField.replace(/"/g, '""')}"`;
    }

    return stringField;
  }

  /**
   * Export to Excel format
   */
  async exportToExcel(params, filename) {
    try {
      // For Excel, we can use the same CSV approach but with .xlsx extension
      // Or use a library like SheetJS (xlsx)

      // Option 1: Simple CSV renamed to .xlsx
      await this.exportToCSV(params, filename);

      // Option 2: Use SheetJS for proper Excel format (if available)
      if (typeof XLSX !== "undefined") {
        const response = await fetch(`${this.apiUrl}?${params.toString()}`);
        const data = await response.json();

        if (!data.success || !data.data) {
          throw new Error(data.message || "Failed to fetch export data");
        }

        // Create worksheet
        const wsData = [
          [
            "Reference",
            "Driver Name",
            "Driver Email",
            "Date",
            "Time",
            "Amount",
            "Bank",
            "Account Number",
            "Account Name",
            "Status",
          ],
          ...data.data.map((item) => [
            item.reference,
            item.driver_firstname + " " + item.driver_lastname,
            item.driver_email,
            item.date_formatted,
            item.time_formatted,
            item.amount,
            item.bank_name,
            item.masked_account,
            item.account_name,
            item.status_text,
          ]),
        ];

        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.aoa_to_sheet(wsData);

        // Style the header
        ws["!cols"] = [
          { wch: 20 }, // Reference
          { wch: 20 }, // Driver Name
          { wch: 25 }, // Driver Email
          { wch: 12 }, // Date
          { wch: 10 }, // Time
          { wch: 15 }, // Amount
          { wch: 15 }, // Bank
          { wch: 15 }, // Account Number
          { wch: 20 }, // Account Name
          { wch: 12 }, // Status
        ];

        XLSX.utils.book_append_sheet(wb, ws, "Withdrawals");
        XLSX.writeFile(wb, `${filename}.xlsx`);

        this.showExportSuccess("Excel file downloaded successfully");
      }
    } catch (error) {
      console.error("Excel export error:", error);
      throw error;
    }
  }

  /**
   * Export to PDF format
   */
  async exportToPDF(params, filename) {
    try {
      const response = await fetch(`${this.apiUrl}?${params.toString()}`);
      const data = await response.json();

      if (!data.success || !data.data) {
        throw new Error(data.message || "Failed to fetch export data");
      }

      // Check if jsPDF is available
      if (typeof jspdf === "undefined") {
        // Fallback to print
        await this.printReport();
        return;
      }

      const { jsPDF } = window.jspdf;
      const doc = new jsPDF({
        orientation: "landscape",
        unit: "mm",
        format: "a4",
      });

      // Add title
      doc.setFontSize(16);
      doc.setTextColor(40);
      doc.text("Withdrawals Report", 14, 15);

      // Add metadata
      doc.setFontSize(10);
      doc.setTextColor(100);

      const metadata = [
        `Generated: ${new Date().toLocaleString()}`,
        `Status Filter: ${this.filters.status || "All"}`,
        `Date Range: ${this.filters.date_from || "Any"} to ${this.filters.date_to || "Any"}`,
        `Total Records: ${data.data.length}`,
      ];

      let yPos = 25;
      metadata.forEach((line) => {
        if (line) {
          doc.text(line, 14, yPos);
          yPos += 5;
        }
      });

      yPos += 5;

      // Prepare table data
      const tableData = data.data.map((item) => [
        item.reference || "",
        item.driver_firstname + " " + item.driver_lastname || "",
        item.driver_email || "",
        item.date_formatted || "",
        item.time_formatted || "",
        item.amount || "",
        item.bank_name || "",
        item.status_text || "",
      ]);

      // Add table
      doc.autoTable({
        head: [
          [
            "Reference",
            "Driver's Name",
            "Email",
            "Date",
            "Time",
            "Amount",
            "Bank",
            "Status",
          ],
        ],
        body: tableData,
        startY: yPos,
        theme: "grid",
        styles: {
          fontSize: 8,
          cellPadding: 2,
          overflow: "linebreak",
        },
        headStyles: {
          fillColor: [41, 128, 185],
          textColor: 255,
          fontStyle: "bold",
        },
        alternateRowStyles: {
          fillColor: [245, 245, 245],
        },
        didDrawPage: (data) => {
          // Footer with page number
          doc.setFontSize(8);
          doc.setTextColor(150);
          doc.text(
            `Page ${data.pageNumber} of ${data.pageCount}`,
            data.settings.margin.left,
            doc.internal.pageSize.height - 10,
          );
        },
      });

      // Save PDF
      doc.save(`${filename}.pdf`);

      this.showExportSuccess("PDF file downloaded successfully");
    } catch (error) {
      console.error("PDF export error:", error);
      throw error;
    }
  }

  /**
   * Print report
   */
  async printReport() {
    try {
      const printWindow = window.open("", "_blank");

      // Get current table HTML
      const tableHTML = document.querySelector(".withdrawals-table").outerHTML;
      const filtersHTML = this.getFiltersHTML();

      const html = `
            <!DOCTYPE html>
            <html>
            <head>
                <title>Withdrawals Report</title>
                <style>
                    @media print {
                        body {
                            font-family: Arial, sans-serif;
                            margin: 15mm;
                        }
                        h1 {
                            color: #333;
                            text-align: center;
                            margin-bottom: 20px;
                        }
                        .filters {
                            margin-bottom: 20px;
                            padding: 10px;
                            background: #f5f5f5;
                            border-radius: 5px;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-top: 20px;
                        }
                        th {
                            background-color: #3498db;
                            color: white;
                            padding: 8px;
                            text-align: left;
                        }
                        td {
                            padding: 6px;
                            border: 1px solid #ddd;
                        }
                        tr:nth-child(even) {
                            background-color: #f9f9f9;
                        }
                        .footer {
                            margin-top: 20px;
                            text-align: center;
                            color: #666;
                            font-size: 12px;
                        }
                        .status-badge {
                            padding: 3px 8px;
                            border-radius: 12px;
                            font-size: 11px;
                            font-weight: 500;
                        }
                        .status-pending { background: #fff3cd; color: #856404; }
                        .status-processing { background: #cce5ff; color: #004085; }
                        .status-completed { background: #d4edda; color: #155724; }
                        .status-cancelled { background: #f8d7da; color: #721c24; }
                    }
                </style>
            </head>
            <body>
                <h1>Withdrawals Report</h1>
                
                <div class="filters">
                    <h3>Applied Filters:</h3>
                    ${filtersHTML}
                </div>
                
                ${tableHTML}
                
                <div class="footer">
                    Generated on ${new Date().toLocaleString()}<br>
                    Total Records: ${document.querySelectorAll("#withdrawalsBody tr").length}
                </div>
                
                <script>
                    setTimeout(() => {
                        window.print();
                        setTimeout(() => window.close(), 500);
                    }, 500);
                <\/script>
            </body>
            </html>
        `;

      printWindow.document.write(html);
      printWindow.document.close();
    } catch (error) {
      console.error("Print error:", error);
      throw error;
    }
  }

  /**
   * Get filters HTML for print
   */
  getFiltersHTML() {
    let filtersHTML = "<ul>";

    if (this.filters.status) {
      filtersHTML += `<li>Status: ${this.filters.status}</li>`;
    }
    if (this.filters.driver_name) {
      filtersHTML += `<li>Driver: ${this.filters.driver_name}</li>`;
    }
    if (this.filters.date_from) {
      filtersHTML += `<li>From Date: ${this.filters.date_from}</li>`;
    }
    if (this.filters.date_to) {
      filtersHTML += `<li>To Date: ${this.filters.date_to}</li>`;
    }
    if (this.filters.reference) {
      filtersHTML += `<li>Reference: ${this.filters.reference}</li>`;
    }

    if (filtersHTML === "<ul>") {
      filtersHTML += "<li>No filters applied (all records)</li>";
    }

    filtersHTML += "</ul>";
    return filtersHTML;
  }

  /**
   * Get selected withdrawal IDs
   */
  getSelectedWithdrawalIds() {
    const checkboxes = document.querySelectorAll(
      ".withdrawal-checkbox:checked",
    );
    return Array.from(checkboxes).map((cb) => cb.value);
  }

  /**
   * Show export loading state
   */
  showExportLoading(isLoading) {
    const exportBtn = document.getElementById("exportBtn");

    if (isLoading) {
      exportBtn.disabled = true;
      exportBtn.innerHTML =
        '<i class="fas fa-spinner fa-spin"></i> Exporting...';
    } else {
      exportBtn.disabled = false;
      exportBtn.innerHTML = '<i class="fas fa-download"></i> Export';
    }
  }

  /**
   * Show export success message
   */
  showExportSuccess(message) {
    this.showToast(message, "success");
  }

  /**
   * Show export error message
   */
  showExportError(message) {
    this.showToast(message, "error");
  }

  /**
   * Show toast notification
   */
  showToast(message, type = "info") {
    // Create toast element
    const toast = document.createElement("div");
    toast.className = `toast toast-${type}`;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        background: ${type === "success" ? "#4CAF50" : type === "error" ? "#f44336" : "#2196F3"};
        color: white;
        border-radius: 4px;
        z-index: 9999;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        animation: slideIn 0.3s ease;
    `;

    toast.innerHTML = `
        <i class="fas ${type === "success" ? "fa-check-circle" : type === "error" ? "fa-exclamation-circle" : "fa-info-circle"}"></i>
        ${message}
    `;

    document.body.appendChild(toast);

    // Auto remove after 3 seconds
    setTimeout(() => {
      toast.style.animation = "fadeOut 0.3s ease";
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }

  /**
   * Log export action
   */
  logExportAction(format, selectedCount, exportType) {
    console.log(
      `Export: ${format} | Type: ${exportType} | Selected: ${selectedCount} | Time: ${new Date().toISOString()}`,
    );

    // You can also send to server for logging
    // fetch("backend/log_action.php", {
    //   method: "POST",
    //   headers: { "Content-Type": "application/json" },
    //   body: JSON.stringify({
    //     action: "export",
    //     format: format,
    //     selected_count: selectedCount,
    //     export_type: exportType,
    //     timestamp: new Date().toISOString(),
    //   }),
    // }).catch((err) => console.error("Failed to log export:", err));
  }

  // end of export function
}

// Initialize
document.addEventListener("DOMContentLoaded", () => {
  window.adminManager = new AdminWithdrawalManager();
});

// Global functions
function closeProcessModal() {
  adminManager.closeProcessModal();
}
function closeDetailsModal() {
  adminManager.closeDetailsModal();
}
function closeDriverModal() {
  adminManager.closeDriverModal();
}
function closeBulkModal() {
  adminManager.closeBulkModal();
}
function closeSuccessModal() {
  adminManager.closeSuccessModal();
}
function processWithdrawal() {
  adminManager.processWithdrawal();
}
function toggleProcessFields() {
  adminManager.toggleProcessFields();
}
function filterByStatus(status) {
  adminManager.filterByStatus(status);
}
function filterByDate(period) {
  adminManager.filterByDate(period);
}
function processBulkAction() {
  adminManager.processBulkAction();
}
function updateBulkModalTitle() {
  const action = document.getElementById("bulkActionType")?.value;
  if (action && adminManager) {
    adminManager.currentBulkAction = action;

    document.getElementById("bulkApproveFields").style.display =
      action === "approve" ? "block" : "none";
    document.getElementById("bulkRejectFields").style.display =
      action === "reject" ? "block" : "none";
    document.getElementById("bulkFailedFields").style.display =
      action === "mark_failed" ? "block" : "none";
  }
}
