document.addEventListener("DOMContentLoaded", () => {
  const limit = 10;
  let currentPage = 1;

  const ordersTableBody = document.querySelector("#orderSummaryTable tbody");
  const orderDetailsTableBody = document.querySelector(
    "#orderDetailsTable tbody"
  );
  const paginationContainer = document.getElementById("pagination");
  // const liveSearchInput = document.getElementById("liveSearch");
  const printButton = document.getElementById("receipt-btn");
  const orderModal = document.getElementById("orderModal");
  const reportOrderModal = document.getElementById("reportOrderModal");
  const orderDetailsTableBodyHeader = document.querySelector(
    "#orderDetailsTable thead tr"
  );
  orderDetailsTableBodyHeader.style.color = "#000";
  const submitReportBtn = document.getElementById("submit-report");

  // Fetch Pending orders
  fetch("../v2/fetch_pending_order.php")
    .then((response) => response.json())
    .then((data) => {
      // Assuming the structure has `pending_orders` inside the response
      const pending_orders = data.pending_orders;
      //Populate select input to select order for Update
      if (Array.isArray(pending_orders)) {
        const orderSelect = document.getElementById("order-id");

        pending_orders.forEach((item) => {
          let option = document.createElement("option");
          option.value = item.order_id;
          option.setAttribute("data-status", item.delivery_status);
          option.text = `Order: ${item.order_id} - ${item.order_date} - ${item.delivery_status} - ${item.customer_id}`;
          orderSelect.appendChild(option);
        });
      } else {
        console.error("Expected pending_orders to be an array.");
      }
    })
    .catch((error) => console.error("Error fetching food items:", error));

  // Fetch orders with pagination
  function fetchOrders(page = 1) {
    fetch(`../v2/fetch_pending_order.php?page=${page}&limit=${limit}`)
      .then((response) => response.json())
      .then((data) => {
        // Prefer pending_orders if available and non-empty
        const ordersToDisplay =
          data.pending_orders && data.pending_orders.length > 0
            ? data.pending_orders
            : data.orders || [];

        if (data.success && ordersToDisplay.length > 0) {
          updateTable(ordersToDisplay);
          updatePagination(data.total, data.page, data.limit);
        } else {
          const ordersTableBody = document.querySelector(
            "#orderSummaryTable tbody"
          );
          ordersTableBody.innerHTML = "";
          const noOrderRow = document.createElement("tr");
          noOrderRow.innerHTML = `<td colspan="7" style="text-align:center;">No Pending Orders at the moment</td>`;
          ordersTableBody.appendChild(noOrderRow);
          console.error("Failed to fetch orders:", data.message);
        }
      })
      .catch((error) => console.error("Error fetching data:", error));
  }

  // Update orders table
  function updateTable(orders) {
    ordersTableBody.innerHTML = "";
    const fragment = document.createDocumentFragment();

    orders.forEach((order) => {
      const row = document.createElement("tr");

      const reportButtonCell =
        order.delivery_status === "In Transit"
          ? `<button class="report-order"
            data-order-id="${order.order_id}"
            data-driver-id="${order.driver_id}"
            data-customer-id="${order.customer_id}">
            Report Order
           </button>`
          : "";

      row.innerHTML = `
        <td>${order.order_id}</td>
        <td>${order.order_date}</td>
        <td>${order.delivery_fee}</td>
        <td>${order.delivery_status}</td>
        <td>
          <button class="view-details-btn" data-order-id="${order.order_id}">
            View Details
          </button>
        </td>
        <td>${reportButtonCell}</td>
    `;

      fragment.appendChild(row);
    });

    ordersTableBody.appendChild(fragment);

    document.querySelectorAll(".report-order").forEach((btn) => {
      btn.addEventListener("click", handleReportOrderClick);
    });
  }

  // Handle click on "Report Order" button
  function handleReportOrderClick(e) {
    const btn = e.currentTarget;
    const orderId = btn.dataset.orderId;
    const driverId = btn.dataset.driverId || "";
    const customerId = btn.dataset.customerId || "";

    // Prefill modal form
    document.getElementById("report-order-id").value = orderId;
    document.getElementById("report-driver-id").value = driverId;
    document.getElementById("report-customer-id").value = customerId;
    document.getElementById("report-action").value = "";

    // Show modal
    document.getElementById("reportOrderModal").style.display = "block";

    // Disable button to prevent duplicate clicks
    // btn.disabled = true;
  }

  // Handle modal close
  document
    .querySelector(".close-report-modal")
    .addEventListener("click", () => {
      document.getElementById("reportOrderModal").style.display = "none";
      document
        .querySelectorAll(".report-order")
        .forEach((btn) => (btn.disabled = false));
    });

  // Word limit enforcement (max 50 words)
  document
    .getElementById("report-action")
    .addEventListener("input", function () {
      const words = this.value.trim().split(/\s+/).filter(Boolean);
      if (words.length > 50) {
        this.value = words.slice(0, 50).join(" ");
        alert("Action must not exceed 50 words.");
      }
    });

  // Handle form submission
  document
    .getElementById("reportOrderForm")
    .addEventListener("submit", async function (e) {
      e.preventDefault();

      const payload = {
        order_id: document.getElementById("report-order-id").value,
        customer_id: document.getElementById("report-customer-id").value,
        driver_id: document.getElementById("report-driver-id").value,
        action: document.getElementById("report-action").value.trim(),
      };

      try {
        // Disable the entire page and show loading state
        disablePage();
        const response = await fetch("../v2/report_order.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload),
        });

        const result = await response.json();

        if (result.success) {
          alert("Order report submitted successfully.");
          document.getElementById("reportOrderModal").style.display = "none";
        } else {
          alert("Failed to submit report: " + result.message);
        }
      } catch (err) {
        console.error("Error submitting report:", err);
        alert("An error occurred while submitting the report.");
      } finally {
        document
          .querySelectorAll(".report-order")
          .forEach((btn) => (btn.disabled = false));
        // Re-enable the page regardless of success or failure
        enablePage();
      }
    });

  // Delegate event listener for view details buttons
  ordersTableBody.addEventListener("click", (event) => {
    if (event.target.classList.contains("view-details-btn")) {
      const orderId = event.target.getAttribute("data-order-id");
      fetchOrderDetails(orderId);
    }
  });

  // Fetch order details for a specific order
  function fetchOrderDetails(orderId) {
    fetch("../v2/fetch_pending_details.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ order_id: orderId }),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          populateOrderDetails(data.order, data.items);
          orderModal.style.display = "block";
        } else {
          console.error("Failed to fetch order details:", data.message);
        }
      })
      .catch((error) => console.error("Error fetching order details:", error));
  }

  // Updated populateOrderDetails function to work with new structure
  function populateOrderDetails(order, items) {
    orderDetailsTableBody.innerHTML = "";
    const fragment = document.createDocumentFragment();

    // Populate order items
    items.forEach((item) => {
      const row = document.createElement("tr");
      row.innerHTML = `
            <td>${order.order_date}</td>
            <td>${item.food_name}</td>
            <td>${item.quantity}</td>
            <td>${item.item_status}</td>
        `;
      fragment.appendChild(row);
    });

    // Add order metadata
    fragment.appendChild(createRow("", ""));
    fragment.appendChild(createRow("Number of Packs", order.pack_count));
    fragment.appendChild(createRow("Date Last Modified", order.updated_at));
    fragment.appendChild(createRow("Delivery Fee", order.delivery_fee));
    fragment.appendChild(
      createRow(
        "Customer's Name",
        `${order.customer.firstname} ${order.customer.lastname}`
      )
    );
    fragment.appendChild(
      createRow("Customer's Mobile Number", order.customer.phone)
    );
    fragment.appendChild(
      createRow("Customer's Address", order.customer.address)
    );
    fragment.appendChild(createRow("Delivery Status", order.delivery_status));

    if (order.driver && order.driver.firstname && order.driver.lastname) {
      fragment.appendChild(
        createRow(
          "Driver's Name",
          `${order.driver.firstname} ${order.driver.lastname}`
        )
      );
    }

    orderDetailsTableBody.appendChild(fragment);

    if (
      order.delivery_status === "Delivered" ||
      order.delivery_status === "Canceled"
    ) {
      printButton.style.display = "block";
    }
  }

  // Helper function to create table rows (unchanged)
  function createRow(label, value) {
    const row = document.createElement("tr");
    row.innerHTML = `
        <td><strong>${label}</strong></td>
        <td colspan = "3">${value}</td>
    `;
    return row;
  }

  // Update pagination
  function updatePagination(totalItems, currentPage, itemsPerPage) {
    paginationContainer.innerHTML = "";
    const totalPages = Math.ceil(totalItems / itemsPerPage);
    const fragment = document.createDocumentFragment();

    for (let page = 1; page <= totalPages; page++) {
      const pageButton = document.createElement("button");
      pageButton.textContent = page;
      pageButton.classList.add(
        "page-btn",
        page === currentPage ? "active" : ""
      );
      pageButton.addEventListener("click", () => fetchOrders(page));
      fragment.appendChild(pageButton);
    }

    paginationContainer.appendChild(fragment);
  }

  // Close modal event
  document.querySelector(".modal .close");
  window.addEventListener("click", (event) => {
    if (event.target === orderModal) {
      orderModal.style.display = "none";
    }
  });

  document.querySelector(".modal .close-report-modal");
  window.addEventListener("click", (event) => {
    if (event.target === reportOrderModal) {
      reportOrderModal.style.display = "none";
    }
  });

  // Handle printing receipt
  function printReceipt() {
    const orderDetails = orderDetailsTableBody.outerHTML;
    const now = new Date();
    const dateTime = now.toLocaleString();
    const receiptWindow = window.open("", "", "width=800,height=600");

    receiptWindow.document.write(`
            <html><head><title>Receipt</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #333; }
                h2 { text-align: center; margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                @media print { body { padding: 10px; } table { font-size: 12px; } }
            </style></head><body>
            <h2>KaraKata Foods</h2>
            <h3>Order Details</h3>
            ${orderDetails}
            <br>Thank you for your Patronage <br/>Date and Time: ${dateTime}
            </body></html>
        `);

    receiptWindow.document.close();
    receiptWindow.print();
  }

  if (printButton) {
    printButton.addEventListener("click", printReceipt);
  }

  // Initial fetch
  fetchOrders(currentPage);

  // Function to disable the entire page
  function disablePage() {
    // Create overlay if it doesn't exist
    let overlay = document.getElementById("pageOverlay");
    if (!overlay) {
      overlay = document.createElement("div");
      overlay.id = "pageOverlay";
      overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9998;
            display: flex;
            justify-content: center;
            align-items: center;
        `;

      // Create loading spinner
      const spinner = document.createElement("div");
      spinner.style.cssText = `
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        `;

      // Add CSS for spinner animation
      if (!document.querySelector("#spinnerStyles")) {
        const style = document.createElement("style");
        style.id = "spinnerStyles";
        style.textContent = `
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            `;
        document.head.appendChild(style);
      }

      overlay.appendChild(spinner);
      document.body.appendChild(overlay);
    } else {
      overlay.style.display = "flex";
    }

    // Disable all interactive elements
    const interactiveElements = document.querySelectorAll(
      "button, input, select, textarea, a"
    );
    interactiveElements.forEach((element) => {
      element.setAttribute(
        "data-was-disabled",
        element.disabled || element.style.pointerEvents === "none"
      );
      element.disabled = true;
      element.style.pointerEvents = "none";
      element.style.opacity = "0.6";
    });

    // Disable the submit button specifically with more obvious styling
    submitReportBtn.disabled = true;
    submitReportBtn.style.opacity = "0.5";
    submitReportBtn.style.cursor = "not-allowed";
    submitReportBtn.textContent = "Processing...";
  }

  // Function to re-enable the page
  function enablePage() {
    // Remove overlay
    const overlay = document.getElementById("pageOverlay");
    if (overlay) {
      overlay.style.display = "none";
    }

    // Re-enable all interactive elements
    const interactiveElements = document.querySelectorAll(
      "button, input, select, textarea, a"
    );
    interactiveElements.forEach((element) => {
      const wasDisabled = element.getAttribute("data-was-disabled") === "true";
      if (!wasDisabled) {
        element.disabled = false;
        element.style.pointerEvents = "";
        element.style.opacity = "";
      }
      element.removeAttribute("data-was-disabled");
    });

    // Re-enable the submit button
    submitReportBtn.disabled = false;
    submitReportBtn.style.opacity = "";
    submitReportBtn.style.cursor = "";
    submitReportBtn.textContent = "Submit Report";
  }
});
