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
});

document.addEventListener('DOMContentLoaded', (event) => {
    event.preventDefault();
    fetch('../transaction_manager/backend/fetch_random_customers.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const customerNames = document.querySelectorAll('.customerName');
                customerNames.forEach((element, index) => {
                    const customer = data.customer_names[index % data.customer_names.length];
                    element.textContent = `- ${customer.firstname} ${customer.lastname}`;
                });
            } else {
                console.error('Failed to fetch Customer Information:', data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching Customers Info:', error);
        });

})

function toggleReadMore() {
    const aboutText = document.getElementById('aboutText');
    const readMoreLink = document.getElementById('readMoreLink');

    if (readMoreLink.innerText === "Read More") {
        aboutText.style.height = "auto";
        readMoreLink.innerText = "Read Less";
    } else {
        aboutText.style.height = "200px"; // Same height as defined in the CSS
        readMoreLink.innerText = "Read More";
    }
}