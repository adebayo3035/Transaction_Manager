document.addEventListener("DOMContentLoaded", function () {
    const applyFiltersButton = document.getElementById("apply-filters");
    const downloadStatementButton = document.getElementById("download-statement");
    const transactionTableBody = document.querySelector("#transactions tbody");

    // Event listener for applying filters
    applyFiltersButton.addEventListener("click", function () {
        const startDate = document.getElementById("start-date").value;
        const endDate = document.getElementById("end-date").value;
        const transactionType = document.getElementById("transaction-type").value;
        const paymentMethod = document.getElementById("payment-method").value;
        const description = document.getElementById("transaction_description").value;
        

        if (!validateDates(startDate, endDate)) return;

        fetchTransactions(startDate, endDate, transactionType, paymentMethod, description);
    });

    downloadStatementButton.addEventListener("click", function () {
        const startDate = document.getElementById("start-date").value;
        const endDate = document.getElementById("end-date").value;
        const format = document.querySelector('input[name="format"]:checked')?.value; // Get selected format (pdf or csv)
    
        if (!startDate || !endDate || !format) {
            alert("Please select start date, end date, and format.");
            return;
        }
        if (!validateDates(startDate, endDate)) return;
    
        fetch("../v2/download_statement.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ start_date: startDate, end_date: endDate, format: format }),
        })
        .then(response => {
            if (!response.ok) throw new Error("Download failed.");
            return response.blob();
        })
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = `account_statement.${format === "csv" ? "csv" : "pdf"}`;
            document.body.appendChild(a);
            a.click();
            a.remove();
        })
        .catch(error => console.error("Error downloading statement:", error));
    });
    

    // Function to validate start and end dates
    function validateDates(startDate, endDate) {
        if (!startDate || !endDate) {
            alert("Both start and end dates are required.");
            return false;
        }

        const start = new Date(startDate);
        const end = new Date(endDate);
        const today = new Date();
        // today.setHours(0, 0, 0, 0); // Normalize to the start of the day

        if (start > end) {
            alert("Start date cannot be greater than end date.");
            return false;
        }

        if (end > today) {
            alert("End date cannot be in the future.");
            return false;
        }

        return true;
    }

    // Function to fetch transactions from the backend
    function fetchTransactions(startDate, endDate, transactionType, paymentMethod, description, page = 1) {
        fetch("../v2/payment_history.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                start_date: startDate,
                end_date: endDate,
                type: transactionType,
                payment_method: paymentMethod,
                description: description,
                page: page, // Send page number
            }),
        })
        .then((response) => response.json())
        .then((data) => {
            transactionTableBody.innerHTML = ""; // Clear existing rows
            data.transactions.forEach((transaction) => {
                const row = document.createElement("tr");
                row.innerHTML = `
                    <td>${transaction.id}</td>
                    <td>${transaction.transaction_ref}</td>
                    <td>${transaction.customer_id}</td>
                    <td>${transaction.date_created}</td>
                    <td>${transaction.transaction_type}</td>
                    <td>${transaction.payment_method}</td>
                    <td>${transaction.amount}</td>
                    <td>${transaction.description}</td>
                `;
                transactionTableBody.appendChild(row);
            });
    
            // Handle pagination UI
            updatePaginationControls(data.total_pages, page);
        })
        .catch((error) => console.error("Error fetching transactions:", error));
    }
    
    // Function to update pagination UI
    function updatePaginationControls(totalPages, currentPage) {
        const paginationContainer = document.getElementById("pagination");
        paginationContainer.innerHTML = ""; // Clear previous pagination
    
        for (let i = 1; i <= totalPages; i++) {
            const button = document.createElement("button");
            button.textContent = i;
            button.classList.add("pagination-btn");
            
            if (i === currentPage) {
                button.classList.add("active");
            }
    
            // Fetch filters directly inside the event listener
            button.addEventListener("click", () => {
                const startDate = document.getElementById("start-date").value;
                const endDate = document.getElementById("end-date").value;
                const transactionType = document.getElementById("transaction-type").value;
                const paymentMethod = document.getElementById("payment-method").value;
                const description = document.getElementById("transaction_description").value;
    
                fetchTransactions(startDate, endDate, transactionType, paymentMethod, description, i);
            });
    
            paginationContainer.appendChild(button);
        }
    }
});
