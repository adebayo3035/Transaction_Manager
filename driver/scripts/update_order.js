document.addEventListener('DOMContentLoaded', function () {
    // Reload the page every 120 seconds
    setInterval(() => window.location.reload(), 120000);

    // Constants for DOM elements
    const UPDATE_DELIVERY_STATUS = document.getElementById('updateDeliveryStatus');
    const ORDER_SUMMARY_TABLE = document.getElementById('orderSummaryTable').querySelector('tbody');
    const SELECT_ELEMENT = document.getElementById('order-id');
    const INPUT_ELEMENT = document.getElementById('current-status');
    const CUSTOMER_NUMBER = document.getElementById('customer-id');
    const DELIVERY_AUTH = document.getElementById('delivery_auth');
    const ORDER_STATUS = document.getElementById('order-status');
    const NEW_STATUS = document.getElementById('new-status');
    const CANCEL_REASON_CONTAINER = document.getElementById('cancelReasonContainer');
    const CANCEL_REASON_LABEL = document.getElementById('lblCancelReason');

    // Event listener for order ID selection
    SELECT_ELEMENT.addEventListener('change', handleOrderSelection);

    // Event listener for order status change
    ORDER_STATUS.addEventListener('change', handleOrderStatusChange);

    // Event listener for updating delivery status
    UPDATE_DELIVERY_STATUS.addEventListener('click', updateOrderStatus);

    // Function to handle order selection
    function handleOrderSelection() {
        const selectedOption = SELECT_ELEMENT.options[SELECT_ELEMENT.selectedIndex];
        const deliveryStatus = selectedOption.getAttribute('data-status');

        INPUT_ELEMENT.value = deliveryStatus;

        const isOptionSelected = SELECT_ELEMENT.value !== "";
        NEW_STATUS.style.display = isOptionSelected ? "flex" : "none";
        DELIVERY_AUTH.style.display = "none";
        UPDATE_DELIVERY_STATUS.style.display = "none";
        CANCEL_REASON_CONTAINER.style.display = "none";
    }

    // Function to handle order status change
    function handleOrderStatusChange() {
        const orderValue = ORDER_STATUS.value;
        const isDeliveryOrCancel = orderValue === "Delivered" || orderValue === "Cancelled on Delivery" || orderValue === "Terminated";

        DELIVERY_AUTH.style.display = isDeliveryOrCancel ? "flex" : "none";
        UPDATE_DELIVERY_STATUS.style.display = (isDeliveryOrCancel || orderValue === "In Transit") ? "flex" : "none";

        CANCEL_REASON_CONTAINER.style.display = orderValue === "Cancelled on Delivery" || orderValue === "Terminated" ? "flex" : "none";
        if(orderValue === "Terminated") {
            CANCEL_REASON_LABEL.textContent = "Reason for Termination";
        }
        else if(orderValue === "Cancelled on Delivery") {
            CANCEL_REASON_LABEL.textContent = "Reason for Cancellation";
        }
    }

    // Function to update order status
    function updateOrderStatus() {
    const orderID = SELECT_ELEMENT.value;
    const orderStatus = ORDER_STATUS.value;
    const currentStatus = INPUT_ELEMENT.value;
    const customerID = CUSTOMER_NUMBER.value;
    const deliveryPin = document.getElementById('delivery_pin').value;
    const cancelReason = document.getElementById('cancelReason').value;

    const orderData = {
        id: orderID,
        orderStatus: orderStatus,
        currentStatus: currentStatus,
        customerID: customerID,
        deliveryPin: deliveryPin,
        cancelReason: cancelReason
    };

    // Show modal
    const modal = document.getElementById('confirmationModal');
    const message = document.getElementById('confirmationMessage');
    message.textContent = `Are you sure you want to change the order status from "${currentStatus}" to "${orderStatus}"?`;
    modal.style.display = 'block';

    // Set up event listeners for buttons
    document.getElementById('confirmCancel').onclick = function() {
        modal.style.display = 'none';
    };

    document.getElementById('confirmOk').onclick = function() {
        modal.style.display = 'none';
        
        // Proceed with the update
        fetch('../v2/update_order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(orderData)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Delivery Status has been successfully updated to ${orderStatus}.`);
                    location.reload();
                } else {
                    alert(`Failed to update Order Status: ${data.message}`);
                }
            })
            .catch(error => {
                console.error('Error updating Order Status:', error);
                alert('An error occurred while updating the order status. Please try again.');
            });
    };
}
});