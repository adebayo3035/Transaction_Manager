class DriverWithdrawal {
  constructor() {
    this.balance = 0;
    this.driverData = null;
    this.savedBanks = [];
    this.withdrawals = [];
    this.selectedBank = null;
    this.apiUrl = "../v2/driver_bankapi.php";
    this.allBanksList = [];

    this.init();
  }

  async init() {
    await this.loadDriverInfo();
    await this.loadSavedBanks();
    await this.loadWithdrawalHistory();
    await this.loadRecentWithdrawals();
    this.setupEventListeners();
    this.setupModals();
    await this.loadDriverBanks();
    await this.initBankSelectListener();
  }

  async loadDriverInfo() {
    try {
      const response = await fetch("../v2/profile.php");
      if (!response.ok)
        throw new Error("Unauthorized or failed to fetch driver info");

      const data = await response.json();
      this.driverData = data;

      // Update UI with driver data
      this.updateDriverInfo(data);

      console.log("Driver data loaded:", data);
      return data;
    } catch (error) {
      console.error("Error loading driver info:", error);
      this.showToast("error", "Failed to load driver information");

      // Set fallback values
      document.querySelector(".balance-amount").textContent = "₦0.00";
      document.querySelector(".balance-updated").textContent =
        "Unable to load balance";

      throw error;
    }
  }
  async loadDriverBanks() {
    try {
      const response = await fetch(`${this.apiUrl}?action=list`);
      const data = await response.json();

      if (data.success) {
        this.allBanksList = data.banks;
        this.populateBankSelect();
      } else {
        this.showToast("error", data.message || "Failed to load banks");
      }
    } catch (error) {
      console.error("Error loading banks:", error);
      this.showToast("error", "Failed to load your banks");
    }
  }

  populateBankSelect() {
    const select = document.getElementById("bankName");

    select.innerHTML = '<option value="">Select Bank</option>';

    this.allBanksList.forEach((bank) => {
      const option = document.createElement("option");
      option.value = bank.bank_code;
      option.textContent = `${bank.bank_name} - ${bank.masked_account}`;
      option.dataset.name = bank.account_name;
      option.dataset.account_number = bank.account_number;
      select.appendChild(option);
    });
  }
  initBankSelectListener() {
    const select = document.getElementById("bankName");

    select.addEventListener("change", (e) => {
      const selectedOption = e.target.selectedOptions[0];

      if (!selectedOption || !selectedOption.value) {
        // Reset fields if "Select Bank" is chosen
        document.getElementById("accountNumber").value = "";
        document.getElementById("accountName").value = "";
        return;
      }

      // Populate fields
      document.getElementById("accountNumber").value =
        selectedOption.dataset.account_number;
      document.getElementById("accountName").value =
        selectedOption.dataset.name || "";
    });
  }

  async loadSavedBanks() {
    try {
      const response = await fetch(`${this.apiUrl}?action=list`);
      const data = await response.json();

      if (data.success) {
        this.savedBanks = data.banks;
        this.renderSavedBanks();
      } else {
        this.showToast("error", data.message || "Failed to load banks");
      }
    } catch (error) {
      console.error("Error loading banks:", error);
      this.showToast("error", "Failed to load your banks");
    }
  }

  async loadRecentWithdrawals() {
    try {
      // Fetch only 2 most recent withdrawals (limit=2, page=1)
      const response = await fetch(
        "../v2/driver_withdrawal_api.php?action=history&page=1&limit=2",
      );
      const data = await response.json();

      const historyList = document.getElementById("historyList");
      const emptyHistory = document.getElementById("emptyHistory");

      if (data.success && data.withdrawals && data.withdrawals.length > 0) {
        // Hide empty state, show history list
        historyList.style.display = "block";
        emptyHistory.style.display = "none";

        // Clear existing items
        historyList.innerHTML = "";

        // Loop through withdrawals (max 2)
        data.withdrawals.forEach((withdrawal) => {
          const statusClass = this.getStatusClass(withdrawal.status);
          const statusIcon = this.getStatusIcon(withdrawal.status);

          const historyItem = document.createElement("div");
          historyItem.className = "history-item";
          historyItem.innerHTML = `
                    <div class="history-status ${statusClass}">
                        <i class="fas ${statusIcon}"></i>
                    </div>
                    <div class="history-details">
                        <span class="history-amount">${withdrawal.formatted_amount}</span>
                        <span class="history-bank">${withdrawal.bank_name} - ${withdrawal.masked_account}</span>
                        <span class="history-date">${withdrawal.time_ago}</span>
                    </div>
                `;
          historyList.appendChild(historyItem);
        });

        // If only 1 withdrawal was returned, we still show it
        // The loop handles any number between 1-2
      } else {
        // No withdrawals found - show empty state
        historyList.style.display = "none";
        emptyHistory.style.display = "block";
      }
    } catch (error) {
      console.error("Error loading recent withdrawals:", error);
      // Show empty state on error
      document.getElementById("historyList").style.display = "none";
      document.getElementById("emptyHistory").style.display = "block";
    }
  }

  // Helper function to get status class based on withdrawal status
  getStatusClass(status) {
    const classes = {
      pending: "pending",
      processing: "processing",
      completed: "success",
      failed: "failed",
      cancelled: "cancelled",
    };
    return classes[status] || "pending";
  }

  // Helper function to get status icon based on withdrawal status
  getStatusIcon(status) {
    const icons = {
      pending: "fa-clock",
      processing: "fa-spinner fa-spin",
      completed: "fa-check-circle",
      failed: "fa-times-circle",
      cancelled: "fa-ban",
    };
    return icons[status] || "fa-clock";
  }

  // Helper function to format amount (if your API doesn't provide formatted_amount)
  formatAmount(amount) {
    return (
      "₦" +
      parseFloat(amount)
        .toFixed(2)
        .replace(/\d(?=(\d{3})+\.)/g, "$&,")
    );
  }

  // Helper function to mask account number
  maskAccount(accountNumber) {
    if (!accountNumber) return "";
    return "****" + accountNumber.slice(-4);
  }

  async loadWithdrawalHistory() {
    try {
      // Simulate API call - Replace with actual endpoint
      // const response = await fetch("../v2/withdrawal-history.php");
      // const data = await response.json();
      // this.withdrawals = data.withdrawals || [];

      // Mock data based on driver info
      this.withdrawals = [
        {
          amount: 5000,
          bank: "GTBank",
          account: "0123",
          status: "success",
          date: new Date().toISOString(),
          reference: "WDV-20240216-001",
        },
        {
          amount: 3500,
          bank: "First Bank",
          account: "9876",
          status: "pending",
          date: new Date(Date.now() - 86400000).toISOString(),
          reference: "WDV-20240215-002",
        },
      ];

      this.renderHistory();
    } catch (error) {
      console.error("Error loading withdrawal history:", error);
      this.showToast("error", "Failed to load withdrawal history");
    }
  }

  updateDriverInfo(data) {
    // Update balance
    this.balance = parseFloat(data.wallet_balance) || 0;
    document.querySelector(".balance-amount").textContent =
      `₦${this.formatNumber(this.balance)}`;

    document.querySelector("#max_hint").textContent =
      `Max: ₦${this.formatNumber(this.balance * 0.80)}`;

    

    // Update last updated time
    const now = new Date();
    document.querySelector(".balance-updated").textContent =
      `Last updated: ${now.toLocaleDateString()}, ${now.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })}`;

    // Update page header with driver name if available
    if (data.fullname) {
      const headerSubtitle = document.querySelector(".page-header p");
      if (headerSubtitle) {
        headerSubtitle.innerHTML = `Welcome back, <strong>${data.fullname}</strong> | Withdraw your earnings to your bank account`;
      }
    }

    // Update any driver stats that might be displayed
    console.log(
      `Driver ${data.unique_id} balance updated: ₦${data.wallet_balance}`,
    );
  }

  setupEventListeners() {
    // Withdraw all button
    document.getElementById("withdrawAll").addEventListener("click", () => {
      document.getElementById("withdrawalAmount").value = (0.80 * this.balance);
      this.validateAmount();
    });


    // Amount validation
    document
      .getElementById("withdrawalAmount")
      .addEventListener("input", () => {
        this.validateAmount();
      });

    // Account number verification
    // document.getElementById("accountNumber").addEventListener("blur", () => {
    //   this.verifyAccount();
    // });

    // // Bank selection change
    // document.getElementById("bankName").addEventListener("change", () => {
    //   if (document.getElementById("accountNumber").value) {
    //     this.verifyAccount();
    //   }
    // });

    // Form submission
    document
      .getElementById("withdrawalForm")
      .addEventListener("submit", (e) => {
        e.preventDefault();
        this.submitWithdrawal();
      });

    // Refresh balance
    document.getElementById("refreshBalance").addEventListener("click", () => {
      this.refreshBalance();
    });

    // Add bank button
    document.getElementById("addBankBtn").addEventListener("click", () => {
      this.showModal("addBankModal");
    });
  }

  setupModals() {
    // Close modal buttons
    document
      .querySelectorAll(".close-modal, #cancelAddBank, #closeSuccessModal")
      .forEach((btn) => {
        btn.addEventListener("click", () => {
          document.querySelectorAll(".modal").forEach((modal) => {
            modal.classList.remove("show");
          });
        });
      });

    // Cancel add bank
    document.getElementById("cancelAddBank").addEventListener("click", () => {
      this.hideModal("addBankModal");
    });

    // Save bank
    document.getElementById("saveBank").addEventListener("click", () => {
      this.saveBank();
    });

    // Modal account number verification
    // document
    //   .getElementById("modalAccountNumber")
    //   .addEventListener("blur", () => {
    //     this.verifyModalAccount();
    //   });

    // document.getElementById("modalBankName").addEventListener("change", () => {
    //   if (document.getElementById("modalAccountNumber").value) {
    //     this.verifyModalAccount();
    //   }
    // });
  }

  validateAmount() {
    const amount =
      parseFloat(document.getElementById("withdrawalAmount").value) || 0;
    const submitBtn = document.getElementById("submitWithdrawal");

    if (amount < 100) {
      submitBtn.disabled = true;
      this.showInputError("Amount must be at least ₦100");
    } else if (amount > (this.balance * 0.80)) {
      submitBtn.disabled = true;
      this.showInputError("Amount exceeds Maximum withdrawal allowed");
    } else {
      submitBtn.disabled = false;
      this.clearInputError();
    }
  }

  showInputError(message) {
    const amountGroup = document.querySelector(".amount-input-group");
    let errorEl = document.querySelector(".amount-error");

    if (!errorEl) {
      errorEl = document.createElement("div");
      errorEl.className = "amount-error";
      errorEl.style.color = "#dc3545";
      errorEl.style.fontSize = "12px";
      errorEl.style.marginTop = "5px";
      amountGroup.parentNode.appendChild(errorEl);
    }

    errorEl.textContent = message;
    amountGroup.style.borderColor = "#dc3545";
  }

  clearInputError() {
    const amountGroup = document.querySelector(".amount-input-group");
    const errorEl = document.querySelector(".amount-error");

    if (errorEl) {
      errorEl.remove();
    }
    amountGroup.style.borderColor = "#ddd";
  }

  //   async verifyAccount() {
  //     const bank = document.getElementById("bankName").value;
  //     const account = document.getElementById("accountNumber").value;

  //     if (!bank || !account || account.length !== 10) {
  //       document.getElementById("accountName").value = "";
  //       return;
  //     }

  //     // Show loading
  //     document.getElementById("accountName").value = "Verifying...";

  //     try {
  //       // Call account verification API
  //       const response = await fetch("../v2/verify-account.php", {
  //         method: "POST",
  //         headers: {
  //           "Content-Type": "application/json",
  //         },
  //         body: JSON.stringify({
  //           bank_code: this.getBankCode(bank),
  //           account_number: account,
  //         }),
  //       });

  //       if (response.ok) {
  //         const data = await response.json();
  //         document.getElementById("accountName").value = data.account_name || "";
  //       } else {
  //         document.getElementById("accountName").value = "Unable to verify";
  //         this.showToast("error", "Account verification failed");
  //       }
  //     } catch (error) {
  //       console.error("Account verification error:", error);

  //       // Fallback to driver name if available
  //       if (this.driverData?.fullname) {
  //         document.getElementById("accountName").value =
  //           this.driverData.fullname.toUpperCase();
  //       } else {
  //         document.getElementById("accountName").value = "Verification failed";
  //       }
  //     }
  //   }

  //   async verifyModalAccount() {
  //     const bank = document.getElementById("modalBankName").value;
  //     const account = document.getElementById("modalAccountNumber").value;

  //     if (!bank || !account || account.length !== 10) {
  //       document.getElementById("modalAccountName").value = "";
  //       return;
  //     }

  //     document.getElementById("modalAccountName").value = "Verifying...";

  //     try {
  //       const response = await fetch("../v2/verify-account.php", {
  //         method: "POST",
  //         headers: {
  //           "Content-Type": "application/json",
  //         },
  //         body: JSON.stringify({
  //           bank_code: this.getBankCode(bank),
  //           account_number: account,
  //         }),
  //       });

  //       if (response.ok) {
  //         const data = await response.json();
  //         document.getElementById("modalAccountName").value =
  //           data.account_name || "";
  //       } else {
  //         document.getElementById("modalAccountName").value = "Unable to verify";
  //       }
  //     } catch (error) {
  //       console.error("Account verification error:", error);
  //       document.getElementById("modalAccountName").value =
  //         this.driverData?.fullname?.toUpperCase() || "Verification failed";
  //     }
  //   }

  getBankCode(bankName) {
    // Map bank names to codes - update based on your bank code system
    const bankCodes = {
      GTBank: "058",
      "First Bank": "011",
      "Access Bank": "044",
      UBA: "033",
      "Zenith Bank": "057",
    };
    return bankCodes[bankName] || "";
  }

  async submitWithdrawal() {
    const submitBtn = document.getElementById("submitWithdrawal");

    const formData = {
      amount: parseFloat(document.getElementById("withdrawalAmount").value),
      bank_code: document.getElementById("bankName").value,
      account_number: document.getElementById("accountNumber").value.trim(),
      account_name: document.getElementById("accountName").value.trim(),
      note: document.getElementById("withdrawalNote").value.trim(),
      secret_answer: document.getElementById("secretAnswer").value.trim(),
    };

    // Basic Validation
    if (!formData.amount || isNaN(formData.amount)) {
      return this.showToast("error", "Enter a valid amount");
    }

    if (formData.amount > this.balance) {
      return this.showToast("error", "Insufficient balance");
    }

    if (formData.amount < 100) {
      return this.showToast("error", "Minimum withdrawal is ₦100");
    }

    if (!formData.bank_code || !formData.account_number) {
      return this.showToast("error", "Bank details are required");
    }

    if (
      !formData.account_name ||
      formData.account_name === "Unable to verify"
    ) {
      return this.showToast("error", "Please verify account details first");
    }

    if (!formData.secret_answer) {
      return this.showToast("error", "Enter your security answer");
    }

    // Loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML =
      '<i class="fas fa-spinner fa-spin"></i> Processing...';

    try {
      const response = await fetch("../v2/driver_withdrawal_api.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action: "create",
          ...formData,
        }),
      });

      let result;
      try {
        result = await response.json();
      } catch {
        throw new Error("Invalid server response");
      }

      if (!response.ok || !result.success) {
        throw new Error(result.message || "Withdrawal request failed");
      }

      // Update balance from backend
      if (result.new_balance !== undefined) {
        this.balance = result.new_balance;
        this.updateBalanceDisplay();
      }

      this.addToHistory({
        ...formData,
        status: "pending",
        reference: result.reference,
        date: new Date().toISOString(),
      });

      document.getElementById("successAmount").textContent =
        result.withdrawal.formatted_amount;

      document.getElementById("successBank").textContent =
        result.withdrawal.bank_name;

      document.getElementById("successRef").textContent =
        result.withdrawal.reference;

      this.showModal("successModal");
      this.showToast("success", result.message || "Withdrawal submitted!");

      document.getElementById("withdrawalForm").reset();
      window.location.reload();
    } catch (error) {
      console.error(error);
      this.showToast("error", error.message);
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerHTML =
        '<i class="fas fa-paper-plane"></i> Request Withdrawal';
    }
  }

  async saveBank() {
    const bankData = {
      bank_name: document.getElementById("modalBankName").value,
      account_number: document.getElementById("modalAccountNumber").value,
      account_name: document.getElementById("modalAccountName").value,
    };

    if (
      !bankData.bank_name ||
      !bankData.account_number ||
      bankData.account_number.length !== 10 ||
      !bankData.account_name
    ) {
      this.showToast("error", "Please fill all fields correctly");
      return;
    }

    if (
      bankData.account_name === "Verifying..." ||
      bankData.account_name === "Unable to verify"
    ) {
      this.showToast("error", "Please verify your account first");
      return;
    }

    try {
      // Save bank to database
      const response = await fetch("../v2/save-bank.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(bankData),
      });

      if (!response.ok) {
        throw new Error("Failed to save bank");
      }

      const result = await response.json();

      // Add to saved banks
      this.savedBanks.push(bankData);
      this.renderSavedBanks();

      // Clear and close modal
      document.getElementById("addBankForm").reset();
      document.getElementById("modalAccountName").value = "";
      this.hideModal("addBankModal");

      this.showToast("success", "Bank saved successfully");
    } catch (error) {
      console.error("Save bank error:", error);
      this.showToast("error", "Failed to save bank");
    }
  }

  addToHistory(withdrawal) {
    const historyItem = {
      amount: withdrawal.amount,
      bank: withdrawal.bank_name,
      account: withdrawal.account_number.slice(-4),
      status: withdrawal.status || "pending",
      date: withdrawal.date,
      reference: withdrawal.reference,
    };

    this.withdrawals.unshift(historyItem);
    this.renderHistory();
  }

  renderSavedBanks() {
    const listEl = document.getElementById("savedBankList");
    const emptyEl = document.getElementById("emptyBanks");

    if (this.savedBanks.length === 0) {
      listEl.style.display = "none";
      emptyEl.style.display = "block";
    } else {
      listEl.style.display = "block";
      emptyEl.style.display = "none";

      listEl.innerHTML = this.savedBanks
        .map(
          (bank) => `
                <div class="saved-bank-item">
                    <div class="bank-info">
                        <span class="bank-name">${bank.bank_name}</span>
                        <span class="bank-details">${bank.account_number} - ${bank.account_name}</span>
                    </div>
                    <button class="btn-use" onclick="window.driverWithdrawal.useSavedBank('${bank.bank_code}')">
                        <i class="fas fa-check"></i> Use
                    </button>
                </div>
            `,
        )
        .join("");
    }
  }

  renderHistory() {
    const listEl = document.getElementById("historyList");
    const emptyEl = document.getElementById("emptyHistory");

    if (this.withdrawals.length === 0) {
      listEl.style.display = "none";
      emptyEl.style.display = "block";
    } else {
      listEl.style.display = "block";
      emptyEl.style.display = "none";

      listEl.innerHTML = this.withdrawals
        .map(
          (w) => `
                <div class="history-item">
                    <div class="history-status ${w.status}">
                        <i class="fas fa-${w.status === "success" ? "check-circle" : "clock"}"></i>
                    </div>
                    <div class="history-details">
                        <span class="history-amount">₦${this.formatNumber(w.amount)}</span>
                        <span class="history-bank">${w.bank} - ${w.account}</span>
                        <span class="history-date">${this.formatDate(w.date)}</span>
                        <small class="history-ref">Ref: ${w.reference}</small>
                    </div>
                </div>
            `,
        )
        .join("");
    }
  }

  useSavedBank(bank_code) {
    const select = document.getElementById("bankName");

    select.value = bank_code; // Must match option.value
    select.dispatchEvent(new Event("change"));

    this.showToast("success", "Bank details filled");
  }

  async refreshBalance() {
    const refreshBtn = document.getElementById("refreshBalance");
    refreshBtn.innerHTML =
      '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
    refreshBtn.disabled = true;

    try {
      await this.loadDriverInfo();
      this.showToast("success", "Balance updated");
    } catch (error) {
      this.showToast("error", "Failed to update balance");
    } finally {
      refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
      refreshBtn.disabled = false;
    }
  }

  updateBalanceDisplay() {
    document.querySelector(".balance-amount").textContent =
      `₦${this.formatNumber(this.balance)}`;

    // Update in driver data as well
    if (this.driverData) {
      this.driverData.wallet_balance = this.balance;
    }
  }

  formatNumber(amount) {
    return amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  showModal(modalId) {
    document.getElementById(modalId).classList.add("show");
    document.body.style.overflow = "hidden";
  }

  hideModal(modalId) {
    document.getElementById(modalId).classList.remove("show");
    document.body.style.overflow = "";
  }

  showToast(type, message, options = {}) {
    const {
      duration = 3000,
      position = "bottom-right",
      showProgress = true,
      showClose = true,
      animate = true,
    } = options;

    // Create or get toast container
    let container = document.querySelector(".toast-container");
    if (!container) {
      container = document.createElement("div");
      container.className = `toast-container toast-${position}`;
      document.body.appendChild(container);
    }

    // Create toast element
    const toast = document.createElement("div");
    toast.className = `toast-notification toast-${type}`;

    // Get icon based on type
    const icons = {
      success: "check-circle",
      error: "exclamation-circle",
      warning: "exclamation-triangle",
      info: "info-circle",
    };

    const icon = icons[type] || "info-circle";

    // Build toast HTML
    let html = `
        <i class="fas fa-${icon}"></i>
        <span>${message}</span>
    `;

    // Add close button if enabled
    if (showClose) {
      html += `<button class="toast-close" onclick="this.closest('.toast-notification').remove()">&times;</button>`;
    }

    // Add progress bar if enabled
    if (showProgress) {
      html += `
            <div class="toast-progress">
                <div class="toast-progress-bar"></div>
            </div>
        `;
    }

    toast.innerHTML = html;

    // Add to container
    container.appendChild(toast);

    // Trigger animation
    setTimeout(() => toast.classList.add("show"), 1000);

    // Auto remove after duration
    const timeoutId = setTimeout(() => {
      if (animate) {
        toast.classList.add("hide");
        setTimeout(() => {
          if (toast.parentNode) {
            toast.remove();
          }
          // Remove container if empty
          if (container.children.length === 0) {
            container.remove();
          }
        }, 600);
      } else {
        toast.remove();
        if (container.children.length === 0) {
          container.remove();
        }
      }
    }, duration);

    // Allow click to dismiss
    toast.addEventListener("click", (e) => {
      if (!e.target.classList.contains("toast-close")) {
        clearTimeout(timeoutId);
        if (animate) {
          toast.classList.add("hide");
          setTimeout(() => {
            if (toast.parentNode) {
              toast.remove();
            }
            if (container.children.length === 0) {
              container.remove();
            }
          }, 600);
        } else {
          toast.remove();
          if (container.children.length === 0) {
            container.remove();
          }
        }
      }
    });

    return toast;
  }

  // Convenience methods
  showSuccess(message, options) {
    return this.showToast("success", message, options);
  }

  showError(message, options) {
    return this.showToast("error", message, options);
  }

  showWarning(message, options) {
    return this.showToast("warning", message, options);
  }

  showInfo(message, options) {
    return this.showToast("info", message, options);
  }

  formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;

    if (diff < 60000) return "Just now";
    if (diff < 3600000) return `${Math.floor(diff / 60000)} minutes ago`;
    if (diff < 86400000) return `${Math.floor(diff / 3600000)} hours ago`;
    return date.toLocaleDateString();
  }
}

// Initialize when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  window.driverWithdrawal = new DriverWithdrawal();
});
