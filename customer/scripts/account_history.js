
const endpoints = {
    deactivation: '../v2/fetch_customer_restrictions.php',
    reactivation: '../v2/fetch_customer_unrestrictions.php'
};

async function fetchData(endpoint, page = 1, limit = 10) {
    const url = `${endpoint}?page=${page}&limit=${limit}`;
    const res = await fetch(url);
    if (!res.ok) throw new Error(`Failed to fetch ${endpoint}`);
    return res.json();
}

function renderDeactivation(data) {
    document.getElementById('deactivationText').style.display = 'inline-block';
    document.getElementById('reactivationText').style.display = 'none';

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
    html += renderPagination(data.pagination, 'deactivation');
    document.getElementById('deactivation-content').innerHTML = html;
}


function renderReactivation(data) {
    document.getElementById('reactivationText').style.display = 'inline-block';
    document.getElementById('deactivationText').style.display = 'none';

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
    html += renderPagination(data.pagination, 'reactivation');
    document.getElementById('reactivation-content').innerHTML = html;
}

function formatDate(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleString(); // or use `toLocaleDateString()` for just the date
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
    const container = document.getElementById(`${type}-content`);
    container.innerHTML = `<div class="spinner-border" role="status"></div>`;

    try {
        const data = await fetchData(endpoints[type], page);
        type === 'deactivation' ? renderDeactivation(data) : renderReactivation(data);
    } catch (err) {
        container.innerHTML = `<div class="alert alert-danger">An Error Occurred, Please Try Again Later.</div>`;
    } finally {
        toggleLoader(false); // Hide spinner after fetch completes (success or error)
    }
}

function toggleLoader(show) {
    const loader = document.getElementById('spinner');
    if (loader) {
        loader.style.display = show ? 'block' : 'none';
    }
}
document.getElementById("liveSearch").addEventListener("input", filterTable);

    function filterTable() {
        const searchTerm = document.getElementById("liveSearch").value.toLowerCase();
        const rows = document.querySelectorAll(".history-table tbody tr");

        rows.forEach(row => {
            const cells = row.getElementsByTagName("td");
            let matchFound = false;

            for (let i = 0; i < cells.length; i++) {
                const cellText = cells[i].textContent.toLowerCase();
                if (cellText.includes(searchTerm)) {
                    matchFound = true;
                    break;
                }
            }

            row.style.display = matchFound ? "" : "none";
        });
    }
// Initial load
loadHistory('deactivation');
document.getElementById('deactivation-tab').addEventListener('click', () => loadHistory('deactivation'));
document.getElementById('reactivation-tab').addEventListener('click', () => loadHistory('reactivation'));