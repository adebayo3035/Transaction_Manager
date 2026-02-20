class BankManager {
    constructor() {
        this.banks = [];
        this.selectedBanks = new Set();
        this.currentPage = 1;
        this.limit = 20;
        this.searchTerm = '';
        this.showInactive = false;
        this.sortBy = 'name_asc';
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
        
        loadingRow.style.display = '';
        emptyState.style.display = 'none';
        
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
                this.renderBanks(data.banks);
                this.renderPagination(data.pagination);
                
                if (data.banks.length === 0) {
                    loadingRow.style.display = 'none';
                    emptyState.style.display = 'block';
                } else {
                    loadingRow.style.display = 'none';
                }
                
                this.updateStats();
            } else {
                this.showToast('error', data.message || 'Failed to load banks');
            }
        } catch (error) {
            console.error('Error loading banks:', error);
            this.showToast('error', 'Failed to load banks');
            loadingRow.style.display = 'none';
            emptyState.style.display = 'block';
        }
    }

    renderBanks(banks) {
        const tableBody = document.getElementById('banksTableBody');
        
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
            
            row.innerHTML = `
                <td>
                    <input type="checkbox" class="bank-checkbox" value="${bank.id}">
                </td>
                <td><span class="badge badge-code">${bank.bank_code}</span></td>
                <td><strong>${this.escapeHtml(bank.bank_name)}</strong></td>
                <td>${bank.sort_code || '-'}</td>
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

        // Page info
        const pageInfo = `
            <div class="page-info">
                Page ${this.currentPage} of ${pagination.total_pages} (${pagination.total} banks)
            </div>
        `;

        container.innerHTML = buttons + pageInfo;

        // Add event listeners
        document.querySelectorAll('.page-btn[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                const page = parseInt(btn.dataset.page);
                if (page !== this.currentPage) {
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
        document.getElementById('selectedCount').textContent = `${count} selected`;
        document.getElementById('bulkDeleteBtn').disabled = count === 0;
        
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.checked = count === document.querySelectorAll('.bank-checkbox').length;
        }
    }

    updateStats() {
        const total = this.banks.length;
        const active = this.banks.filter(b => b.is_active).length;
        const inactive = total - active;
        
        document.getElementById('totalBanks').textContent = total;
        document.getElementById('activeBanks').textContent = active;
        document.getElementById('inactiveBanks').textContent = inactive;
        // document.getElementById('usedBanks').textContent = '?'; // Implement if needed
    }

    setupEventListeners() {
        // Search
        const searchInput = document.getElementById('searchInput');
        let searchTimeout;
        
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.searchTerm = searchInput.value.trim();
                this.currentPage = 1;
                this.loadBanks();
            }, 500);
        });

        // Show inactive checkbox
        document.getElementById('showInactive').addEventListener('change', (e) => {
            this.showInactive = e.target.checked;
            this.currentPage = 1;
            this.loadBanks();
        });

        // Sort
        document.getElementById('sortBy').addEventListener('change', (e) => {
            this.sortBy = e.target.value;
            this.sortBanks();
        });

        // Select all
        document.getElementById('selectAll').addEventListener('change', (e) => {
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

        // Refresh button
        document.getElementById('refreshBtn').addEventListener('click', () => {
            this.loadBanks();
            this.showToast('success', 'Banks list refreshed');
        });

        // Add bank button
        document.getElementById('addBankBtn').addEventListener('click', () => {
            this.openAddModal();
        });

        // Bulk delete
        document.getElementById('bulkDeleteBtn').addEventListener('click', () => {
            this.openBulkDeleteModal();
        });
    }

    setupModals() {
        // Bank modal
        const bankModal = document.getElementById('bankModal');
        const closeBankModal = bankModal.querySelector('.close-modal');
        const cancelBankBtn = document.getElementById('cancelBankBtn');
        const saveBankBtn = document.getElementById('saveBankBtn');

        [closeBankModal, cancelBankBtn].forEach(btn => {
            btn.addEventListener('click', () => this.hideModal('bankModal'));
        });

        saveBankBtn.addEventListener('click', () => this.saveBank());

        // Delete modal
        const deleteModal = document.getElementById('deleteModal');
        const closeDeleteModal = deleteModal.querySelector('.close-modal');
        const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

        [closeDeleteModal, cancelDeleteBtn].forEach(btn => {
            btn.addEventListener('click', () => this.hideModal('deleteModal'));
        });

        confirmDeleteBtn.addEventListener('click', () => this.confirmDelete());

        // View modal
        const viewModal = document.getElementById('viewModal');
        const closeViewModal = viewModal.querySelector('.close-modal');
        closeViewModal.addEventListener('click', () => this.hideModal('viewModal'));

        // Close on outside click
        [bankModal, deleteModal, viewModal].forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.hideModal(modal.id);
                }
            });
        });

        this.bankModal = bankModal;
        this.deleteModal = deleteModal;
        this.viewModal = viewModal;
    }

    openAddModal() {
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Add New Bank';
        document.getElementById('bankForm').reset();
        document.getElementById('bankId').value = '';
        document.getElementById('bankStatus').value = '1';
        this.showModal('bankModal');
    }

    async editBank(bankId) {
        try {
            const response = await fetch(`${this.apiUrl}?action=get&bank_id=${bankId}`);
            const data = await response.json();
            
            if (data.success) {
                const bank = data.bank;
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Bank';
                document.getElementById('bankId').value = bank.id;
                document.getElementById('bankCode').value = bank.bank_code;
                document.getElementById('bankName').value = bank.bank_name;
                document.getElementById('sortCode').value = bank.sort_code || '';
                document.getElementById('bankStatus').value = bank.is_active ? '1' : '0';
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
                document.getElementById('viewId').textContent = bank.id;
                document.getElementById('viewCode').textContent = bank.bank_code;
                document.getElementById('viewName').textContent = bank.bank_name;
                document.getElementById('viewSortCode').textContent = bank.sort_code || '-';
                
                const statusClass = bank.is_active ? 'status-active' : 'status-inactive';
                const statusText = bank.is_active ? 'Active' : 'Inactive';
                document.getElementById('viewStatus').innerHTML = `<span class="status-badge ${statusClass}">${statusText}</span>`;
                
                document.getElementById('viewCreated').textContent = this.formatDateTime(bank.created_at);
                document.getElementById('viewUpdated').textContent = this.formatDateTime(bank.updated_at);
                
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
        const bankId = document.getElementById('bankId').value;
        const bankData = {
            bank_code: document.getElementById('bankCode').value.trim().toUpperCase(),
            bank_name: document.getElementById('bankName').value.trim(),
            sort_code: document.getElementById('sortCode').value.trim(),
            is_active: parseInt(document.getElementById('bankStatus').value)
        };

        // Validate
        if (!bankData.bank_code) {
            this.showToast('error', 'Bank code is required');
            return;
        }
        if (!bankData.bank_name) {
            this.showToast('error', 'Bank name is required');
            return;
        }

        const saveBtn = document.getElementById('saveBankBtn');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

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
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Bank';
        }
    }

    deleteBank(bankId) {
        const bank = this.banks.find(b => b.id == bankId);
        if (!bank) return;

        this.selectedBanks.clear();
        this.selectedBanks.add(bankId.toString());
        
        document.getElementById('deleteMessage').textContent = `Are you sure you want to delete "${bank.bank_name}"?`;
        document.getElementById('banksToDelete').innerHTML = `
            <div class="bank-item">
                <strong>${bank.bank_code}</strong> - ${bank.bank_name}
            </div>
        `;
        
        this.showModal('deleteModal');
    }

    openBulkDeleteModal() {
        if (this.selectedBanks.size === 0) {
            this.showToast('warning', 'No banks selected');
            return;
        }

        const selectedBanks = this.banks.filter(b => this.selectedBanks.has(b.id.toString()));
        
        document.getElementById('deleteMessage').textContent = `Are you sure you want to delete ${selectedBanks.length} bank(s)?`;
        
        const banksList = selectedBanks.map(bank => `
            <div class="bank-item">
                <strong>${bank.bank_code}</strong> - ${bank.bank_name}
            </div>
        `).join('');
        
        document.getElementById('banksToDelete').innerHTML = banksList;
        this.showModal('deleteModal');
    }

    async confirmDelete() {
        const deleteBtn = document.getElementById('confirmDeleteBtn');
        deleteBtn.disabled = true;
        deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

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

        deleteBtn.disabled = false;
        deleteBtn.innerHTML = '<i class="fas fa-trash"></i> Delete';
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
        document.getElementById(modalId).classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    hideModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
        document.body.style.overflow = '';
    }

    closeViewModal() {
        this.hideModal('viewModal');
    }

    formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString();
    }

    formatDateTime(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showToast(type, message) {
        let toast = document.querySelector('.toast-notification');
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'toast-notification';
            document.body.appendChild(toast);
        }
        
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle'
        };
        
        toast.className = `toast-notification toast-${type}`;
        toast.innerHTML = `
            <i class="fas fa-${icons[type] || 'info-circle'}"></i>
            <span>${message}</span>
        `;
        
        toast.classList.add('show');
        
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.bankManager = new BankManager();
});

// Add toast styles
const style = document.createElement('style');
style.textContent = `
    .toast-notification {
        position: fixed;
        bottom: 30px;
        right: 30px;
        padding: 15px 25px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
        display: flex;
        align-items: center;
        gap: 10px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.3s;
        z-index: 2000;
    }
    
    .toast-notification.show {
        transform: translateY(0);
        opacity: 1;
    }
    
    .toast-success {
        border-left: 4px solid #28a745;
    }
    
    .toast-success i {
        color: #28a745;
    }
    
    .toast-error {
        border-left: 4px solid #dc3545;
    }
    
    .toast-error i {
        color: #dc3545;
    }
    
    .toast-warning {
        border-left: 4px solid #ffc107;
    }
    
    .toast-warning i {
        color: #ffc107;
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