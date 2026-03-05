class BankUploadManager {
    constructor() {
        this.apiUrl = '../transaction_manager/backend/bank_upload.php';
        this.selectedFile = null;
        this.importInProgress = false;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupDragAndDrop();
    }

    setupEventListeners() {
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const browseBtn = document.getElementById('browseBtn');
        const removeFileBtn = document.getElementById('removeFile');
        const cancelBtn = document.getElementById('cancelBtn');
        const importBtn = document.getElementById('importBtn');
        const closeResults = document.getElementById('closeResults');
        const viewBanksBtn = document.getElementById('viewBanksBtn');
        const importAnotherBtn = document.getElementById('importAnotherBtn');
        const tryAgainBtn = document.getElementById('tryAgainBtn');
        const toggleSample = document.getElementById('toggleSample');

        // Browse files
        browseBtn.addEventListener('click', () => {
            fileInput.click();
        });

        // File selection
        fileInput.addEventListener('change', (e) => {
            this.handleFileSelect(e.target.files[0]);
        });

        // Remove file
        removeFileBtn.addEventListener('click', () => {
            this.resetUpload();
        });

        // Cancel import
        cancelBtn.addEventListener('click', () => {
            this.resetUpload();
        });

        // Start import
        importBtn.addEventListener('click', () => {
            this.startImport();
        });

        // Close results
        closeResults.addEventListener('click', () => {
            document.getElementById('resultsCard').style.display = 'none';
        });

        // View banks
        viewBanksBtn.addEventListener('click', () => {
            window.location.href = 'bank_management.php';
        });

        // Import another
        importAnotherBtn.addEventListener('click', () => {
            this.resetUpload();
            document.getElementById('resultsCard').style.display = 'none';
        });

        // Try again
        tryAgainBtn.addEventListener('click', () => {
            document.getElementById('errorCard').style.display = 'none';
            this.resetUpload();
        });

        // Toggle sample
        toggleSample.addEventListener('click', () => {
            const sampleBody = document.getElementById('sampleBody');
            if (sampleBody.style.display === 'none') {
                sampleBody.style.display = 'block';
                toggleSample.textContent = 'Hide Sample';
            } else {
                sampleBody.style.display = 'none';
                toggleSample.textContent = 'Show Sample';
            }
        });
    }

    setupDragAndDrop() {
        const uploadArea = document.getElementById('uploadArea');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.classList.remove('dragover');
            });
        });

        uploadArea.addEventListener('drop', (e) => {
            const file = e.dataTransfer.files[0];
            this.handleFileSelect(file);
        });
    }

    handleFileSelect(file) {
        if (!file) return;

        // Validate file type
        if (!file.name.endsWith('.json')) {
            this.showToast('error', 'Please select a JSON file');
            return;
        }

        // Validate file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            this.showToast('error', 'File size must be less than 5MB');
            return;
        }

        this.selectedFile = file;

        // Update UI
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileSize').textContent = this.formatFileSize(file.size);
        document.getElementById('selectedFile').style.display = 'flex';
        document.getElementById('importOptions').style.display = 'block';
        document.getElementById('actionButtons').style.display = 'flex';
        
        // Hide upload area
        document.querySelector('.upload-area').style.display = 'none';
    }

    resetUpload() {
        this.selectedFile = null;
        document.getElementById('fileInput').value = '';

        // Reset UI
        document.querySelector('.upload-area').style.display = 'block';
        document.getElementById('selectedFile').style.display = 'none';
        document.getElementById('importOptions').style.display = 'none';
        document.getElementById('actionButtons').style.display = 'none';
        document.getElementById('progressSection').style.display = 'none';
        
        // Reset progress
        document.getElementById('progressBar').style.width = '0%';
        document.getElementById('progressPercentage').textContent = '0%';
        document.getElementById('processedCount').textContent = '0';
        document.getElementById('successCount').textContent = '0';
        document.getElementById('errorCount').textContent = '0';
    }

    async startImport() {
        if (!this.selectedFile || this.importInProgress) return;

        this.importInProgress = true;

        // Show progress section
        document.getElementById('progressSection').style.display = 'block';
        document.getElementById('actionButtons').style.display = 'none';
        document.getElementById('importOptions').style.display = 'none';

        // Prepare form data
        const formData = new FormData();
        formData.append('file', this.selectedFile);
        formData.append('truncate', document.getElementById('truncateExisting').checked ? '1' : '0');
        formData.append('update_existing', document.getElementById('updateExisting').checked ? '1' : '0');
        formData.append('create_table', document.getElementById('createTable').checked ? '1' : '0');

        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showResults(result);
            } else {
                this.showError(result.message || 'Import failed');
            }
        } catch (error) {
            console.error('Import error:', error);
            this.showError('Network error. Please try again.');
        } finally {
            this.importInProgress = false;
            document.getElementById('progressSection').style.display = 'none';
        }
    }

    showResults(result) {
        const resultsBody = document.getElementById('resultsBody');
        
        // Calculate statistics
        const successRate = ((result.stats.successful / result.stats.total) * 100).toFixed(1);
        
        resultsBody.innerHTML = `
            <div class="result-item">
                <div class="result-icon success">
                    <i class="fas fa-check"></i>
                </div>
                <div class="result-content">
                    <div class="result-title">Import Completed Successfully</div>
                    <div class="result-desc">${result.message || 'Bank data has been imported'}</div>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-box">
                    <span class="stat-value">${result.stats.total}</span>
                    <span class="stat-label">Total Banks</span>
                </div>
                <div class="stat-box">
                    <span class="stat-value">${result.stats.successful}</span>
                    <span class="stat-label">Imported</span>
                </div>
                <div class="stat-box">
                    <span class="stat-value">${result.stats.skipped || 0}</span>
                    <span class="stat-label">Skipped</span>
                </div>
                <div class="stat-box">
                    <span class="stat-value">${result.stats.errors || 0}</span>
                    <span class="stat-label">Errors</span>
                </div>
            </div>
            
            <div class="result-item">
                <div class="result-content">
                    <div class="result-title">Success Rate</div>
                    <div class="result-desc">${successRate}% of banks imported successfully</div>
                </div>
            </div>
            
            ${result.stats.errors_list && result.stats.errors_list.length > 0 ? `
                <div class="result-item">
                    <div class="result-icon error">
                        <i class="fas fa-exclamation"></i>
                    </div>
                    <div class="result-content">
                        <div class="result-title">Errors Encountered</div>
                        <div class="result-desc">
                            <ul style="margin-top: 5px; padding-left: 20px;">
                                ${result.stats.errors_list.map(err => `<li>${err}</li>`).join('')}
                            </ul>
                        </div>
                    </div>
                </div>
            ` : ''}
        `;

        document.getElementById('resultsCard').style.display = 'block';
    }

    showError(message) {
        document.getElementById('errorBody').innerHTML = `
            <p><strong>Error:</strong> ${message}</p>
            <p style="margin-top: 10px; font-size: 13px;">Please check your JSON file format and try again.</p>
        `;
        document.getElementById('errorCard').style.display = 'block';
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
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
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new BankUploadManager();
});