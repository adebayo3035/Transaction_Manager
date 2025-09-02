document.addEventListener("DOMContentLoaded", () => {
    /* ------------------ Modal ------------------ */
    function toggleModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.style.display = (modal.style.display === "block") ? "none" : "block";
    }

    document.querySelectorAll(".modal .close").forEach(btn => {
        btn.addEventListener("click", e => {
            const modal = e.target.closest(".modal");
            if (modal) modal.style.display = "none";
        });
    });

    window.addEventListener("click", e => {
        document.querySelectorAll(".modal").forEach(modal => {
            if (e.target === modal) modal.style.display = "none";
        });
    });

    /* ------------------ Discount Fields ------------------ */
    const discountTypeSelect = document.getElementById("discount_type");
    const percentageDiv = document.getElementById("percentage-div");
    const flatRateDiv = document.getElementById("flat-rate-div");
    const percentageInput = document.getElementById("discount");
    const flatInput = document.getElementById("flat_discount");

    function updateDiscountFields() {
        const type = discountTypeSelect.value;
        percentageDiv.style.display = (type === "percentage") ? "flex" : "none";
        flatRateDiv.style.display = (type === "flat") ? "flex" : "none";

        percentageInput.required = (type === "percentage");
        flatInput.required = (type === "flat");
    }

    discountTypeSelect.addEventListener("change", updateDiscountFields);
    updateDiscountFields();

    /* ------------------ Date-Time Handling ------------------ */
    function setDateConstraints(startDateId, endDateId, minDate = new Date()) {
        const startInput = document.getElementById(startDateId);
        const endInput = document.getElementById(endDateId);

        const minFormatted = minDate.toISOString().slice(0, 16);
        startInput.min = minFormatted;

        startInput.addEventListener("change", () => {
            const selectedStart = new Date(startInput.value);
            endInput.min = startInput.value;

            if (selectedStart.toDateString() === minDate.toDateString()) {
                endInput.min = minFormatted;
            }

            if (new Date(endInput.value) < selectedStart) {
                endInput.value = "";
            }
        });
    }
    setDateConstraints("start_date", "end_date");

    /* ------------------ Fetch Promos ------------------ */
    const ordersTableBody = document.querySelector("#ordersTable tbody");
    const paginationContainer = document.getElementById("pagination");
    const limit = 10;
    let currentPage = 1;

    async function fetchPromos(page = 1) {
        ordersTableBody.innerHTML = `
            <tr><td colspan="7" style="text-align:center;padding:20px">
                <div class="spinner"></div>
            </td></tr>`;

        try {
            const res = await fetch(`backend/get_promo.php?page=${page}&limit=${limit}`);
            const data = await res.json();

            if (data.success && Array.isArray(data.promos.all) && data.promos.all.length) {
                renderTable(data.promos.all);
                renderPagination(data.total, data.page, data.limit);
            } else {
                ordersTableBody.innerHTML = `<tr><td colspan="7" style="text-align:center;">No Promo Details at the moment</td></tr>`;
            }
        } catch (err) {
            console.error("Error fetching promos:", err);
            ordersTableBody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:red;">Error loading Promo data</td></tr>`;
        }
    }

    function renderTable(promos) {
        ordersTableBody.innerHTML = promos.map(p => `
            <tr>
                <td>${p.promo_code}</td>
                <td>${p.promo_name}</td>
                <td>${p.start_date}</td>
                <td>${p.end_date}</td>
                <td>${p.status == 1 ? "Active" : "Inactive"}</td>
                <td>${p.delete_id == 1 ? "Yes" : "No"}</td>
                <td><button class="view-details-btn" data-id="${p.promo_id}">View Details</button></td>
            </tr>`).join("");

        document.querySelectorAll(".view-details-btn").forEach(btn => {
            btn.addEventListener("click", () => fetchPromoDetails(btn.dataset.id));
        });
    }

    function renderPagination(totalItems, current, perPage) {
        paginationContainer.innerHTML = "";
        const totalPages = Math.ceil(totalItems / perPage);

        function createBtn(label, page, disabled = false) {
            const btn = document.createElement("button");
            btn.textContent = label;
            btn.disabled = disabled;
            btn.addEventListener("click", () => fetchPromos(page));
            paginationContainer.appendChild(btn);
        }

        createBtn("« First", 1, current === 1);
        createBtn("‹ Prev", current - 1, current === 1);

        const range = 2;
        for (let i = Math.max(1, current - range); i <= Math.min(totalPages, current + range); i++) {
            const btn = document.createElement("button");
            btn.textContent = i;
            if (i === current) btn.classList.add("active");
            btn.addEventListener("click", () => fetchPromos(i));
            paginationContainer.appendChild(btn);
        }

        createBtn("Next ›", current + 1, current === totalPages);
        createBtn("Last »", totalPages, current === totalPages);
    }

    /* ------------------ Promo Form ------------------ */
    const addPromoForm = document.getElementById("addPromoForm");
    const submitBtn = document.getElementById("createPromoButton");

    addPromoForm.addEventListener("submit", async e => {
        e.preventDefault();

        if (!confirm("Are you sure you want to add new Promo?")) return;

        const formData = new FormData(addPromoForm);
        const discountType = discountTypeSelect.value;
        const discountValue = discountType === "percentage" ? percentageInput.value : flatInput.value;

        formData.set("discount_type", discountType);
        formData.set("discount_value", discountValue);

        try {
            const res = await fetch("backend/create_promo.php", { method: "POST", body: formData });
            const data = await res.json();

            alert(data.status === "success" ? "Promo created successfully!" : `Error: ${data.message}`);
            if (data.status === "success") location.reload();
        } catch (err) {
            console.error("Error creating promo:", err);
            alert("An error occurred. Please try again.");
        }
    });

    /* ------------------ Utilities ------------------ */
    function generatePromoCode() {
        const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        return Array.from({ length: 8 }, () => chars[Math.floor(Math.random() * chars.length)]).join("");
    }

    document.getElementById("promo_code").addEventListener("click", () => {
        document.getElementById("promo_code").value = generatePromoCode();
    });

    function addCharacterCounter(textareaId, counterId) {
        const textarea = document.getElementById(textareaId);
        const counter = document.getElementById(counterId);
        const max = textarea.maxLength;

        textarea.addEventListener("input", () => {
            const remaining = max - textarea.value.length;
            counter.textContent = `${remaining} characters remaining`;
            counter.style.color = remaining < 0 ? "red" : "#555";
        });
        textarea.dispatchEvent(new Event("input"));
    }
    addCharacterCounter("promoDescription", "charCount");

    /* ------------------ Init ------------------ */
    fetchPromos(currentPage);
});
