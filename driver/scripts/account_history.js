const endpoint = '../v2/fetch_driver_history.php';

async function fetchData(actionType, page = 1, limit = 10) {
    const res = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ actionType, page, limit })
    });

    if (!res.ok) throw new Error(`Failed to fetch history for ${actionType}`);
    return res.json();
}

function renderTable(data, tabType) {
    // Show correct heading
    const titles = ['restriction', 'unrestriction', 'deactivation', 'reactivation'];
    titles.forEach(id => {
        document.getElementById(id + 'Text').style.display = id === tabType ? 'inline-block' : 'none';
    });

    const contentId = `${tabType}-content`;
    const container = document.getElementById(contentId);

    // Check if records exist
    if (!data.records || data.records.length === 0) {
        container.innerHTML = `
            <div class="alert alert-info">
                No records found
            </div>
        `;
        return; // Exit the function early
    }

    let html = `
        <table class="table table-bordered history-table">
          <thead class="table-light">
            <tr>
              <th>Date</th>
              <th>Reference ID</th>
              <th>Initiator</th>
            </tr>
          </thead>
          <tbody>
            ${data.records.map(row => `
              <tr>
                <td>${formatDate(row.created_at)}</td>
                <td>${row.reference_id}</td>
                <td>${row.initiator || 'Unknown'}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
    `;
    
    // Only add pagination if there are records
    if (data.pagination && data.pagination.total > 0) {
        html += renderPagination(data.pagination, tabType);
    }
    
    container.innerHTML = html;
}
function renderPagination({ total, page, limit }, type) {
    const totalPages = Math.ceil(total / limit);
    if (totalPages <= 1) return '';

    let html = `<nav><ul class="pagination justify-content-end">`;
    for (let i = 1; i <= totalPages; i++) {
        html += `<li class="page-item ${i === page ? 'active' : ''}">
          <button class="page-link" onclick="loadHistory('${type}', ${i})">${i}</button>
        </li>`;
    }
    html += `</ul></nav>`;
    return html;
}

async function loadHistory(type, page = 1) {
    const contentId = `${type}-content`;
    const container = document.getElementById(contentId);
    container.innerHTML = `<div class="spinner-border" role="status"></div>`;

    const actionMap = {
        restriction: 'RESTRICT',
        unrestriction: 'UNRESTRICT',
        deactivation: 'DEACTIVATE',
        reactivation: 'REACTIVATE'
    };

    try {
        const data = await fetchData(actionMap[type], page);
        renderTable(data, type);
    } catch (err) {
        container.innerHTML = `<div class="alert alert-danger">Error loading data: ${err.message}</div>`;
    } finally {
        toggleLoader(false);
    }
}

function formatDate(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleString();
}

function toggleLoader(show) {
    const loader = document.getElementById('spinner');
    if (loader) loader.style.display = show ? 'block' : 'none';
}

document.getElementById("liveSearch").addEventListener("input", function () {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll(".history-table tbody tr");

    rows.forEach(row => {
        const match = [...row.cells].some(cell =>
            cell.textContent.toLowerCase().includes(searchTerm)
        );
        row.style.display = match ? "" : "none";
    });
});

// Initialize tabs
loadHistory('restriction');
document.getElementById('restriction-tab').addEventListener('click', () => loadHistory('restriction'));
document.getElementById('unrestriction-tab').addEventListener('click', () => loadHistory('unrestriction'));
document.getElementById('deactivation-tab').addEventListener('click', () => loadHistory('deactivation'));
document.getElementById('reactivation-tab').addEventListener('click', () => loadHistory('reactivation'));
