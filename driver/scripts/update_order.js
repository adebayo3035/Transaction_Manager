document.addEventListener('DOMContentLoaded', function () {
    setInterval(function () {
        window.location.reload();
    }, 120000);
    const updateDeliveryStatus = document.getElementById('updateDeliveryStatus');
    const orderSummaryTable = document.getElementById('orderSummaryTable').querySelector('tbody');
    // Get references to the select and input elements
    var selectElement = document.getElementById('order-id');
    var inputElement = document.getElementById('current-status');
    const deliveryAuth = document.getElementById('delivery_auth');
    const orderStatus = document.getElementById('order-status');
    const newStatus = document.getElementById('new-status');
    const cancelReasonContainer = document.getElementById("cancelReasonContainer")



    // Add an event listener to the select element
    selectElement.addEventListener('change', function () {
        // Get the selected option and its delivery status
        var selectedOption = selectElement.options[selectElement.selectedIndex];
        var deliveryStatus = selectedOption.getAttribute('data-status');

        // Set the hidden input value to the delivery status
        inputElement.value = deliveryStatus;

        // Toggle visibility of elements based on the selected option value
        var isOptionSelected = selectElement.value !== "";
        newStatus.style.display = isOptionSelected ? "flex" : "none";
        deliveryAuth.style.display = "none";
        updateDeliveryStatus.style.display = "none"
        cancelReasonContainer.style.display = "none";
        // updateDeliveryStatus.style.display = isOptionSelected ? "flex" : "none";
    });

   // Add an event listener to the new Order Status
orderStatus.addEventListener('change', function () {
    var orderValue = orderStatus.value;
    var isDeliveryOrCancel = orderValue === "Delivered" || orderValue === "Canceled";

    // Toggle visibility for delivery authorization and update button based on the order status
    deliveryAuth.style.display = isDeliveryOrCancel ? "flex" : "none";
    updateDeliveryStatus.style.display = (isDeliveryOrCancel || orderValue === "In Transit") ? "flex" : "none";

    // Show the cancellation reason input box only if the status is "Canceled"
    if (orderValue === "Canceled") {
        cancelReasonContainer.style.display = "flex";  // Show the reason input box
    } else {
        cancelReasonContainer.style.display = "none";   // Hide the reason input box
    }
});


    updateDeliveryStatus.addEventListener('click', function () {
        const orderID = document.getElementById('order-id').value;
        const orderStatus = document.getElementById('order-status').value;
        const currentStatus = document.getElementById('current-status').value;
        const deliveryPin = document.getElementById('delivery_pin').value;
        const cancelReason = document.getElementById('cancelReason').value;
        const orderData = {
            id: orderID,
            orderStatus: orderStatus,
            currentStatus: currentStatus,
            deliveryPin: deliveryPin,
            cancelReason : cancelReason
        };
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
                    console.log(data.message)
                    alert('Order updated successfully.');
                    location.reload();

                } else {
                    alert('Failed to update Order Status: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error updating Order Status:', error);
            });
        // this.location.reload()
    });

});
