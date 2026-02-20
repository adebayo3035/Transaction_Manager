class DriverWithdrawalManager {
    constructor() {
        this.apiUrl = '../v2/driver_withdrawal_api.php';
        this.currentPage = 1;
        this.limit = 10;
        this.filters = {
            status: '',
            from_date: '',
            to_date: '',
            search: ''
        };
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
    }

    async loadSummary() {
        try {
            const response = await fetch(`${this.apiUrl}?action=summary`);
            const data = await response.json();
            
            if (data.success) {
                this.updateSummaryUI(data.summary);
            } else {
                this.showToast('error', 'Failed to load summary');
            }
        } catch (error) {
            console.error('Error loading summary:', error);
            this.showToast('error', 'Error loading summary');
        }
    }

    async loadWithdrawals() {
        const loadingRow = document.getElementById('loadingRow');
        const emptyState = document.getElementById('emptyState');
        const tableBody = document.getElementById('withdrawalsBody');
        
        loadingRow.style.display = '';
        emptyState.style.display = 'none';
        
        // Clear existing rows except loading
        Array.from(tableBody.children).forEach(row => {
            if (row.id !== 'loadingRow') {
                row.remove();
            }
        });

        try {
            const params = new URLSearchParams({
                action: 'history',
                page: this.currentPage,
                limit: this.limit,
                ...this.filters
            });

            const response = await fetch(`${this.apiUrl}?${params}`);
            const data = await response.json();
            
            if (data.success) {
                this.renderWithdrawals(data.withdrawals);
                this.renderPagination(data.pagination);
                
                if (data.withdrawals.length === 0) {
                    loadingRow.style.display = 'none';
                    emptyState.style.display = 'block';
                } else {
                    loadingRow.style.display = 'none';
                }
                
                this.updateTableInfo(data.pagination);
            } else {
                this.showToast('error', data.message || 'Failed to load withdrawals');
            }
        } catch (error) {
            console.error('Error loading withdrawals:', error);
            this.showToast('error', 'Error loading withdrawals');
            loadingRow.style.display = 'none';
            emptyState.style.display = 'block';
        }
    }

    async loadWithdrawalDetails(withdrawalId) {
        try {
            const response = await fetch(`${this.apiUrl}?action=details&withdrawal_id=${withdrawalId}`);
            const data = await response.json();
            
            if (data.success) {
                this.showDetailsModal(data.withdrawal);
            } else {
                this.showToast('error', data.message || 'Failed to load details');
            }
        } catch (error) {
            console.error('Error loading details:', error);
            this.showToast('error', 'Error loading withdrawal details');
        }
    }

    async cancelWithdrawal(withdrawalId, secretAnswer, reason) {
        const cancelBtn = document.getElementById('confirmCancelBtn');
        cancelBtn.disabled = true;
        cancelBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'cancel',
                    withdrawal_id: withdrawalId,
                    secret_answer: secretAnswer,
                    cancel_reason: reason || 'User cancelled'
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.closeCancelModal();
                this.showSuccess(
                    'Withdrawal Cancelled!',
                    'Your withdrawal has been cancelled and amount refunded.',
                    `Refunded: ${data.formatted_new_balance}`
                );
                await this.loadSummary();
                await this.loadWithdrawals();
            } else {
                this.showError(data.message || 'Failed to cancel withdrawal');
            }
        } catch (error) {
            console.error('Error cancelling withdrawal:', error);
            this.showError('Error cancelling withdrawal');
        } finally {
            cancelBtn.disabled = false;
            cancelBtn.innerHTML = '<i class="fas fa-ban"></i> Cancel Withdrawal';
        }
    }

    renderWithdrawals(withdrawals) {
        const tableBody = document.getElementById('withdrawalsBody');
        
        withdrawals.forEach(w => {
            const row = document.createElement('tr');
            row.dataset.id = w.id;
            
            const statusClass = this.getStatusClass(w.status);
            
            row.innerHTML = `
                <td>
                    <span class="reference">${w.reference}</span>
                </td>
                <td>
                    <span class="date">${w.date_formatted}</span>
                    <span class="time">${w.time_formatted}</span>
                    <span class="time-ago">${w.time_ago}</span>
                </td>
                <td>
                    <span class="amount">${w.formatted_amount}</span>
                </td>
                <td>
                    <span class="bank">${w.bank_name}</span>
                </td>
                <td>
                    <span class="account">${w.masked_account}</span>
                    <span class="account-name">${w.account_name}</span>
                </td>
                <td>
                    <span class="status-badge status-${w.status}">${w.status_text}</span>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-icon view" onclick="withdrawalManager.loadWithdrawalDetails(${w.id})" 
                                title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${w.status === 'pending' ? `
                            <button class="btn-icon cancel" onclick="withdrawalManager.showCancelModal(${w.id}, '${w.reference}', '${w.formatted_amount}')" 
                                    title="Cancel Withdrawal">
                                <i class="fas fa-ban"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            `;
            
            tableBody.appendChild(row);
        });
    }

    renderPagination(pagination) {
        const container = document.getElementById('pagination');
        
        if (pagination.total_pages <= 1) {
            container.innerHTML = '';
            return;
        }

        let buttons = '';
        
        // Previous button
        if (this.currentPage > 1) {
            buttons += `
                <button class="page-btn" data-page="${this.currentPage - 1}">
                    <i class="fas fa-chevron-left"></i>
                </button>
            `;
        }

        // Page numbers
        const maxVisible = 5;
        let startPage = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
        let endPage = Math.min(pagination.total_pages, startPage + maxVisible - 1);
        
        if (endPage - startPage + 1 < maxVisible) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        for (let i = startPage; i <= endPage; i++) {
            buttons += `
                <button class="page-btn ${i === this.currentPage ? 'active' : ''}" 
                        data-page="${i}">
                    ${i}
                </button>
            `;
        }

        // Next button
        if (this.currentPage < pagination.total_pages) {
            buttons += `
                <button class="page-btn" data-page="${this.currentPage + 1}">
                    <i class="fas fa-chevron-right"></i>
                </button>
            `;
        }

        container.innerHTML = buttons;

        // Add event listeners
        document.querySelectorAll('.page-btn[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                const page = parseInt(btn.dataset.page);
                if (page !== this.currentPage) {
                    this.currentPage = page;
                    this.loadWithdrawals();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        });
    }

    updateSummaryUI(summary) {
        document.getElementById('walletBalance').textContent = summary.formatted_wallet_balance;
        document.getElementById('totalWithdrawals').textContent = summary.total_withdrawals;
        document.getElementById('totalAmount').textContent = summary.formatted_total_amount;
        
        document.getElementById('pendingCount').textContent = summary.pending.count;
        document.getElementById('pendingAmount').textContent = summary.pending.formatted_amount;
        
        document.getElementById('completedCount').textContent = summary.completed.count;
        document.getElementById('completedAmount').textContent = summary.completed.formatted_amount;
        
        document.getElementById('failedCount').textContent = summary.failed.count;
        document.getElementById('failedAmount').textContent = summary.failed.formatted_amount;
        
        document.getElementById('lastWithdrawal').textContent = summary.last_withdrawal_formatted;
    }

    updateTableInfo(pagination) {
        const start = ((pagination.page - 1) * pagination.limit) + 1;
        const end = Math.min(pagination.page * pagination.limit, pagination.total);
        document.getElementById('showingInfo').textContent = 
            `Showing ${start} to ${end} of ${pagination.total} entries`;
    }

    showDetailsModal(withdrawal) {
        const detailsContent = document.getElementById('detailsContent');
        
        detailsContent.innerHTML = `
            <div class="detail-section">
                <h4>Transaction Information</h4>
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
                        <span class="status-badge status-${withdrawal.status}">${withdrawal.status_text}</span>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Note:</span>
                    <span class="detail-value">${withdrawal.note || 'No note provided'}</span>
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
                ${withdrawal.processed_at_formatted ? `
                <div class="detail-row">
                    <span class="detail-label">Processed:</span>
                    <span class="detail-value">${withdrawal.processed_at_formatted}</span>
                </div>
                ` : ''}
                ${withdrawal.processed_by_name ? `
                <div class="detail-row">
                    <span class="detail-label">Processed By:</span>
                    <span class="detail-value">${withdrawal.processed_by_name}</span>
                </div>
                ` : ''}
            </div>
            
            ${withdrawal.admin_notes ? `
            <div class="detail-section">
                <h4>Admin Notes</h4>
                <div class="admin-notes">
                    ${withdrawal.admin_notes}
                </div>
            </div>
            ` : ''}
        `;
        
        this.showModal('detailsModal');
    }

    showCancelModal(withdrawalId, reference, amount) {
        this.currentWithdrawalId = withdrawalId;
        document.getElementById('cancelWithdrawalInfo').innerHTML = `
            <div class="info-row">
                <span>Reference:</span>
                <strong>${reference}</strong>
            </div>
            <div class="info-row">
                <span>Amount:</span>
                <strong>${amount}</strong>
            </div>
        `;
        document.getElementById('cancelReason').value = '';
        document.getElementById('cancelSecretAnswer').value = '';
        this.showModal('cancelModal');
    }

    confirmCancel() {
        const secretAnswer = document.getElementById('cancelSecretAnswer').value;
        const reason = document.getElementById('cancelReason').value;
        
        if (!secretAnswer) {
            this.showToast('error', 'Secret answer is required');
            return;
        }
        
        this.cancelWithdrawal(this.currentWithdrawalId, secretAnswer, reason);
    }

    getStatusClass(status) {
        const classes = {
            'pending': 'status-pending',
            'processing': 'status-processing',
            'completed': 'status-completed',
            'failed': 'status-failed',
            'cancelled': 'status-cancelled'
        };
        return classes[status] || '';
    }

    setupEventListeners() {
        // Refresh button
        document.getElementById('refreshBtn').addEventListener('click', () => {
            this.showToast('info', 'Refreshing...');
            this.loadSummary();
            this.loadWithdrawals();
        });

        // Apply filters
        document.getElementById('applyFiltersBtn').addEventListener('click', () => {
            this.filters = {
                status: document.getElementById('statusFilter').value,
                from_date: document.getElementById('dateFrom').value,
                to_date: document.getElementById('dateTo').value,
                search: document.getElementById('searchFilter').value
            };
            this.currentPage = 1;
            this.loadWithdrawals();
        });

        // Clear filters
        document.getElementById('clearFiltersBtn').addEventListener('click', () => {
            document.getElementById('statusFilter').value = '';
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            document.getElementById('searchFilter').value = '';
            this.filters = { status: '', from_date: '', to_date: '', search: '' };
            this.currentPage = 1;
            this.loadWithdrawals();
        });

        // Search on enter
        document.getElementById('searchFilter').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.filters.search = e.target.value;
                this.currentPage = 1;
                this.loadWithdrawals();
            }
        });
    }

    setupModals() {
        // Close modals on outside click
        const modals = ['detailsModal', 'cancelModal', 'successModal', 'errorModal'];
        modals.forEach(modalId => {
            const modal = document.getElementById(modalId);
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.hideModal(modalId);
                }
            });
        });
    }

    showModal(modalId) {
        document.getElementById(modalId).classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    hideModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
        document.body.style.overflow = '';
    }

    closeDetailsModal() {
        this.hideModal('detailsModal');
    }

    closeCancelModal() {
        this.hideModal('cancelModal');
        this.currentWithdrawalId = null;
    }

    closeSuccessModal() {
        this.hideModal('successModal');
    }

    closeErrorModal() {
        this.hideModal('errorModal');
    }

    showSuccess(title, message, details = '') {
        document.getElementById('successTitle').textContent = title;
        document.getElementById('successMessage').textContent = message;
        document.getElementById('successDetails').textContent = details;
        this.showModal('successModal');
    }

    showError(message) {
        document.getElementById('errorMessage').textContent = message;
        this.showModal('errorModal');
    }

    showToast(type, message) {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        
        toast.innerHTML = `
            <i class="fas fa-${icons[type] || 'info-circle'}"></i>
            <span>${message}</span>
        `;
        
        container.appendChild(toast);
        
        setTimeout(() => toast.classList.add('show'), 10);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    showLoading() {
        document.getElementById('loadingRow').style.display = '';
    }

    hideLoading() {
        // Loading is hidden by render functions
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.withdrawalManager = new DriverWithdrawalManager();
});

// Global functions for onclick handlers
function closeDetailsModal() { withdrawalManager.closeDetailsModal(); }
function closeCancelModal() { withdrawalManager.closeCancelModal(); }
function closeSuccessModal() { withdrawalManager.closeSuccessModal(); }
function closeErrorModal() { withdrawalManager.closeErrorModal(); }
function confirmCancel() { withdrawalManager.confirmCancel(); }