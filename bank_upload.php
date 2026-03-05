<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Data Import</title>
    <link rel="stylesheet" href="css/bank_upload.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include('navbar.php'); ?>
    
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-left">
                <h1><i class="fas fa-university"></i> Bank Data Import</h1>
                <p>Upload and import bank data from JSON file</p>
            </div>
            <div class="header-right">
                <a href="banks.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Banks
                </a>
            </div>
        </div>

        <!-- Upload Card -->
        <div class="upload-card">
            <div class="upload-area" id="uploadArea">
                <i class="fas fa-cloud-upload-alt"></i>
                <h3>Drag & Drop JSON File</h3>
                <p>or</p>
                <button class="btn btn-primary" id="browseBtn">
                    <i class="fas fa-folder-open"></i> Browse Files
                </button>
                <input type="file" id="fileInput" accept=".json" style="display: none;">
                <p class="file-info">Supported format: .json (Max size: 5MB)</p>
            </div>

            <!-- Selected File Info -->
            <div class="selected-file" id="selectedFile" style="display: none;">
                <div class="file-details">
                    <i class="fas fa-file-code"></i>
                    <div class="file-info">
                        <span class="file-name" id="fileName"></span>
                        <span class="file-size" id="fileSize"></span>
                    </div>
                </div>
                <button class="btn-icon remove-file" id="removeFile" title="Remove file">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Import Options -->
            <div class="import-options" id="importOptions" style="display: none;">
                <h4><i class="fas fa-cog"></i> Import Options</h4>
                <div class="options-grid">
                    <label class="checkbox-label">
                        <input type="checkbox" id="truncateExisting" checked>
                        <span class="checkmark"></span>
                        Truncate existing data before import
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" id="updateExisting" checked>
                        <span class="checkmark"></span>
                        Update existing bank records
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" id="createTable" checked>
                        <span class="checkmark"></span>
                        Create table if not exists
                    </label>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons" id="actionButtons" style="display: none;">
                <button class="btn btn-secondary" id="cancelBtn">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="btn btn-primary" id="importBtn">
                    <i class="fas fa-upload"></i> Start Import
                </button>
            </div>
        </div>

        <!-- Progress Section -->
        <div class="progress-section" id="progressSection" style="display: none;">
            <div class="progress-header">
                <h4><i class="fas fa-spinner fa-spin"></i> Import in Progress</h4>
                <span class="progress-percentage" id="progressPercentage">0%</span>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar" id="progressBar" style="width: 0%"></div>
            </div>
            <div class="progress-stats" id="progressStats">
                <span>Processing: <span id="processedCount">0</span>/<span id="totalCount">0</span></span>
                <span>Success: <span id="successCount">0</span></span>
                <span>Errors: <span id="errorCount">0</span></span>
            </div>
        </div>

        <!-- Results Card -->
        <div class="results-card" id="resultsCard" style="display: none;">
            <div class="results-header">
                <h3><i class="fas fa-check-circle" style="color: #28a745;"></i> Import Results</h3>
                <button class="btn-icon close-results" id="closeResults">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="results-body" id="resultsBody">
                <!-- Results will be populated here -->
            </div>
            <div class="results-footer">
                <button class="btn btn-primary" id="viewBanksBtn">
                    <i class="fas fa-university"></i> View Banks
                </button>
                <button class="btn btn-secondary" id="importAnotherBtn">
                    <i class="fas fa-redo"></i> Import Another
                </button>
            </div>
        </div>

        <!-- Error Card -->
        <div class="error-card" id="errorCard" style="display: none;">
            <div class="error-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Import Failed</h3>
            </div>
            <div class="error-body" id="errorBody"></div>
            <div class="error-footer">
                <button class="btn btn-primary" id="tryAgainBtn">
                    <i class="fas fa-redo"></i> Try Again
                </button>
            </div>
        </div>

        <!-- Sample JSON Format -->
        <div class="sample-card">
            <div class="sample-header">
                <h4><i class="fas fa-code"></i> Expected JSON Format</h4>
                <button class="btn-link" id="toggleSample">Show Sample</button>
            </div>
            <div class="sample-body" id="sampleBody" style="display: none;">
                <pre>
{
    "status": true,
    "message": "Banks retrieved",
    "data": [
        {
            "id": 879,
            "name": "78 Finance Company Ltd",
            "code": "40195",
            "longcode": "110072",
            "gateway": null,
            "pay_with_bank": false,
            "supports_transfer": true,
            "active": true,
            "country": "Nigeria",
            "currency": "NGN",
            "type": "nuban"
        }
    ]
}
                </pre>
                <p class="note"><i class="fas fa-info-circle"></i> The file must contain a "data" array with bank objects.</p>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>

    <script src="scripts/bank_upload.js"></script>
</body>
</html>