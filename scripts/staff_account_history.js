const endpoints = {
    deactivation: 'backend/staff_deactivation_history.php',
    reactivation: 'backend/staff_reactivation_history.php'
};

// Common render functions
function renderNoRecordsFound(containerId) {
    const container = document.getElementById(containerId);
    container.innerHTML = `
        <div class="alert alert-info text-center">
            No records found
        </div>
    `;
}

function renderError(containerId, message = 'An Error Occurred, Please Try Again Later.') {
    const container = document.getElementById(containerId);
    container.innerHTML = `
        <div class="alert alert-danger text-center">
            ${message}
        </div>
    `;
}

function renderLoading(containerId) {
    document.getElementById(containerId).innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border" role="status"></div>
        </div>
    `;
}

// Optimized fetch function
async function fetchData(endpoint, page = 1, limit = 10) {
    try {
        const url = `${endpoint}?page=${page}&limit=${limit}`;
        const res = await fetch(url);
        if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
        const data = await res.json();
        return data;
    } catch (error) {
        console.error('Fetch error:', error);
        throw error;
    }
}

// Generic table renderer
function renderTable(data, type, columns) {
    const contentId = `${type}-content`;
    const textElementId = `${type}Text`;
    
    // Hide all titles first
    document.querySelectorAll('[id$="Text"]').forEach(el => {
        el.style.display = 'none';
    });
    
    // Show current title
    document.getElementById(textElementId).style.display = 'inline-block';

    // Check for empty data
    if (!data.data || data.data.length === 0) {
        renderNoRecordsFound(contentId);
        return;
    }

    // Build table HTML
    let html = `
        <table class="table table-bordered history-table">
            <thead class="table-light">
                <tr>
                    ${columns.map(col => `<th>${col.title}</th>`).join('')}
                </tr>
            </thead>
            <tbody>
                ${data.data.map(row => `
                    <tr>
                        ${columns.map(col => `
                            <td>${col.render ? col.render(row) : (row[col.field] || '-')}</td>
                        `).join('')}
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;

    // Add pagination if needed
    if (data.pagination && data.pagination.total > data.pagination.limit) {
        html += renderPagination(data.pagination, type);
    }

    document.getElementById(contentId).innerHTML = html;
}

// Column configurations
const tableColumns = {
    deactivation: [
        { title: 'Date', field: 'date' },
        { title: 'Deactivation ID', field: 'deactivation_id' },
        { title: 'Reason', field: 'reason' },
        { title: 'Status', field: 'status' },
        { 
            title: 'Deactivated By', 
            render: (row) => row.deactivated_by?.name || 'Unknown' 
        }
    ],
    reactivation: [
        { title: 'Date', field: 'date' },
        { title: 'Deactivation ID', field: 'deactivation_id' },
        { title: 'Status', field: 'status' },
        { title: 'Reason', field: 'reason' },
        { 
            title: 'Deactivation Reason', 
            render: (row) => row.deactivation_details?.reason || '-' 
        },
        { 
            title: 'Processed By', 
            render: (row) => row.processed_by?.name || 'Pending' 
        }
    ]
};

// Pagination renderer
function renderPagination({ total, page, limit }, type) {
    const totalPages = Math.ceil(total / limit);
    if (totalPages <= 1) return '';

    let html = `<nav><ul class="pagination justify-content-end">`;
    
    // Previous button
    html += `<li class="page-item ${page === 1 ? 'disabled' : ''}">
        <button class="page-link" onclick="loadHistory('${type}', ${page - 1})">&laquo;</button>
    </li>`;
    
    // Page numbers
    const maxVisiblePages = 5;
    let startPage = Math.max(1, page - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
    
    if (endPage - startPage < maxVisiblePages - 1) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }
    
    if (startPage > 1) {
        html += `<li class="page-item"><button class="page-link" onclick="loadHistory('${type}', 1)">1</button></li>`;
        if (startPage > 2) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    }
    
    for (let i = startPage; i <= endPage; i++) {
        html += `<li class="page-item ${i === page ? 'active' : ''}">
            <button class="page-link" onclick="loadHistory('${type}', ${i})">${i}</button>
        </li>`;
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        html += `<li class="page-item"><button class="page-link" onclick="loadHistory('${type}', ${totalPages})">${totalPages}</button></li>`;
    }
    
    // Next button
    html += `<li class="page-item ${page === totalPages ? 'disabled' : ''}">
        <button class="page-link" onclick="loadHistory('${type}', ${page + 1})">&raquo;</button>
    </li>`;
    
    html += `</ul></nav>`;
    return html;
}

// Main load function
async function loadHistory(type, page = 1) {
    const contentId = `${type}-content`;
    renderLoading(contentId);
    
    try {
        const data = await fetchData(endpoints[type], page);
        renderTable(data, type, tableColumns[type]);
    } catch (err) {
        console.error(`Error loading ${type} history:`, err);
        renderError(contentId);
    }
}

// Search functionality
function setupSearch() {
    const searchInput = document.getElementById("liveSearch");
    if (searchInput) {
        searchInput.addEventListener("input", function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll(".history-table tbody tr");
            
            rows.forEach(row => {
                const cells = row.querySelectorAll("td");
                let matchFound = false;
                
                for (const cell of cells) {
                    if (cell.textContent.toLowerCase().includes(searchTerm)) {
                        matchFound = true;
                        break;
                    }
                }
                
                row.style.display = matchFound ? "" : "none";
            });
        });
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadHistory('deactivation');
    setupSearch();
    
    document.getElementById('deactivation-tab')?.addEventListener('click', () => loadHistory('deactivation'));
    document.getElementById('reactivation-tab')?.addEventListener('click', () => loadHistory('reactivation'));
});