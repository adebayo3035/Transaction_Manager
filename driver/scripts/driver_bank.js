class DriverBankManager {
  constructor() {
    this.apiUrl = "../v2/driver_bankapi.php";
    this.banksApiUrl = "../v2/get_active_banks.php";
    this.driverId = null;
    this.banks = [];
    this.maxBanks = 3;
    this.currentBankId = null;
    this.allBanksList = [];

    this.init();
  }

  async init() {
    this.showLoading();
    await this.loadDriverBanks();
    await this.loadAllBanks();
    await this.loadRecentWithdrawals();
    this.setupEventListeners();
    this.hideLoading();
  }

  async loadDriverBanks() {
    try {
      const response = await fetch(`${this.apiUrl}?action=list`);
      const data = await response.json();

      if (data.success) {
        this.banks = data.banks;
        this.renderBanksList();
        this.updateStats();
      } else {
        this.showToast("error", data.message || "Failed to load banks");
      }
    } catch (error) {
      console.error("Error loading banks:", error);
      this.showToast("error", "Failed to load your banks");
    }
  }

  async loadAllBanks() {
    try {
      const response = await fetch(this.banksApiUrl);
      const data = await response.json();

      if (data.success) {
        this.allBanksList = data.banks;
        this.populateBankSelect();
      } else {
        console.error("Failed to load banks list:", data.message);
      }
    } catch (error) {
      console.error("Error loading banks list:", error);
    }
  }

  populateBankSelect() {
    const select = document.getElementById("bankSelect");
    const searchInput = document.getElementById("bankSearch");

    select.innerHTML = '<option value="">Select Bank</option>';

    this.allBanksList.forEach((bank) => {
      const option = document.createElement("option");
      option.value = bank.code;
      option.textContent = bank.display || bank.name;
      option.dataset.name = bank.name;
      select.appendChild(option);
    });

    // Setup search functionality
    searchInput.addEventListener("input", () => {
      const searchTerm = searchInput.value.toLowerCase();
      const options = select.options;

      for (let i = 1; i < options.length; i++) {
        const text = options[i].text.toLowerCase();
        const shouldShow = text.includes(searchTerm);
        options[i].style.display = shouldShow ? "" : "none";
      }

      // Select first visible option if nothing selected
      if (!select.value) {
        for (let i = 1; i < options.length; i++) {
          if (options[i].style.display !== "none") {
            select.value = options[i].value;
            this.updateSelectedBankInfo();
            break;
          }
        }
      }
    });
  }

  renderBanksList() {
    const bankList = document.getElementById("bankList");
    const emptyState = document.getElementById("emptyState");
    const bankCounter = document.getElementById("bankCounter");

    // Update counter
    const countSpan = bankCounter.querySelector(".count");
    countSpan.textContent = `${this.banks.length}/${this.maxBanks}`;

    // Enable/disable add button based on limit
    const addBtn = document.getElementById("addBankBtn");
    const emptyAddBtn = document.getElementById("emptyAddBankBtn");

    if (this.banks.length >= this.maxBanks) {
      addBtn.disabled = true;
      if (emptyAddBtn) emptyAddBtn.disabled = true;
    } else {
      addBtn.disabled = false;
      if (emptyAddBtn) emptyAddBtn.disabled = false;
    }

    if (this.banks.length === 0) {
      bankList.style.display = "none";
      emptyState.style.display = "block";
      return;
    }

    bankList.style.display = "block";
    emptyState.style.display = "none";

    bankList.innerHTML = this.banks
      .map(
        (bank) => `
            <div class="saved-bank-item ${bank.is_default ? "default-bank" : ""}" data-id="${bank.id}">
                <div class="bank-info">
                    <span class="bank-name">
                        ${bank.bank_name}
                        ${bank.is_default ? '<span class="default-badge">Default</span>' : ""}
                    </span>
                    <span class="bank-details">${bank.masked_account} - ${bank.account_name}</span>
                </div>
                <div class="bank-actions">
                    ${
                      !bank.is_default
                        ? `
                        <button class="btn-icon set-default" onclick="bankManager.showSetDefaultModal(${bank.id}, '${bank.bank_name}', '${bank.masked_account}')" 
                                title="Set as default">
                            <i class="fas fa-star"></i>
                        </button>
                    `
                        : ""
                    }
                    <button class="btn-icon edit" onclick="bankManager.showEditModal(${bank.id}, '${bank.account_name}')" 
                            title="Edit account name">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-icon delete" onclick="bankManager.showDeleteModal(${bank.id}, '${bank.bank_name}', '${bank.masked_account}')" 
                            title="Delete bank">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `,
      )
      .join("");
  }

  updateStats() {
    document.getElementById("statTotal").textContent = this.banks.length;

    const defaultBank = this.banks.find((b) => b.is_default);
    document.getElementById("statDefault").textContent = defaultBank
      ? defaultBank.bank_name
      : "None";

    document.getElementById("statRemaining").textContent =
      this.maxBanks - this.banks.length;
    document.getElementById("statUpdated").textContent =
      new Date().toLocaleTimeString();
  }

  setupEventListeners() {
    // Add bank buttons
    document
      .getElementById("addBankBtn")
      .addEventListener("click", () => this.showAddModal());
    document
      .getElementById("emptyAddBankBtn")
      .addEventListener("click", () => this.showAddModal());

    // Refresh button
    document.getElementById("refreshBtn").addEventListener("click", () => {
      this.showToast("info", "Refreshing...");
      this.loadDriverBanks();
    });

    // Bank select change
    document.getElementById("bankSelect").addEventListener("change", () => {
      this.updateSelectedBankInfo();
    });

    // Account number input
    document.getElementById("accountNumber").addEventListener("input", () => {
      const accountNumber = document.getElementById("accountNumber").value;
      if (accountNumber.length === 10) {
        this.verifyAccount();
      } else {
        document.getElementById("accountName").value = "";
        document.getElementById("verifyStatus").innerHTML = "";
      }
    });

    // Close modals on escape key
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        this.closeAllModals();
      }
    });
  }

  showAddModal() {
    if (this.banks.length >= this.maxBanks) {
      this.showToast(
        "error",
        `You cannot have more than ${this.maxBanks} banks. Please delete a bank first.`,
      );
      return;
    }

    document.getElementById("modalTitle").innerHTML =
      '<i class="fas fa-plus-circle"></i> Add New Bank';
    document.getElementById("bankId").value = "";
    document.getElementById("bankForm").reset();
    document.getElementById("step1").style.display = "block";
    document.getElementById("step2").style.display = "none";
    document.getElementById("sameBankWarning").style.display = "none";

    this.showModal("bankModal");
  }

  async showEditModal(bankId, currentName) {
    this.currentBankId = bankId;
    document.getElementById("editBankId").value = bankId;
    document.getElementById("editAccountName").value = currentName;
    document.getElementById("editSecretAnswer").value = "";
    document.getElementById("editSetDefault").checked = false;

    this.showModal("editModal");
  }

  showSetDefaultModal(bankId, bankName, maskedAccount) {
    this.currentBankId = bankId;
    document.getElementById("defaultBankInfo").textContent =
      `Set ${bankName} (${maskedAccount}) as your default bank?`;
    document.getElementById("defaultSecretAnswer").value = "";
    this.showModal("defaultModal");
  }

  showDeleteModal(bankId, bankName, maskedAccount) {
    this.currentBankId = bankId;
    document.getElementById("deleteBankInfo").textContent =
      `Delete ${bankName} (${maskedAccount})? This action cannot be undone.`;
    document.getElementById("deleteSecretAnswer").value = "";
    this.showModal("deleteModal");
  }

  updateSelectedBankInfo() {
    const select = document.getElementById("bankSelect");
    const selectedOption = select.options[select.selectedIndex];

    if (selectedOption && select.value) {
      document.getElementById("selectedBankInfo").innerHTML = `
                <strong>Selected Bank:</strong> ${selectedOption.text}
            `;
    } else {
      document.getElementById("selectedBankInfo").innerHTML = "";
    }
  }

  async verifyAccount() {
    const bankCode = document.getElementById("bankSelect").value;
    const accountNumber = document.getElementById("accountNumber").value;

    if (!bankCode || accountNumber.length !== 10) {
      return;
    }

    const verifyStatus = document.getElementById("verifyStatus");
    verifyStatus.innerHTML =
      '<span class="verifying"><i class="fas fa-spinner fa-spin"></i> Verifying...</span>';

    try {
      const response = await fetch(this.apiUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "verify",
          bank_code: bankCode,
          account_number: accountNumber,
        }),
      });

      const data = await response.json();

      if (data.success) {
        document.getElementById("accountName").value = data.account_name;
        verifyStatus.innerHTML =
          '<span class="success"><i class="fas fa-check-circle"></i> Verified</span>';

        // Check if this bank already exists
        const existingSameBank = this.banks.some(
          (b) => b.bank_code === bankCode,
        );
        if (existingSameBank) {
          document.getElementById("sameBankWarning").style.display = "flex";
        } else {
          document.getElementById("sameBankWarning").style.display = "none";
        }
      } else {
        document.getElementById("accountName").value = "";
        verifyStatus.innerHTML = `<span class="error"><i class="fas fa-exclamation-circle"></i> ${data.message}</span>`;
      }
    } catch (error) {
      console.error("Verification error:", error);
      verifyStatus.innerHTML =
        '<span class="error"><i class="fas fa-exclamation-circle"></i> Verification failed</span>';
    }
  }

  async saveBank() {
    const bankCode = document.getElementById("bankSelect").value;
    const accountNumber = document.getElementById("accountNumber").value;
    const accountName = document.getElementById("accountName").value;
    const secretAnswer = document.getElementById("secretAnswer").value;
    const setDefault = document.getElementById("setDefault").checked;

    // Validate
    if (!bankCode) {
      this.showToast("error", "Please select a bank");
      return;
    }

    if (!accountNumber || accountNumber.length !== 10) {
      this.showToast("error", "Please enter a valid 10-digit account number");
      return;
    }

    if (!accountName) {
      this.showToast("error", "Please verify your account first");
      return;
    }

    if (!secretAnswer) {
      this.showToast("error", "Secret answer is required for authorization");
      return;
    }

    const saveBtn = document.getElementById("saveBankBtn");
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    try {
      const response = await fetch(this.apiUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "add",
          bank_code: bankCode,
          account_number: accountNumber,
          account_name: accountName,
          secret_answer: secretAnswer,
          set_default: setDefault,
        }),
      });

      const data = await response.json();

      if (data.success) {
        this.hideModal("bankModal");
        await this.loadDriverBanks();
        this.showSuccess(
          "Bank Added!",
          "Your bank account has been added successfully.",
        );

        if (data.bank.warning) {
          this.showToast("warning", data.bank.warning);
        }
      } else {
        this.showToast("error", data.message);
      }
    } catch (error) {
      console.error("Error saving bank:", error);
      this.showToast("error", "Failed to save bank");
    } finally {
      saveBtn.disabled = false;
      saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Bank';
    }
  }

  async updateBank() {
    const bankId = document.getElementById("editBankId").value;
    const accountName = document.getElementById("editAccountName").value;
    const secretAnswer = document.getElementById("editSecretAnswer").value;
    const setDefault = document.getElementById("editSetDefault").checked;

    if (!accountName) {
      this.showToast("error", "Account name is required");
      return;
    }

    if (!secretAnswer) {
      this.showToast("error", "Secret answer is required");
      return;
    }

    try {
      const response = await fetch(this.apiUrl, {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "update",
          bank_id: parseInt(bankId),
          account_name: accountName,
          secret_answer: secretAnswer,
          set_default: setDefault,
        }),
      });

      const data = await response.json();

      if (data.success) {
        this.hideModal("editModal");
        await this.loadDriverBanks();
        this.showSuccess(
          "Bank Updated!",
          "Your bank account has been updated successfully.",
        );
      } else {
        this.showToast("error", data.message);
      }
    } catch (error) {
      console.error("Error updating bank:", error);
      this.showToast("error", "Failed to update bank");
    }
  }

  async confirmSetDefault() {
    const secretAnswer = document.getElementById("defaultSecretAnswer").value;

    if (!secretAnswer) {
      this.showToast("error", "Secret answer is required");
      return;
    }

    try {
      const response = await fetch(this.apiUrl, {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "set_default",
          bank_id: parseInt(this.currentBankId),
          secret_answer: secretAnswer,
        }),
      });

      const data = await response.json();

      if (data.success) {
        this.hideModal("defaultModal");
        await this.loadDriverBanks();
        this.showSuccess(
          "Default Bank Updated!",
          "Your default bank has been changed successfully.",
        );
      } else {
        this.showToast("error", data.message);
      }
    } catch (error) {
      console.error("Error setting default bank:", error);
      this.showToast("error", "Failed to set default bank");
    }
  }

  // load recent withdrawals
  async loadRecentWithdrawals() {
    try {
      const response = await fetch(
        "../v2/driver_withdrawal_api.php?action=history&page=1&limit=3",
      );
      const data = await response.json();

      const recentList = document.getElementById("recentWithdrawals");

      if (data.success && data.withdrawals && data.withdrawals.length > 0) {
        // Clear existing items
        recentList.innerHTML = "";

        data.withdrawals.forEach((withdrawal) => {
          const displayDate = this.formatRecentDate(
            withdrawal.created_at,
            withdrawal.time_ago,
          );

          const recentItem = document.createElement("div");
          recentItem.className = "recent-item";
          recentItem.innerHTML = `
                    <span class="recent-amount">${withdrawal.formatted_amount}</span>
                    <span class="recent-bank">${withdrawal.bank_name} - ${withdrawal.masked_account}</span>
                    <span class="recent-date">${displayDate}</span>
                `;
          recentList.appendChild(recentItem);
        });

        // Hide the "View All" link if there are no withdrawals? (optional)
        document.querySelector(".view-all-link").style.display =
          data.withdrawals.length > 0 ? "flex" : "none";
      } else {
        // Hide the recent items and show empty state
        recentList.innerHTML = `
                <div class="empty-recent">
                    <i class="fas fa-receipt"></i>
                    <p>No recent withdrawals</p>
                </div>
            `;
      }
    } catch (error) {
      console.error("Error loading recent withdrawals:", error);
      document.getElementById("recentWithdrawals").innerHTML = `
            <div class="empty-recent error">
                <i class="fas fa-exclamation-circle"></i>
                <p>Failed to load</p>
            </div>
        `;
    }
  }
  // Helper function to format date for display
