document.addEventListener('DOMContentLoaded', () => {
    // Configuration
    const CONFIG = {
        itemsPerPage: 10,
        repaymentItemsPerPage: 5,
        minSpinnerTime: 1000,
        spinnerDelay: 800,
        apiEndpoints: {
            fetchCredits: 'backend/fetch_credit_summary.php',
            fetchCreditDetails: 'backend/fetch_credit_details.php'
        }
    };
    document.getElementById('applyFilters').addEventListener('click', () => {
        api.fetchCredits(1); // restart from first page with filters applied
    });


    // State management
    const state = {
        currentPage: 1,
        currentCreditId: null,
        currentRepaymentPage: 1
    };

    // DOM Elements
    const elements = {
        tables: {
            credits: {
                body: document.querySelector('#ordersTable tbody'),
                container: document.getElementById('ordersTableBody')
            },
            creditDetails: {
                body: document.querySelector('#orderDetailsTable tbody'),
                header: document.querySelector('#orderDetailsTable thead tr')
            },
            repaymentHistory: {
                body: document.querySelector('#repaymentHistoryTable tbody')
            }
        },
        pagination: {
            credits: document.getElementById('pagination'),
            repayment: document.getElementById('repaymentPagination')
        },
        search: document.getElementById('liveSearch'),
        modal: document.getElementById('orderModal'),
        creditOrderId: document.getElementById('credit_order_id'),
        buttons: {
            closeModal: document.querySelector('.modal .close')
        }
    };

    // Style initialization
    elements.tables.creditDetails.header.style.color = "#000";

    // Utility functions
    const utils = {
        showSpinner: (container, message = 'Loading...') => {
            container.innerHTML = `
                <tr>
                    <td colspan="7" class="spinner-container">
                        <div class="spinner"
                            style="border: 4px solid #f3f3f3;
                                   border-top: 4px solid #3498db;
                                   border-radius: 50%;
                                   width: 30px;
                                   height: 30px;
                                   animation: spin 1s linear infinite;
                                   margin: auto;">
                        </div>
                        ${message ? `<p style="margin-top: 8px; "text=align:center";>${message}</p>` : ''}
                    </td>
                </tr>
            `;
        },

        showError: (container, message = 'Error loading data') => {
            container.innerHTML = `
                <tr>
                    <td colspan="7" class="error-message">${message}</td>
                </tr>
            `;
        },

        toggleModal: (show = true) => {
            elements.modal.style.display = show ? 'block' : 'none';
        },

        createButton: (label, page, disabled = false, active = false) => {
            const btn = document.createElement('button');
            btn.textContent = label;
            btn.disabled = disabled;
            if (active) btn.classList.add('active');
            return btn;
        },

        debounce: (func, delay) => {
            let timeout;
            return function () {
                const context = this;
                const args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), delay);
            };
        }
    };

    // API Functions
    const api = {
        fetchCredits: (page = 1) => {
            utils.showSpinner(elements.tables.credits.container);

            // Get filter values
            const repaymentStatus = document.getElementById('repaymentStatus')?.value || '';
            const dueStatus = document.getElementById('dueStatus')?.value || '';

            // Build query string
            let url = `${CONFIG.apiEndpoints.fetchCredits}?page=${page}&limit=${CONFIG.itemsPerPage}`;
            if (repaymentStatus) url += `&repayment_status=${encodeURIComponent(repaymentStatus)}`;
            if (dueStatus) url += `&due_status=${encodeURIComponent(dueStatus)}`;

            const minDelay = new Promise(resolve => setTimeout(resolve, CONFIG.minSpinnerTime));
            const fetchData = fetch(url).then(res => res.json());

            Promise.all([fetchData, minDelay])
                .then(([data]) => {
                    if (data.success && data.data?.credits?.length > 0) {
                        ui.updateCreditsTable(data.data.credits);
                        ui.updatePagination(
                            data.data.pagination.total,
                            data.data.pagination.page,
                            data.data.pagination.limit
                        );
                    } else {
                        utils.showError(
                            elements.tables.credits.container,
                            data.message || 'No Credit History at the moment'
                        );
                    }
                })
                .catch(error => {
                    console.error('Error fetching credit data:', error);
                    utils.showError(
                        elements.tables.credits.container,
                        'Error loading Credit History'
                    );
                });
        },


        fetchCreditDetails: (creditId, page = 1) => {
            return fetch(CONFIG.apiEndpoints.fetchCreditDetails, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    credit_id: creditId,
                    page: page,
                    limit: CONFIG.repaymentItemsPerPage
                })
            })
                .then(response => response.json());
        }
    };

    // UI Functions
    const ui = {
        updateCreditsTable: (credits) => {
            elements.tables.credits.body.innerHTML = '';
            const fragment = document.createDocumentFragment();

            credits.forEach(credit => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${credit.order_id}</td>
                    <td>${credit.credit_order_id}</td>
                    <td>${credit.customer_id}</td>
                    <td>${credit.total_credit_amount}</td>
                    <td>${credit.remaining_balance}</td>
                    <td>${credit.repayment_status}</td>
                    <td>
                        <button class="view-details-btn" data-credit-id="${credit.credit_order_id}">
                            View Details
                        </button>
                    </td>
                `;
                fragment.appendChild(row);
            });

            elements.tables.credits.body.appendChild(fragment);
        },

        updatePagination: (totalItems, currentPage, itemsPerPage) => {
            elements.pagination.credits.innerHTML = '';
            const totalPages = Math.ceil(totalItems / itemsPerPage);

            // First and Previous buttons
            elements.pagination.credits.appendChild(
                utils.createButton('« First', 1, currentPage === 1)
            );
            elements.pagination.credits.appendChild(
                utils.createButton('‹ Prev', currentPage - 1, currentPage === 1)
            );

            // Page numbers
            const maxVisible = 2;
            const start = Math.max(1, currentPage - maxVisible);
            const end = Math.min(totalPages, currentPage + maxVisible);

            for (let i = start; i <= end; i++) {
                const btn = utils.createButton(i, i, false, i === currentPage);
                btn.addEventListener('click', () => {
                    state.currentPage = i;
                    api.fetchCredits(i);
                });
                elements.pagination.credits.appendChild(btn);
            }

            // Next and Last buttons
            const nextBtn = utils.createButton('Next ›', currentPage + 1, currentPage === totalPages);
            nextBtn.addEventListener('click', () => {
                state.currentPage = currentPage + 1;
                api.fetchCredits(currentPage + 1);
            });
            elements.pagination.credits.appendChild(nextBtn);

            const lastBtn = utils.createButton('Last »', totalPages, currentPage === totalPages);
            lastBtn.addEventListener('click', () => {
                state.currentPage = totalPages;
                api.fetchCredits(totalPages);
            });
            elements.pagination.credits.appendChild(lastBtn);
        },

        populateCreditDetails: (details) => {
            elements.tables.creditDetails.body.innerHTML = '';
            elements.creditOrderId.textContent = details.credit_order_id;

            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${details.total_credit_amount}</td>
                <td>${details.amount_paid}</td>
                <td>${details.remaining_balance}</td>
                <td>${details.repayment_status}</td>
                <td>${details.due_date}</td>
            `;
            elements.tables.creditDetails.body.appendChild(row);
        },

        populateRepaymentHistory: (historyData) => {
            elements.tables.repaymentHistory.body.innerHTML = '';

            if (!historyData || historyData.length === 0) {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td colspan="2" style="text-align: center;">
                        No repayment history available.
                    </td>
                `;
                elements.tables.repaymentHistory.body.appendChild(row);
                return;
            }

            historyData.forEach(rep => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${rep.payment_date}</td>
                    <td>${rep.amount_paid}</td>
                `;
                elements.tables.repaymentHistory.body.appendChild(row);
            });
        },

        setupRepaymentPagination: (pagination, creditId) => {
            const { total, page, limit } = pagination;
            const totalPages = Math.ceil(total / limit);
            elements.pagination.repayment.innerHTML = '';

            if (totalPages <= 1) return;

            const isFirstPage = page === 1;
            const isLastPage = page === totalPages;

            // Create pagination button with proper disabled state
            const createPaginationButton = (label, targetPage, isDisabled) => {
                const btn = document.createElement('button');
                btn.textContent = label;
                btn.disabled = isDisabled;
                if (isDisabled) {
                    btn.classList.add('disabled');
                    btn.style.opacity = '0.5';
                    btn.style.cursor = 'not-allowed';
                } else {
                    btn.addEventListener('click', () => {
                        ui.showRepaymentSpinner();
                        setTimeout(() => {
                            credit.loadCreditDetails(creditId, targetPage);
                        }, CONFIG.spinnerDelay);
                    });
                }
                elements.pagination.repayment.appendChild(btn);
            };

            // First and Previous buttons (disabled on first page)
            createPaginationButton('« First', 1, isFirstPage);
            createPaginationButton('‹ Prev', page - 1, isFirstPage);

            // Page number buttons
            const maxVisible = 2;
            const start = Math.max(1, page - maxVisible);
            const end = Math.min(totalPages, page + maxVisible);

            for (let i = start; i <= end; i++) {
                const btn = document.createElement('button');
                btn.textContent = i;
                if (i === page) {
                    btn.classList.add('active');
                    btn.style.fontWeight = 'bold';
                }
                btn.addEventListener('click', () => {
                    ui.showRepaymentSpinner();
                    setTimeout(() => {
                        credit.loadCreditDetails(creditId, i);
                    }, CONFIG.spinnerDelay);
                });
                elements.pagination.repayment.appendChild(btn);
            }

            // Next and Last buttons (disabled on last page)
            createPaginationButton('Next ›', page + 1, isLastPage);
            createPaginationButton('Last »', totalPages, isLastPage);

            // Add some styling between buttons
            elements.pagination.repayment.style.gap = '5px';
            elements.pagination.repayment.style.display = 'flex';
            elements.pagination.repayment.style.alignItems = 'center';
        },
        showRepaymentSpinner: () => {
            elements.tables.repaymentHistory.body.innerHTML = `
                <tr>
                    <td colspan="2" style="text-align:center; padding: 20px;">
                        <div class="spinner"
                            style="border: 3px solid rgba(0,0,0,0.1);
                                   border-top: 3px solid #3498db;
                                   border-radius: 50%;
                                   width: 24px;
                                   height: 24px;
                                   animation: spin 0.8s linear infinite;
                                   margin: 0 auto;">
                        </div>
                        <p style="margin-top: 8px; color: #666; font-size: 14px;">
                            Loading repayment history...
                        </p>
                    </td>
                </tr>
            `;
        },

        filterTable: () => {
            const input = elements.search.value.toLowerCase();
            const rows = elements.tables.credits.body.getElementsByTagName("tr");

            Array.from(rows).forEach(row => {
                const cells = row.getElementsByTagName("td");
                const found = Array.from(cells).some(cell =>
                    cell.textContent.toLowerCase().includes(input)
                );
                row.style.display = found ? "" : "none";
            });
        }
    };

    // Credit Management Functions
    const credit = {
        loadCreditDetails: (creditId, page = 1) => {
            api.fetchCreditDetails(creditId, page)
                .then(data => {
                    if (data.success) {
                        state.currentCreditId = creditId;
                        state.currentRepaymentPage = page;

                        ui.populateCreditDetails(data.credit_details);
                        ui.populateRepaymentHistory(data.repayment_history?.records);
                        ui.setupRepaymentPagination(data.repayment_history?.pagination, creditId);
                        utils.toggleModal(true);
                    } else {
                        console.error('Failed to fetch credit details:', data.message);
                    }
                })
                .catch(error => console.error('Error fetching credit details:', error));
        }
    };

    // Event Listeners
    const setupEventListeners = () => {
        // View details button delegation
        elements.tables.credits.body.addEventListener('click', (event) => {
            if (event.target.classList.contains('view-details-btn')) {
                const creditId = event.target.getAttribute('data-credit-id');
                credit.loadCreditDetails(creditId);
            }
        });

        // Search with debounce
        elements.search.addEventListener('input', utils.debounce(ui.filterTable, 300));

        // Modal close
        elements.buttons.closeModal.addEventListener('click', () => {
            utils.toggleModal(false);
            location.reload();
        });

        // Outside click to close modal
        window.addEventListener('click', (event) => {
            if (event.target === elements.modal) {
                utils.toggleModal(false);
                location.reload();
            }
        });
    };

    // Initialize
    const init = () => {
        setupEventListeners();
        api.fetchCredits(state.currentPage);
    };

    init();
});