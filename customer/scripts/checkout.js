document.addEventListener('DOMContentLoaded', () => {
    const checkoutForm = document.getElementById('checkoutForm');
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    const creditCardDetails = document.getElementById('credit-card-details');
    const paypalDetails = document.getElementById('paypal-details');
    const creditDetails = document.getElementById('credit-details');
    const bankTransferDetails = document.getElementById('bank_transfer_details');
    const bankAccountInput = document.getElementById('bank_account');
    const bankNameSelect = document.getElementById('bank_name');
    const printReceiptBtn = document.getElementById('receipt-btn');
    const placeOrderBtn = document.getElementById('place-orderBtn');
    const promoCheckBox = document.getElementById('promoCheckBox');
    const promoInput = document.getElementById('promo_code');
    const promoBtn = document.getElementById('validate_promo');

    let usePromo = false;
    let discount_value = 0;
    let discount_percent = 0;
    let promoCode = '';
    let orderData = {}; // Declare orderData at the top to make it accessible globally


    creditDetails.style.display = "none";

    // Toggle display of Promo Container
    promoCheckBox.addEventListener('change', function () {
        const promoContainer = document.getElementById('promoContainer');
        if (this.checked) {
            promoContainer.style.display = 'block';
            checkoutForm.style.display = 'none';
        } else {
            promoContainer.style.display = 'none';
            checkoutForm.style.display = 'block';
        }
    });

    // Validate promo code
    // Validate promo code (updated for pack structure)
    promoBtn.addEventListener('click', () => {
        const discountItem = document.getElementById('discount-item');
        const totalAfterDiscount = document.getElementById('total-after-discount');
        const totalAmountLabel = document.getElementById('totalAmount-label');
        const discountValueElem = document.getElementById('discount-value');
        const totalFeeAfterElem = document.getElementById('total-fee-after');

        if (!confirm("Are you sure you want to validate this promo code?")) {
            promoInput.value = '';
            return;
        }

        fetch('../v2/get_order_session.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Use either new structure or legacy data
                    const orderData = data.data || data.legacy_data;

                    // Calculate SUBTOTAL (just food items)
                    const subtotal = parseFloat(orderData.total_order || orderData.totals?.subtotal);

                    // Calculate TOTAL ORDER (subtotal + fees)
                    const serviceFee = parseFloat(orderData.service_fee || orderData.totals?.service_fee);
                    const deliveryFee = parseFloat(orderData.delivery_fee || orderData.totals?.delivery_fee);
                    const totalOrder = subtotal + serviceFee + deliveryFee;

                    const promoDetails = {
                        promo_code: promoInput.value,
                        total_order: totalOrder // Pass the complete total including fees
                    };

                    return fetch('../v2/validate_promo.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(promoDetails)
                    });
                } else {
                    alert("Failed to retrieve Total Order. Please try again.");
                    throw new Error(data.message);
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.eligible) {
                    alert('Promo Code has been successfully Validated.');
                    usePromo = true;
                    discount_value = data.discount;
                    discount_percent = data.discount_percent;
                    promoCode = data.promo_code;
                    totalOrder = data.total_order;

                    // Calculate the FINAL AMOUNT after discount
                    const totalAmountAfter = parseFloat(totalOrder) - discount_value;

                    // Update UI
                    discountItem.style.display = "flex";
                    totalAfterDiscount.style.display = "flex";
                    totalAmountLabel.textContent = 'Total Amount Before Discount';
                    discountValueElem.textContent = `N ${discount_value.toFixed(2)}`;
                    totalFeeAfterElem.textContent = `N ${totalAmountAfter.toFixed(2)}`;

                    // Disable promo UI
                    promoCheckBox.disabled = true;
                    promoBtn.disabled = true;
                    promoBtn.style.cursor = 'not-allowed';
                    promoBtn.style.backgroundColor = '#ccc';
                    promoInput.disabled = true;

                    checkoutForm.style.display = 'block';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error("Error during promo validation:", error);
                alert('An error occurred. Please try again.');
            });
    });

    // Fetch and display order data (updated for pack structure)
    fetch('../v2/get_order_session.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Use either new structure or legacy data
                orderData = data.data || data.legacy_data;
                console.log("Order data retrieved from session:", orderData);

                // Populate the checkout page order table
                const orderTableBody = document.getElementById('orderSummaryTable').querySelector('tbody');
                orderTableBody.innerHTML = ""; // Clear existing rows

                // Get items from either packs structure or flat items array
                const itemsToDisplay = orderData.packs
                    ? Object.values(orderData.packs).flat()
                    : orderData.order_items;

                itemsToDisplay.forEach((item, index) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                    <td>${index + 1}</td>
                    ${orderData.packs ? `<td>${item.pack_id}</td>` : ''}
                    <td>${item.food_name}</td>
                    <td>${item.quantity}</td>
                    <td>N ${parseFloat(item.price_per_unit).toFixed(2)}</td>
                    <td class="total-price">N ${parseFloat(item.total_price).toFixed(2)}</td>
                `;
                    orderTableBody.appendChild(row);
                });

                // Calculate totals CONSISTENTLY with promo validation
                const subtotal = parseFloat(orderData.total_order || orderData.totals?.subtotal);
                const serviceFee = parseFloat(orderData.service_fee || orderData.totals?.service_fee);
                const deliveryFee = parseFloat(orderData.delivery_fee || orderData.totals?.delivery_fee);

                // Calculate final amount (applying discount to subtotal only if that's your business rule)
                let totalAmount;
                if (usePromo) {
                    // Apply discount to subtotal only, then add fees
                    totalAmount = (subtotal - discount_value) + serviceFee + deliveryFee;
                } else {
                    // No discount, just add everything
                    totalAmount = subtotal + serviceFee + deliveryFee;
                }

                // Populate the total amounts on the checkout page
                document.getElementById('total-order').textContent = `N ${subtotal.toFixed(2)}`;
                document.getElementById('service-fee').textContent = `N ${serviceFee.toFixed(2)}`;
                document.getElementById('delivery-fee').textContent = `N ${deliveryFee.toFixed(2)}`;
                document.getElementById('total-fee').textContent = `N ${totalAmount.toFixed(2)}`;

                // Store the final amount for checkout
                finalTotalAmount = totalAmount;
            } else {
                console.error("Failed to retrieve order data:", data.message);
                alert("Failed to retrieve order data. Please try again.");
            }
        })
        .catch(error => {
            console.error("Error fetching order data:", error);
            alert("An error occurred while fetching order data. Please try again.");
        });
    const clearInputFields = (...fields) => {
        fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) field.value = '';
        });
    };

    const togglePaymentDetails = (method) => {
        const paymentDetails = {
            'Card': () => {
                creditCardDetails.style.display = 'block';
                paypalDetails.style.display = 'none';
                bankTransferDetails.style.display = 'none';
                creditDetails.style.display = 'none';
                clearInputFields('bank_name', 'bank_account', 'paypal_email', 'customer_secret_answer');
            },
            'paypal': () => {
                creditCardDetails.style.display = 'none';
                paypalDetails.style.display = 'block';
                bankTransferDetails.style.display = 'none';
                creditDetails.style.display = 'none';
                clearInputFields('card_number', 'card_expiry', 'card_cvv', 'card_pin', 'bank_name', 'bank_account', 'customer_secret_answer');
            },
            'bank_transfer': () => {
                creditCardDetails.style.display = 'none';
                paypalDetails.style.display = 'none';
                bankTransferDetails.style.display = 'block';
                creditDetails.style.display = 'none';
                clearInputFields('card_number', 'card_expiry', 'card_cvv', 'card_pin', 'paypal_email', 'customer_secret_answer');
            },
            'credit': () => {
                creditCardDetails.style.display = 'none';
                paypalDetails.style.display = 'none';
                bankTransferDetails.style.display = 'none';
                creditDetails.style.display = 'block';
                clearInputFields('card_number', 'card_expiry', 'card_cvv', 'card_pin', 'bank_name', 'bank_account', 'paypal_email');
            }
        };

        (paymentDetails[method.value] || (() => { }))();
    };

    paymentMethods.forEach(method => {
        method.addEventListener('change', () => togglePaymentDetails(method));
    });

    const formatExpiryDate = (expiryDate) => {
        const [year, month] = expiryDate.split('-');
        return `${month.padStart(2, '0')}-${year}`;
    };

    // Payment form submission handler
    checkoutForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        // Show confirmation dialog
        const isConfirmed = confirm('Are you sure you want to proceed with the payment?');
        if (!isConfirmed) {
            alert("Your Order Payment Process has been Cancelled!");
            return;
        }

        // Create and show loader
        const loaderOverlay = document.createElement('div');
        loaderOverlay.className = 'loader-overlay';
        loaderOverlay.innerHTML = '<div class="roller-loader"></div>';
        document.body.appendChild(loaderOverlay);

        // Then update your payment processing code
        try {
            // Debug: Log start
            console.log('Starting payment process...');

            // 1. Debug get_order_session.php
            console.log('Fetching order session data...');
            const sessionResponse = await fetch('../v2/get_order_session.php');

            // Check response status
            if (!sessionResponse.ok) {
                throw new Error(`Server returned ${sessionResponse.status} ${sessionResponse.statusText}`);
            }

            // Get raw response text first
            const responseText = await sessionResponse.text();
            console.log('Raw response:', responseText.substring(0, 200) + '...'); // First 200 chars

            // Check for PHP errors
            if (responseText.includes('<b>Warning</b>') ||
                responseText.includes('<b>Notice</b>') ||
                responseText.includes('<b>Fatal error</b>') ||
                responseText.includes('<br />')) {
                console.error('PHP error detected in response');
                throw new Error('Server error detected. Please check the PHP endpoint.');
            }

            // Try to parse JSON
            let sessionData;
            try {
                sessionData = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Full response:', responseText);
                throw new Error('Invalid JSON response from server');
            }

            if (!sessionData.success) {
                throw new Error("Failed to retrieve order data: " + (sessionData.message || 'Unknown error'));
            }

            // Normalize the order data
            const rawOrderData = sessionData.data || sessionData.legacy_data;
            const orderData = normalizeOrderData(rawOrderData);
            console.log('Normalized order data:', orderData);

            // Get form values
            const selectedPaymentMethod = document.querySelector('input[name="payment_method"]:checked')?.value;
            const customer_secret_answer = document.getElementById('customer_secret_answer')?.value;
            const customer_token = document.getElementById('customer_token')?.value;

            if (!selectedPaymentMethod || !customer_secret_answer || !customer_token) {
                throw new Error('Missing required form fields');
            }

            // Calculate totals
            const subtotal = orderData.total_order;
            const serviceFee = orderData.service_fee;
            const deliveryFee = orderData.delivery_fee;
            let totalAmount = ((subtotal - (usePromo ? discount_value : 0)) + serviceFee + deliveryFee);

            // Generate receipts
            console.log('Generating PDF receipt...');
            const receiptHtml = generateReceiptHtml(orderData);

            // Prepare payment data
            let paymentDetails = {
                payment_method: selectedPaymentMethod,
                order_items: orderData.packs && Object.keys(orderData.packs).length > 0
                    ? Object.values(orderData.packs).flat()
                    : orderData.order_items,
                packs: orderData.packs,
                pack_count: orderData.pack_count,
                total_amount: totalAmount,
                service_fee: serviceFee,
                delivery_fee: deliveryFee,
                total_order: subtotal,
                using_promo: usePromo,
                discount: discount_value,
                discount_percent: discount_percent,
                promo_code: promoCode,
                customer_secret_answer: customer_secret_answer,
                customer_token: customer_token,
                receipt_html: receiptHtml
            };

            // Add payment method details
            if (selectedPaymentMethod === 'Card') {
                paymentDetails = {
                    ...paymentDetails,
                    card_number: document.getElementById('card_number')?.value,
                    card_expiry: formatExpiryDate(document.getElementById('card_expiry')?.value),
                    card_cvv: document.getElementById('card_cvv')?.value,
                    card_pin: document.getElementById('card_pin')?.value
                };
            } else if (selectedPaymentMethod === 'paypal') {
                paymentDetails.paypal_email = document.getElementById('paypal_email')?.value;
            } else if (selectedPaymentMethod === 'bank_transfer') {
                paymentDetails.bank_name = document.getElementById('bank_name')?.value;
                paymentDetails.bank_account = document.getElementById('bank_account')?.value;
            } else if (selectedPaymentMethod === 'credit') {
                paymentDetails.is_credit = true;
            }

            console.log('Sending payment data:', {
                ...paymentDetails,
                receipt_html: receiptHtml ? `HTML (${receiptHtml.length} chars)` : 'null'
            });

            // Send to server
            const response = await fetch('../v2/process_paymentOld.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(paymentDetails)
            });

            // Debug the response from process_paymentOld.php
            const responseText2 = await response.text();
            console.log('Payment response:', responseText2.substring(0, 200) + '...');

            if (!response.ok) {
                throw new Error(`Payment server returned ${response.status}`);
            }

            let data;
            try {
                data = JSON.parse(responseText2);
            } catch (parseError) {
                console.error('Payment response JSON error:', parseError);
                console.error('Full payment response:', responseText2);
                throw new Error('Invalid JSON response from payment server');
            }

            if (data.success) {
                alert('Your Order has been successfully received. Kindly wait for approval.');
                printReceiptBtn.style.display = 'block';
                placeOrderBtn.style.display = 'none';
                disableFormElements();
            } else {
                throw new Error(data.message || 'Payment processing failed');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error: ' + error.message);
        } finally {
            loaderOverlay.remove();
        }
    });

    // Function to disable form elements
    function disableFormElements() {
        // Disable radio buttons
        paymentMethods.forEach(method => {
            method.disabled = true;
        });

        // Disable promo checkbox and input
        promoCheckBox.disabled = true;
        promoInput.disabled = true;
        promoBtn.disabled = true;

        // Disable input fields in payment details sections
        const inputFields = document.querySelectorAll('input, select, textarea');
        inputFields.forEach(field => {
            field.disabled = true;
        });
    }

    bankNameSelect.addEventListener('change', () => {
        bankAccountInput.value = generateVirtualBankAccountNumber();
    });

    const generateVirtualBankAccountNumber = () => Math.floor(1000000000 + Math.random() * 9000000000).toString();

    const setMinMonth = () => {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        document.querySelector('input[type="month"]').min = `${year}-${month}`;
    };

    setMinMonth();

    // Print Receipt function
    function printReceipt(orderData) {
        // Generate the receipt HTML first to ensure it works
        const receiptHtml = generateReceiptHtml(orderData);

        const printWindow = window.open('', '_blank', 'width=800,height=600');
        printWindow.document.write(`
        <html>
        <head>
            <title>Receipt</title>
            <link rel="stylesheet" href="../css/receipt.css">
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                .receipt-container { max-width: 800px; margin: 0 auto; }
                .receipt-header { text-align: center; margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
                th { background: #f0f0f0; }
                .receipt-footer { text-align: center; margin-top: 30px; font-style: italic; }
                @media print {
                    body { padding: 15px; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            ${receiptHtml}
            <div class="receipt-footer">
                <p>Thank you for your purchase!</p>
            </div>
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(function() {
                        window.close();
                    }, 500);
                }
            </script>
        </body>
        </html>
    `);
        printWindow.document.close();
    }

    // Enhanced event listener with error handling
    const printButton = document.getElementById('receipt-btn');
    if (printButton) {
        printButton.addEventListener('click', function () {
            try {
                // Make sure orderData is available
                if (!orderData || typeof orderData !== 'object') {
                    throw new Error('Order data not available');
                }

                // Add loading state
                printButton.disabled = true;
                printButton.textContent = 'Generating Receipt...';

                // Call print function
                printReceipt(orderData);

            } catch (error) {
                console.error('Error generating receipt:', error);
                alert('Error generating receipt: ' + error.message);
            } finally {
                // Reset button state after a delay
                setTimeout(() => {
                    printButton.disabled = false;
                    printButton.textContent = 'Print Receipt';
                }, 2000);
            }
        });
    } else {
        console.warn('Print receipt button not found');
    }
    // Redirect to Order Page when the checkout Page is refreshed
    const navigationEntries = performance.getEntriesByType("navigation");
    if (navigationEntries.length > 0) {
        const navigationEntry = navigationEntries[0];
        if (navigationEntry.type === "reload") {
            window.location.href = '../v1/dashboard.php';
        }
    }
    // Function to generate PDF receipt
    // First, let's create a function to safely normalize order data
    function normalizeOrderData(orderData) {
        if (!orderData) return {};

        // Support both old and new structures
        const normalized = {
            // Copy all existing properties
            ...orderData,

            // Ensure we have consistent structure
            totals: orderData.totals || {},
            packs: orderData.packs || {},
            order_items: orderData.order_items || [],

            // Calculate missing values
            total_order: parseFloat(orderData.total_order || orderData.totals?.subtotal || 0),
            service_fee: parseFloat(orderData.service_fee || orderData.totals?.service_fee || 0),
            delivery_fee: parseFloat(orderData.delivery_fee || orderData.totals?.delivery_fee || 0),
            pack_count: orderData.pack_count || (orderData.packs ? Object.keys(orderData.packs).length : 1)
        };

        return normalized;
    }

    // Function to generate receipt HTML
    // Safe function to generate receipt HTML with fallbacks
    function generateReceiptHtml(orderData) {
        // Ensure we have safe data
        const safeData = normalizeOrderData(orderData);

        const now = new Date();
        const dateTime = now.toLocaleString();
        const paymentMethod = document.querySelector('input[name="payment_method"]:checked')?.value || 'Not specified';

        // Calculate totals with safe data
        const subtotal = safeData.total_order;
        const serviceFee = safeData.service_fee;
        const deliveryFee = safeData.delivery_fee;
        const discountValue = safeData.discount || 0;
        const totalAmount = subtotal + serviceFee + deliveryFee - discountValue;

        // Get items safely
        const itemsToDisplay = getItemsToDisplay(safeData);

        return `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>KaraKata Receipt</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #333; }
                .receipt-container { max-width: 800px; margin: 0 auto; }
                .receipt-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #eee; padding-bottom: 20px; }
                .receipt-header h1 { color: #2c3e50; margin-bottom: 5px; }
                .receipt-header h2 { color: #7f8c8d; margin-top: 0; }
                .order-info { margin-bottom: 20px; }
                .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
                .info-card { background: #f9f9f9; padding: 15px; border-radius: 8px; }
                .info-card h3 { margin-top: 0; color: #2c3e50; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
                th { background: #f0f0f0; font-weight: bold; }
                .pack-header { background: #e3f2fd; padding: 10px; margin: 15px 0; border-radius: 5px; }
                .totals { margin-top: 30px; text-align: right; }
                .total-row { display: flex; justify-content: space-between; padding: 8px 0; }
                .grand-total { border-top: 2px solid #007bff; padding-top: 15px; font-weight: bold; font-size: 1.2em; }
                .footer { text-align: center; margin-top: 40px; color: #7f8c8d; font-style: italic; }
            </style>
        </head>
        <body>
            <div class="receipt-container">
                <div class="receipt-header">
                    <h1>KaraKata</h1>
                    <h2>Order Receipt</h2>
                    <p>Date & Time: ${dateTime}</p>
                </div>
                
                <div class="info-grid">
                    <div class="info-card">
                        <h3>Order Information</h3>
                        <p><strong>Order #:</strong> ${safeData.order_id || 'Pending'}</p>
                        <p><strong>Payment Method:</strong> ${paymentMethod}</p>
                        <p><strong>Status:</strong> Processing</p>
                    </div>
                    
                    <div class="info-card">
                        <h3>Order Summary</h3>
                        <p><strong>Items:</strong> ${itemsToDisplay.length}</p>
                        <p><strong>Packs:</strong> ${safeData.pack_count}</p>
                    </div>
                </div>
                
                <h3>Order Items</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Unit Price (N)</th>
                            <th>Total (N)</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsToDisplay.map(item => `
                            <tr>
                                <td>${item.food_name || 'Unknown Item'}</td>
                                <td>${item.quantity || 0}</td>
                                <td>${parseFloat(item.price_per_unit || 0).toFixed(2)}</td>
                                <td>${parseFloat(item.total_price || 0).toFixed(2)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
                
                ${safeData.packs && Object.keys(safeData.packs).length > 0 ? `
                <h3>Pack Information</h3>
                ${Object.entries(safeData.packs).map(([packId, items]) => `
                    <div class="pack-header">
                        <h4>Pack: ${packId}</h4>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Unit Price (N)</th>
                                <th>Total (N)</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${(items || []).map(item => `
                                <tr>
                                    <td>${item.food_name || 'Unknown Item'}</td>
                                    <td>${item.quantity || 0}</td>
                                    <td>${parseFloat(item.price_per_unit || 0).toFixed(2)}</td>
                                    <td>${parseFloat(item.total_price || 0).toFixed(2)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `).join('')}
                ` : ''}
                
                <div class="totals">
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span>N ${subtotal.toFixed(2)}</span>
                    </div>
                    <div class="total-row">
                        <span>Delivery Fee:</span>
                        <span>N ${deliveryFee.toFixed(2)}</span>
                    </div>
                    <div class="total-row">
                        <span>Service Fee:</span>
                        <span>N ${serviceFee.toFixed(2)}</span>
                    </div>
                    ${discountValue > 0 ? `
                    <div class="total-row">
                        <span>Discount:</span>
                        <span>-N ${discountValue.toFixed(2)}</span>
                    </div>
                    ` : ''}
                    <div class="total-row grand-total">
                        <span>Grand Total:</span>
                        <span>N ${totalAmount.toFixed(2)}</span>
                    </div>
                </div>
                
                <div class="footer">
                    <p>Thank you for choosing KaraKata!</p>
                    <p>For any inquiries, please contact support@karakata.com</p>
                </div>
            </div>
        </body>
        </html>
    `;
    }

    // Helper function to safely get items to display
    function getItemsToDisplay(orderData) {
        const safeData = normalizeOrderData(orderData);

        if (safeData.packs && Object.keys(safeData.packs).length > 0) {
            // Flatten packs into single array of items
            return Object.values(safeData.packs).flat().filter(item => item);
        } else if (safeData.order_items && safeData.order_items.length > 0) {
            return safeData.order_items;
        } else {
            return [];
        }
    }
    // Helper function to calculate subtotal from items if not provided
    function calculateSubtotalFromItems(orderData) {
        const items = getItemsToDisplay(orderData);
        return items.reduce((sum, item) => sum + parseFloat(item.total_price || 0), 0);
    }

});