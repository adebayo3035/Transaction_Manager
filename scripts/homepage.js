$(document).ready(function () {
    $(document).ready(function () {
        $('.testimonial-slider').slick({
            dots: true,
            infinite: true,
            speed: 500,
            slidesToShow: 2,
            slidesToScroll: 1,
            autoplay: true,
            autoplaySpeed: 3000,
            arrows: false
        });
    });

    // Fetch random customer names
    $.ajax({
        url: 'backend/fetch_random_customers.php',
        method: 'GET',
        success: function (data) {
            $('.customer-name').each(function (index) {
                if (data[index]) {
                    $(this).text('- ' + data[index]);
                }
            });
        },
        error: function (error) {
            console.error('Error fetching customer names:', error);
        }
    });
});
