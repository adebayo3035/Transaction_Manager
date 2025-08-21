document.addEventListener('DOMContentLoaded', function () {
    // Initialize with extracted order details
    const { orderId, driverId } = populateOrderRatingPage();
    limitInputCharsWithPopup("driverComment", 50);
    limitInputCharsWithPopup("orderComment", 50);

    // Initialize ratings
    const ratingElements = {
        driverRating: 0,
        foodQuality: 0,
        packaging: 0,
        deliveryTime: 0
    };

    function populateOrderRatingPage() {
        // Get the stored order data from sessionStorage
        const orderData = JSON.parse(sessionStorage.getItem('orderDetails'));

        // Check if data exists and has the expected structure
        if (!orderData || !orderData.order_id || !orderData.driverID) {
            // alert('Order details not available or incomplete.');
            sessionStorage.removeItem('orderDetails');
            window.location.href = '../v1/view_orders.php'; // Redirect if no data
            return {};
        }

        // Extract necessary information from the transformed data structure
        const driverFullName = `${orderData.driver_firstname || ''} ${orderData.driver_lastname || ''}`.trim();
        const driverPhoto = orderData.driver_photo
            ? `../../backend/driver_photos/${orderData.driver_photo}`
            : '../../assets/images/default-driver.jpg'; // Fallback image
        const orderId = orderData.order_id;
        const driverId = orderData.driverID;
        const deliveryDate = new Date(orderData.order_date).toLocaleString();

        // Update UI elements
        const detailCard = document.querySelector('.detail-card');
        if (detailCard) {
            const title = detailCard.querySelector('h3');
            const date = detailCard.querySelector('p');

            if (title) title.textContent = `Order #${orderId}`;
            if (date) date.textContent = `Delivered on: ${deliveryDate}`;
        }

        const driverNameElement = document.querySelector('.driver-name');
        const driverPhotoElement = document.querySelector('.driver-photo');

        if (driverNameElement) driverNameElement.textContent = driverFullName || 'Driver information not available';
        if (driverPhotoElement) {
            driverPhotoElement.src = driverPhoto;
            driverPhotoElement.alt = driverFullName || 'Driver photo';
        }

        // Return essential IDs for later use
        return {
            orderId,
            driverId
        };
    }

    // Star rating functionality (unchanged)
    document.querySelectorAll('.stars').forEach(starsContainer => {
        const stars = starsContainer.querySelectorAll('i');
        const ratingId = starsContainer.id;

        stars.forEach(star => {
            star.addEventListener('mouseover', () => {
                const ratingValue = parseInt(star.getAttribute('data-rating'));
                highlightStars(stars, ratingValue);
            });

            star.addEventListener('mouseout', () => {
                highlightStars(stars, ratingElements[ratingId] || 0);
            });

            star.addEventListener('click', () => {
                ratingElements[ratingId] = parseInt(star.getAttribute('data-rating'));
                highlightStars(stars, ratingElements[ratingId]);
            });
        });
    });

    // Form submission
    // Form submission - updated with confirmation flow
    const ratingForm = document.getElementById('ratingForm');
    let pendingRatingData = null; // Store data while confirming

    ratingForm.addEventListener('submit', function (e) {
        e.preventDefault();

        // Validate driver rating
        if (ratingElements.driverRating === 0) {
            showError('Please rate your driver before submitting');
            return;
        }

        // Store the data for potential submission
        pendingRatingData = {
            order_id: orderId,
            driver_id: driverId,
            driver_rating: ratingElements.driverRating,
            driver_comment: document.getElementById('driverComment').value,
            food_rating: ratingElements.foodQuality || null,
            packaging_rating: ratingElements.packaging || null,
            delivery_time_rating: ratingElements.deliveryTime || null,
            order_comment: document.getElementById('orderComment').value
        };

        // Show confirmation modal instead of submitting directly
        showModal('confirmModal');
    });

    // Confirm submission handler
    document.getElementById('confirmSubmit').addEventListener('click', async function () {
        hideModal('confirmModal');

        try {
            const response = await fetch('../v2/order_rating.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(pendingRatingData)
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Failed to submit rating');
            }

            if (result.success) {
                showModal('thankYouModal');
                sessionStorage.removeItem('orderDetails');
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            showError(error.message || 'Something went wrong. Please try again later.');
        } finally {
            pendingRatingData = null;
        }
    });

    // Cancel submission handler
    document.getElementById('cancelSubmit').addEventListener('click', function () {
        hideModal('confirmModal');
        pendingRatingData = null;
    });

    // Error modal close handler
    document.getElementById('closeErrorModal').addEventListener('click', function () {
        hideModal('errorModal');
    });

    // Thank you modal close handler (existing)
    document.getElementById('closeModal').addEventListener('click', function () {
        hideModal('thankYouModal');
        window.location.href = '../v1/order_rating.php';
    });

    // Modal control functions
    function showModal(modalId) {
        document.getElementById(modalId).style.display = 'flex';
    }

    function hideModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    function showError(message) {
        document.getElementById('errorMessage').textContent = message;
        showModal('errorModal');
    }

    function highlightStars(stars, upToIndex) {
        stars.forEach((star, index) => {
            star.classList.toggle('active', index < upToIndex);
            star.classList.toggle('fas', index < upToIndex);
            star.classList.toggle('far', index >= upToIndex);
        });
    }
    function limitInputCharsWithPopup(inputId, maxLength = 100) {
        const input = document.getElementById(inputId);

        if (input) {
            input.addEventListener("input", function () {
                if (this.value.length > maxLength) {
                    this.value = this.value.substring(0, maxLength);

                    // show popup only once when limit is reached
                    if (!this.dataset.limitReached) {
                        showError("You have reached the maximum character limit of " + maxLength + ".");
                        this.dataset.limitReached = "true";
                    }
                } else {
                    this.dataset.limitReached = ""; // reset if user deletes characters
                }
            });
        }
    }
});