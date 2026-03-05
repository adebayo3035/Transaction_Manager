class BankManager {
    constructor() {
        this.banks = [];
        this.selectedBanks = new Set();
        this.currentPage = 1;
        this.limit = 20;
        this.searchTerm = '';
        this.showInactive = false;
        this.sortBy = 'name_asc';
        // Fix the API URL to point to the correct endpoint
        this.apiUrl = '../transaction_manager/backend/bankapi.php';
        
        this.init();
    }

    async init() {
        await this.loadBanks();
        this.setupEventListeners();
        this.setupModals();
        this.updateStats();
    }

    async loadBanks() {
        const loadingRow = document.getElementById('loadingRow');
        const emptyState = document.getElementById('emptyState');
        const tableBody = document.getElementById('banksTableBody');
        const paginationContainer = document.getElementById('pagination');
        
        if (loadingRow) loadingRow.style.display = '';
        if (emptyState) emptyState.style.display = 'none';
        
        try {
            const params = new URLSearchParams({
                action: 'list',
                page: this.currentPage,
                limit: this.limit,
                search: this.searchTerm,
                show_inactive: this.showInactive
            });
            
            const response = await fetch(`${this.apiUrl}?${params}`);
            const data = await response.json();
            
            if (data.success) {
                this.banks = data.banks;
                this.pagination = data.pagination;
                this.bank_stats = data.bank_stats;
                // this.driverBankCount = data.driver_bank_stats?.distinct_banks_used || 0;
                this.renderBanks(data.banks);
                
                // Always render pagination, even if total_pages is 1 (the method handles it)
                this.renderPagination(data.pagination);
                
                if (data.banks.length === 0) {
                    if (loadingRow) loadingRow.style.display = 'none';
                    if (emptyState) emptyState.style.display = 'block';
                } else {
                    if (loadingRow) loadingRow.style.display = 'none';
                }
                
                this.updateStats();
            } else {
                this.showToast('error', data.message || 'Failed to load banks');
                if (loadingRow) loadingRow.style.display = 'none';
                if (emptyState) emptyState.style.display = 'block';
            }
        } catch (error) {
            console.error('Error loading banks:', error);
            this.showToast('error', 'Failed to load banks');
            if (loadingRow) loadingRow.style.display = 'none';
            if (emptyState) emptyState.style.display = 'block';
        }
    }

    renderBanks(banks) {
        const tableBody = document.getElementById('banksTableBody');
        if (!tableBody) return;
        
        // Clear existing rows
        Array.from(tableBody.children).forEach(row => {
            if (row.id !== 'loadingRow') {
                row.remove();
            }
        });

        banks.forEach(bank => {
            const row = document.createElement('tr');
            row.dataset.bankId = bank.id;
            
            const statusClass = bank.is_active ? 'status-active' : 'status-inactive';
            const statusText = bank.is_active ? 'Active' : 'Inactive';
            
            // Format additional fields
            const supportsTransfer = bank.supports_transfer ? '✓' : '✗';
            const payWithBank = bank.pay_with_bank ? '✓' : '✗';
            
            row.innerHTML = `
                <td>
                    <input type="checkbox" class="bank-checkbox" value="${bank.id}">
                </td>
                <td><span class="badge badge-code">${bank.bank_code}</span></td>
                <td><strong>${this.escapeHtml(bank.bank_name)}</strong></td>
                <td>${bank.longcode || '-'}</td>
                <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                <td>${this.formatDate(bank.created_at)}</td>
                <td>${this.formatDate(bank.updated_at)}</td>
                <td>
                    <div class="action-btns">
                        <button class="btn-icon view" onclick="bankManager.viewBank(${bank.id})" title="View">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn-icon edit" onclick="bankManager.editBank(${bank.id})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-icon delete" onclick="bankManager.deleteBank(${bank.id})" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            
            tableBody.appendChild(row);
        });

        // Add event listeners to checkboxes
        this.setupCheckboxListeners();
    }

    renderPagination(pagination) {
        const container = document.getElementById('pagination');
        if (!container) {
            console.error('Pagination container not found');
            return;
        }

        // Always show pagination container
        container.innerHTML = '';

        // If no pagination data or total_pages <= 1, show simple message
        if (!pagination || pagination.total_pages <= 1) {
            if (pagination && pagination.total > 0) {
                container.innerHTML = `
                    <div class="page-info">
                        Showing all ${pagination.total} banks
                    </div>
                `;
            }
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
        } else {
            buttons += `
                <button class="page-btn disabled" disabled>
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
        } else {
            buttons += `
                <button class="page-btn disabled" disabled>
                    <i class="fas fa-chevron-right"></i>
                </button>
            `;
        }

        // Page info
        const pageInfo = `
            <div class="page-info">
                Page ${this.currentPage} of ${pagination.total_pages} (${pagination.total} banks)
            </div>
        `;

        container.innerHTML = buttons + pageInfo;

        // Add event listeners to page buttons
        document.querySelectorAll('.page-btn[data-page]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const page = parseInt(e.target.dataset.page);
                if (page && page !== this.currentPage) {
                    this.currentPage = page;
                    this.loadBanks();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        });
    }

    setupCheckboxListeners() {
        document.querySelectorAll('.bank-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                const bankId = e.target.value;
                if (e.target.checked) {
                    this.selectedBanks.add(bankId);
                } else {
                    this.selectedBanks.delete(bankId);
                }
                this.updateSelectedCount();
            });
        });
    }

    updateSelectedCount() {
        const count = this.selectedBanks.size;
        const selectedCountEl = document.getElementById('selectedCount');
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
        
        if (selectedCountEl) {
            selectedCountEl.textContent = `${count} selected`;
        }
        
        if (bulkDeleteBtn) {
            bulkDeleteBtn.disabled = count === 0;
        }
        
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            const totalCheckboxes = document.querySelectorAll('.bank-checkbox').length;
            selectAll.checked = count === totalCheckboxes && totalCheckboxes > 0;
        }
    }

    updateStats() {
        const total = this.bank_stats.total;
        // const active = this.banks.filter(b => b.is_active).length;
        const active = this.bank_stats.active
        const inactive = this.bank_stats.inactive
        const driverBankCount = this.bank_stats.used_by_drivers
        
        const totalEl = document.getElementById('totalBanks');
        const activeEl = document.getElementById('activeBanks');
        const inactiveEl = document.getElementById('inactiveBanks');
        const usedEl = document.getElementById('usedBanks');
        
        if (totalEl) totalEl.textContent = total;
        if (activeEl) activeEl.textContent = active;
        if (inactiveEl) inactiveEl.textContent = inactive;
        if (usedEl) usedEl.textContent = driverBankCount || 0;
    }

    setupEventListeners() {
        // Search
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', () => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.searchTerm = searchInput.value.trim();
                    this.currentPage = 1;
                    this.loadBanks();
                }, 500);
            });
        }

        // Show inactive checkbox
        const showInactiveCheckbox = document.getElementById('showInactive');
        if (showInactiveCheckbox) {
            showInactiveCheckbox.addEventListener('change', (e) => {
                this.showInactive = e.target.checked;
                this.currentPage = 1;
                this.loadBanks();
            });
        }

        // Sort
        const sortBySelect = document.getElementById('sortBy');
        if (sortBySelect) {
            sortBySelect.addEventListener('change', (e) => {
                this.sortBy = e.target.value;
                this.sortBanks();
            });
        }

        // Select all
        const selectAllCheckbox = document.getElementById('selectAll');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', (e) => {
                const isChecked = e.target.checked;
                document.querySelectorAll('.bank-checkbox').forEach(checkbox => {
                    checkbox.checked = isChecked;
                    const bankId = checkbox.value;
                    if (isChecked) {
                        this.selectedBanks.add(bankId);
                    } else {
                        this.selectedBanks.delete(bankId);
                    }
                });
                this.updateSelectedCount();
            });
        }

        // Refresh button
        const refreshBtn = document.getElementById('refreshBtn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.loadBanks();
                this.showToast('success', 'Banks list refreshed');
            });
        }

        // Add bank button
        const addBankBtn = document.getElementById('addBankBtn');
        if (addBankBtn) {
            addBankBtn.addEventListener('click', () => {
                this.openAddModal();
            });
        }

        // Bulk delete
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
        if (bulkDeleteBtn) {
            bulkDeleteBtn.addEventListener('click', () => {
                this.openBulkDeleteModal();
            });
        }
    }

    setupModals() {
        // Bank modal
        const bankModal = document.getElementById('bankModal');
        if (bankModal) {
            const closeBankModal = bankModal.querySelector('.close-modal');
            const cancelBankBtn = document.getElementById('cancelBankBtn');
            const saveBankBtn = document.getElementById('saveBankBtn');

            if (closeBankModal) {
                closeBankModal.addEventListener('click', () => this.hideModal('bankModal'));
            }
            
            if (cancelBankBtn) {
                cancelBankBtn.addEventListener('click', () => this.hideModal('bankModal'));
            }

            if (saveBankBtn) {
                saveBankBtn.addEventListener('click', () => this.saveBank());
            }
        }

        // Delete modal
        const deleteModal = document.getElementById('deleteModal');
        if (deleteModal) {
            const closeDeleteModal = deleteModal.querySelector('.close-modal');
            const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

            if (closeDeleteModal) {
                closeDeleteModal.addEventListener('click', () => this.hideModal('deleteModal'));
            }
            
            if (cancelDeleteBtn) {
                cancelDeleteBtn.addEventListener('click', () => this.hideModal('deleteModal'));
            }

            if (confirmDeleteBtn) {
                confirmDeleteBtn.addEventListener('click', () => this.confirmDelete());
            }
        }

        // View modal
        const viewModal = document.getElementById('viewModal');
        if (viewModal) {
            const closeViewModal = viewModal.querySelector('.close-modal');
            if (closeViewModal) {
                closeViewModal.addEventListener('click', () => this.hideModal('viewModal'));
            }
        }

        // Close on outside click
        [bankModal, deleteModal, viewModal].forEach(modal => {
            if (modal) {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        this.hideModal(modal.id);
                    }
                });
            }
        });

        this.bankModal = bankModal;
        this.deleteModal = deleteModal;
        this.viewModal = viewModal;
    }

    openAddModal() {
        const modalTitle = document.getElementById('modalTitle');
        if (modalTitle) {
            modalTitle.innerHTML = '<i class="fas fa-plus-circle"></i> Add New Bank';
        }
        
        const bankForm = document.getElementById('bankForm');
        if (bankForm) {
            bankForm.reset();
        }
        
        const bankId = document.getElementById('bankId');
        if (bankId) {
            bankId.value = '';
        }
        
        const bankStatus = document.getElementById('bankStatus');
        if (bankStatus) {
            bankStatus.value = '1';
        }
        
        this.showModal('bankModal');
    }

    async editBank(bankId) {
        try {
            const response = await fetch(`${this.apiUrl}?action=get&bank_id=${bankId}`);
            const data = await response.json();
            
            if (data.success) {
                const bank = data.bank;
                const modalTitle = document.getElementById('modalTitle');
                if (modalTitle) {
                    modalTitle.innerHTML = '<i class="fas fa-edit"></i> Edit Bank';
                }
                
                const bankIdField = document.getElementById('bankId');
                if (bankIdField) {
                    bankIdField.value = bank.id;
                }
                
                const bankCodeField = document.getElementById('bankCode');
                if (bankCodeField) {
                    bankCodeField.value = bank.bank_code;
                }
                
                const bankNameField = document.getElementById('bankName');
                if (bankNameField) {
                    bankNameField.value = bank.bank_name;
                }
                
                const sortCodeField = document.getElementById('sortCode');
                if (sortCodeField) {
                    sortCodeField.value = bank.longcode || '';
                }
                
                const bankStatusField = document.getElementById('bankStatus');
                if (bankStatusField) {
                    bankStatusField.value = bank.is_active ? '1' : '0';
                }
                
                this.showModal('bankModal');
            } else {
                this.showToast('error', 'Failed to load bank details');
            }
        } catch (error) {
            console.error('Error loading bank:', error);
            this.showToast('error', 'Failed to load bank details');
        }
    }

    async viewBank(bankId) {
        try {
            const response = await fetch(`${this.apiUrl}?action=get&bank_id=${bankId}`);
            const data = await response.json();
            
            if (data.success) {
                const bank = data.bank;
                
                const viewId = document.getElementById('viewId');
                if (viewId) viewId.textContent = bank.id;
                
                const viewCode = document.getElementById('viewCode');
                if (viewCode) viewCode.textContent = bank.bank_code;
                
                const viewName = document.getElementById('viewName');
                if (viewName) viewName.textContent = bank.bank_name;
                
                const viewLongcode = document.getElementById('viewLongcode');
                if (viewLongcode) viewLongcode.textContent = bank.longcode || '-';
                
                const viewSlug = document.getElementById('viewSlug');
                if (viewSlug) viewSlug.textContent = bank.slug || '-';
                
                const viewGateway = document.getElementById('viewGateway');
                if (viewGateway) viewGateway.textContent = bank.gateway || '-';
                
                const viewCountry = document.getElementById('viewCountry');
                if (viewCountry) viewCountry.textContent = bank.country || 'Nigeria';
                
                const viewCurrency = document.getElementById('viewCurrency');
                if (viewCurrency) viewCurrency.textContent = bank.currency || 'NGN';
                
                const viewType = document.getElementById('viewType');
                if (viewType) viewType.textContent = bank.type || 'nuban';
                
                const statusClass = bank.is_active ? 'status-active' : 'status-inactive';
                const statusText = bank.is_active ? 'Active' : 'Inactive';
                
                const viewStatus = document.getElementById('viewStatus');
                if (viewStatus) {
                    viewStatus.innerHTML = `<span class="status-badge ${statusClass}">${statusText}</span>`;
                }
                
                const viewCreated = document.getElementById('viewCreated');
                if (viewCreated) {
                    viewCreated.textContent = this.formatDateTime(bank.created_at);
                }
                
                const viewUpdated = document.getElementById('viewUpdated');
                if (viewUpdated) {
                    viewUpdated.textContent = this.formatDateTime(bank.updated_at);
                }
                
                this.showModal('viewModal');
            } else {
                this.showToast('error', 'Failed to load bank details');
            }
        } catch (error) {
            console.error('Error loading bank:', error);
            this.showToast('error', 'Failed to load bank details');
        }
    }

    async saveBank() {
        const bankId = document.getElementById('bankId')?.value;
        const bankCode = document.getElementById('bankCode')?.value.trim().toUpperCase();
        const bankName = document.getElementById('bankName')?.value.trim();
        const longcode = document.getElementById('sortCode')?.value.trim();
        const isActive = parseInt(document.getElementById('bankStatus')?.value || '1');

        // Validate
        if (!bankCode) {
            this.showToast('error', 'Bank code is required');
            return;
        }
        if (!bankName) {
            this.showToast('error', 'Bank name is required');
            return;
        }

        const bankData = {
            bank_code: bankCode,
            bank_name: bankName,
            longcode: longcode || '',
            is_active: isActive
        };

        const saveBtn = document.getElementById('saveBankBtn');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        }

        try {
            let response;
            if (bankId) {
                // Update
                response = await fetch(this.apiUrl, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update',
                        bank_id: parseInt(bankId),
                        ...bankData
                    })
                });
            } else {
                // Create
                response = await fetch(this.apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'create',
                        ...bankData
                    })
                });
            }

            const data = await response.json();
            
            if (data.success) {
                this.showToast('success', data.message);
                this.hideModal('bankModal');
                this.loadBanks();
            } else {
                this.showToast('error', data.message || 'Failed to save bank');
            }
        } catch (error) {
            console.error('Error saving bank:', error);
            this.showToast('error', 'Failed to save bank');
        } finally {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Bank';
            }
        }
    }

    deleteBank(bankId) {
        const bank = this.banks.find(b => b.id == bankId);
        if (!bank) return;

        this.selectedBanks.clear();
        this.selectedBanks.add(bankId.toString());
        
        const deleteMessage = document.getElementById('deleteMessage');
        if (deleteMessage) {
            deleteMessage.textContent = `Are you sure you want to delete "${bank.bank_name}"?`;
        }
        
        const banksToDelete = document.getElementById('banksToDelete');
        if (banksToDelete) {
            banksToDelete.innerHTML = `
                <div class="bank-item">
                    <strong>${bank.bank_code}</strong> - ${bank.bank_name}
                </div>
            `;
        }
        
        this.showModal('deleteModal');
    }

    openBulkDeleteModal() {
        if (this.selectedBanks.size === 0) {
            this.showToast('warning', 'No banks selected');
            return;
        }

        const selectedBanks = this.banks.filter(b => this.selectedBanks.has(b.id.toString()));
        
        const deleteMessage = document.getElementById('deleteMessage');
        if (deleteMessage) {
            deleteMessage.textContent = `Are you sure you want to delete ${selectedBanks.length} bank(s)?`;
        }
        
        const banksList = selectedBanks.map(bank => `
            <div class="bank-item">
                <strong>${bank.bank_code}</strong> - ${bank.bank_name}
            </div>
        `).join('');
        
        const banksToDelete = document.getElementById('banksToDelete');
        if (banksToDelete) {
            banksToDelete.innerHTML = banksList;
        }
        
        this.showModal('deleteModal');
    }

    async confirmDelete() {
        const deleteBtn = document.getElementById('confirmDeleteBtn');
        if (deleteBtn) {
            deleteBtn.disabled = true;
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
        }

        let successCount = 0;
        let failCount = 0;

        for (const bankId of this.selectedBanks) {
            try {
                const response = await fetch(this.apiUrl, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ bank_id: parseInt(bankId) })
                });
                
                const data = await response.json();
                if (data.success) {
                    successCount++;
                } else {
                    failCount++;
                }
            } catch (error) {
                console.error('Error deleting bank:', error);
                failCount++;
            }
        }

        this.selectedBanks.clear();
        this.hideModal('deleteModal');
        
        if (successCount > 0) {
            this.showToast('success', `Successfully deleted ${successCount} bank(s)`);
            this.loadBanks();
        }
        if (failCount > 0) {
            this.showToast('error', `Failed to delete ${failCount} bank(s)`);
        }

        if (deleteBtn) {
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = '<i class="fas fa-trash"></i> Delete';
        }
    }

    sortBanks() {
        const [field, order] = this.sortBy.split('_');
        
        this.banks.sort((a, b) => {
            let valA, valB;
            
            if (field === 'name') {
                valA = a.bank_name.toLowerCase();
                valB = b.bank_name.toLowerCase();
            } else if (field === 'code') {
                valA = a.bank_code;
                valB = b.bank_code;
            } else if (field === 'newest') {
                valA = new Date(a.created_at);
                valB = new Date(b.created_at);
                return valB - valA;
            } else if (field === 'oldest') {
                valA = new Date(a.created_at);
                valB = new Date(b.created_at);
                return valA - valB;
            }
            
            if (order === 'asc') {
                return valA < valB ? -1 : valA > valB ? 1 : 0;
            } else {
                return valA > valB ? -1 : valA < valB ? 1 : 0;
            }
        });
        
        this.renderBanks(this.banks);
    }

    showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }

    hideModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }

    closeViewModal() {
        this.hideModal('viewModal');
    }

    formatDate(dateString) {
        if (!dateString) return '-';
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString();
        } catch (e) {
            return '-';
        }
    }

    formatDateTime(dateString) {
        if (!dateString) return '-';
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        } catch (e) {
            return '-';
        }
    }

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showToast(type, message) {
        // Check if toast container exists, create if not
        let toastContainer = document.getElementById('toastContainer');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toastContainer';
            toastContainer.className = 'toast-container';
            document.body.appendChild(toastContainer);
        }
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle'
        };
        
        toast.innerHTML = `
            <i class="fas fa-${icons[type] || 'info-circle'}"></i>
            <span>${message}</span>
        `;
        
        toastContainer.appendChild(toast);
        
        // Trigger animation
        setTimeout(() => toast.classList.add('show'), 10);
        
        // Remove after 3 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
                // Remove container if empty
                if (toastContainer.children.length === 0) {
                    toastContainer.remove();
                }
            }, 300);
        }, 3000);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Check if we're on the bank management page
    if (document.getElementById('banksTableBody')) {
        window.bankManager = new BankManager();
    }
});

// Add toast styles if not already present
if (!document.querySelector('#toast-styles')) {
    const style = document.createElement('style');
    style.id = 'toast-styles';
    style.textContent = `
        .toast-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 350px;
            width: 100%;
            pointer-events: none;
        }
        
        .toast {
            background: white;
            border-radius: 8px;
            padding: 15px 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            transform: translateX(400px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.35);
            pointer-events: auto;
            border-left: 4px solid transparent;
        }
        
        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        .toast-success {
            border-left-color: #28a745;
        }
        
        .toast-success i {
            color: #28a745;
        }
        
        .toast-error {
            border-left-color: #dc3545;
        }
        
        .toast-error i {
            color: #dc3545;
        }
        
        .toast-warning {
            border-left-color: #ffc107;
        }
        
        .toast-warning i {
            color: #ffc107;
        }
        
        .toast i {
            font-size: 20px;
        }
        
        .toast span {
            flex: 1;
            font-size: 14px;
            color: #333;
        }
        
        .page-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .badge-code {
            background: #e9ecef;
            padding: 3px 8px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
        }
    `;
    document.head.appendChild(style);
}