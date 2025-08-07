<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Your Experience</title>
    <link rel="stylesheet" href="../css/order_rating.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
    <?php include('customer_navbar.php'); ?>
    <div class="rating-container">
        <div class="header">
            <h1>How was your experience?</h1>
            <p>Please rate your driver and order</p>
        </div>

        <div class="order-details">
            <div class="detail-card">
                <h3>Order #</h3>
                <p>Delivered on: May 15, 2023 at 2:30 PM</p>
            </div>
        </div>

        <form id="ratingForm">
            <div class="rating-section">
                <h2>Rate Your Driver</h2>
                <div class="driver-info">
                    <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAMwAAADACAMAAAB/Pny7AAAAJ1BMVEXQ0NDw8PDKysrX19fp6enT09Ps7Ozc3Nzm5ubj4+Pg4ODNzc3z8/NXAHyOAAADYklEQVR4nO2c25KrIBREdRBQJ///vcdb5kTDVUFpqtdzqmRVb1Bhm6YhhBBCCCGEEEIIIYQQQgghhBBCCGnkzNODSIIUehwmRi3AhSaTvn2jBo2sI8eu3THgpiP69ogaQW0MLnM4kDaiM7nMpfb0yOKRxlxmACtttLm0Sj89tli0ssq0PVihycHu0rZg0QhHMFM0WLPGPmNmOqw6sy5lK+PT44vBfL/8kEGqM9daNoP0GPDrk0FaAbwyUCtATclUNWccT5krUKtZ43yaQXuecdcZ1Py3v5kBTpnGU2dYVeaOBi2Y6bnZOmuU+H16cNHY9wCeHtkJbPcaRBfLq7PCdJl3Z7/mTYe2kH0gup2OQtwA/ED0nVqElOpHwGXsgBB6nIA/niGEEEIIIYQQUjcSezdhh1SqGhvZta16ehCJ2JpZqtghkdsJg6rARv6dluDPm93JD3g2+1Ms7GyOJ3LINt/tUrirgOmkFDUbcxsbWFfBhq0lD9HG3l6IZ+NqlUSzcbd9Ytn4WliRuth9LkjZ+F1wbEJcUCotzAUjm1AXBJtwl/IrTTobJLFsfE3Fj1daRONJrMvdNnJUwU2acTV2v83ShBZoc8blVpu1oS6oHzC+xjabu96k3x+jBWRzLpcbs/n/YZ3X5rzLTTb644Kjuy3wbI2t3LCrrncXdM6bay432OjDBQeHy4UaW8m8Cnx/hjLYKu1qLjNZdweF4YKWSkvhMmWTz+VYYwsvYzbXa2yzyZWN0cVsk8olm43FZbIZf44uSWoso41+Wa/3OsybmHexAJv0d09rLgu7FTplLlls3C67ZwH3PzycIfHHusJeY2+bfC6JbXy5zGyVlrrGVhJWmu8z55Wl0nLkMpNsl8N03zfaTD/N5JKs0kJqbGXI55Iom7AaW0l23zeRwCbGJTOXbQpyuWxTlMtFm8JcLtkU53LBpkCX0zZFupy8exbqciqbYl1O2BTsEl1pRbtE2hTuEmVTvEvE7qD7nw0Loa8ml5maXIJkQt+RH6cmlwAZlBprA2RwcvHLILn4ZML3lEqgolw8MvLp0UVSUS5OGaA1eaOiXBwygC5WGbwaa60ykC4WGUwXswyoi1FGZz0gysi3ikTNxSQjhg4VQzICFsvSTAghhBBCCKmUn4ponn7VTck/evw2WVjiDMwAAAAASUVORK5CYII=" alt="Driver Photo" class="driver-photo">
                    <span class="driver-name">John D.</span>
                </div>

                <div class="rating-stars">
                    <p>How was your driver?</p>
                    <div class="stars" id="driverRating">
                        <i class="far fa-star" data-rating="1"></i>
                        <i class="far fa-star" data-rating="2"></i>
                        <i class="far fa-star" data-rating="3"></i>
                        <i class="far fa-star" data-rating="4"></i>
                        <i class="far fa-star" data-rating="5"></i>
                    </div>
                </div>

                <div class="rating-comment">
                    <label for="driverComment">Additional feedback (optional)</label>
                    <textarea id="driverComment"
                        placeholder="Was there anything special about your driver's service?"></textarea>
                </div>
            </div>

            <div class="rating-section">
                <h2>Rate Your Order</h2>
                <div class="rating-criteria">
                    <div class="criterion">
                        <p>Food Quality</p>
                        <div class="stars" id="foodQuality">
                            <i class="far fa-star" data-rating="1"></i>
                            <i class="far fa-star" data-rating="2"></i>
                            <i class="far fa-star" data-rating="3"></i>
                            <i class="far fa-star" data-rating="4"></i>
                            <i class="far fa-star" data-rating="5"></i>
                        </div>
                    </div>

                    <div class="criterion">
                        <p>Packaging</p>
                        <div class="stars" id="packaging">
                            <i class="far fa-star" data-rating="1"></i>
                            <i class="far fa-star" data-rating="2"></i>
                            <i class="far fa-star" data-rating="3"></i>
                            <i class="far fa-star" data-rating="4"></i>
                            <i class="far fa-star" data-rating="5"></i>
                        </div>
                    </div>

                    <div class="criterion">
                        <p>Delivery Time</p>
                        <div class="stars" id="deliveryTime">
                            <i class="far fa-star" data-rating="1"></i>
                            <i class="far fa-star" data-rating="2"></i>
                            <i class="far fa-star" data-rating="3"></i>
                            <i class="far fa-star" data-rating="4"></i>
                            <i class="far fa-star" data-rating="5"></i>
                        </div>
                    </div>
                </div>

                <div class="rating-comment">
                    <label for="orderComment">Order feedback (optional)</label>
                    <textarea id="orderComment" placeholder="How can we improve your order experience?"></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="submit-btn">Submit Rating</button>
            </div>
        </form>
    </div>

    <!-- <div class="thank-you-modal" id="thankYouModal">
        <div class="modal-content">
            <i class="fas fa-check-circle"></i>
            <h2>Thank You!</h2>
            <p>Your feedback helps us improve our service.</p>
            <button class="close-btn" id="closeModal">Close</button>
        </div>
    </div> -->

    <!-- Add these modals to your HTML (place before closing </body> tag) -->
    <div class="modal" id="confirmModal">
        <div class="modal-content">
            <h2>Confirm Submission</h2>
            <p>Are you sure you want to submit your rating? You won't be able to modify it later.</p>
            <div class="modal-actions">
                <button class="btn-secondary" id="cancelSubmit">Cancel</button>
                <button class="btn-primary" id="confirmSubmit">Submit Rating</button>
            </div>
        </div>
    </div>

    <div class="modal" id="errorModal">
        <div class="modal-content">
            <i class="fas fa-exclamation-circle error-icon"></i>
            <h2>Error</h2>
            <p id="errorMessage"></p>
            <div class="modal-actions">
                <button class="btn-primary" id="closeErrorModal">OK</button>
            </div>
        </div>
    </div>

    <!-- Existing thank you modal -->
    <div class="modal" id="thankYouModal">
        <div class="modal-content">
            <i class="fas fa-check-circle success-icon"></i>
            <h2>Thank You!</h2>
            <p>Your feedback helps us improve our service.</p>
            <button class="btn-primary" id="closeModal">Close</button>
        </div>
    </div>

    <script src="../scripts/order_rating.js"></script>
</body>

</html>