formatRecentDate(createdAt, timeAgo) {
    // If time_ago is provided by API, use it
    if (timeAgo) {
        return timeAgo;
    }
    
    // Otherwise format the date
    const date = new Date(createdAt);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays === 0) return 'Today';
    if (diffDays === 1) return 'Yesterday';
    if (diffDays < 7) return `${diffDays} days ago`;
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

  async confirmDelete() {
    const secretAnswer = document.getElementById("deleteSecretAnswer").value;

    if (!secretAnswer) {
      this.showToast("error", "Secret answer is required");
      return;
    }

    try {
      const response = await fetch(this.apiUrl, {
        method: "DELETE",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          bank_id: parseInt(this.currentBankId),
          secret_answer: secretAnswer,
        }),
      });

      const data = await response.json();

      if (data.success) {
        this.hideModal("deleteModal");
        await this.loadDriverBanks();
        this.showSuccess(
          "Bank Deleted!",
          "Your bank account has been removed successfully.",
        );
      } else {
        this.showToast("error", data.message);
      }
    } catch (error) {
      console.error("Error deleting bank:", error);
      this.showToast("error", "Failed to delete bank");
    }
  }

  nextStep(step) {
    const bankCode = document.getElementById("bankSelect").value;

    if (step === 2 && !bankCode) {
      this.showToast("error", "Please select a bank");
      return;
    }

    document.getElementById("step1").style.display = "none";
    document.getElementById("step2").style.display = "block";
  }

  prevStep(step) {
    document.getElementById("step1").style.display = "block";
    document.getElementById("step2").style.display = "none";
  }

  showModal(modalId) {
    document.getElementById(modalId).classList.add("show");
    document.body.style.overflow = "hidden";
  }

  hideModal(modalId) {
    document.getElementById(modalId).classList.remove("show");
    document.body.style.overflow = "";
  }

  closeAllModals() {
    const modals = [
      "bankModal",
      "editModal",
      "defaultModal",
      "deleteModal",
      "successModal",
      "supportModal",
    ];
    modals.forEach((id) => {
      const modal = document.getElementById(id);
      if (modal) modal.classList.remove("show");
    });
    document.body.style.overflow = "";
  }

  showSuccess(title, message) {
    document.getElementById("successTitle").textContent = title;
    document.getElementById("successMessage").textContent = message;
    this.showModal("successModal");
  }

  showSupportModal() {
    document.getElementById("supportSubject").value = "Bank Account Issue";
    document.getElementById("supportMessage").value = "";
    document.getElementById("supportEmail").value = "";
    this.showModal("supportModal");
  }

  sendSupportMessage() {
    const subject = document.getElementById("supportSubject").value;
    const message = document.getElementById("supportMessage").value;
    const email = document.getElementById("supportEmail").value;

    if (!subject || !message || !email) {
      this.showToast("error", "Please fill all fields");
      return;
    }

    // Here you would send the support message to your backend
    this.showToast(
      "success",
      "Support message sent! We'll respond within 24 hours.",
    );
    this.hideModal("supportModal");
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
            <i class="fas fa-${icons[type] || "info-circle"}"></i>
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
    document.getElementById("loadingState").style.display = "block";
    document.getElementById("bankList").style.display = "none";
    document.getElementById("emptyState").style.display = "none";
  }

  hideLoading() {
    document.getElementById("loadingState").style.display = "none";
  }
}

// Global functions for HTML onclick handlers
function nextStep(step) {
  bankManager.nextStep(step);
}
function prevStep(step) {
  bankManager.prevStep(step);
}
function saveBank() {
  bankManager.saveBank();
}
function updateBank() {
  bankManager.updateBank();
}
function confirmSetDefault() {
  bankManager.confirmSetDefault();
}
function confirmDelete() {
  bankManager.confirmDelete();
}
function showSupportModal() {
  bankManager.showSupportModal();
}
function sendSupportMessage() {
  bankManager.sendSupportMessage();
}

function closeBankModal() {
  bankManager.hideModal("bankModal");
}
function closeEditModal() {
  bankManager.hideModal("editModal");
}
function closeDefaultModal() {
  bankManager.hideModal("defaultModal");
}
function closeDeleteModal() {
  bankManager.hideModal("deleteModal");
}
function closeSuccessModal() {
  bankManager.hideModal("successModal");
}
function closeSupportModal() {
  bankManager.hideModal("supportModal");
}

// Initialize when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  window.bankManager = new DriverBankManager();
});
