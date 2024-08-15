document.getElementById('generate-token').addEventListener('click', function() {
    fetch('../v2/generate_token.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('token').value = data.token;
                startTimer(); // Start the timer after the token is successfully generated
            } else {
                alert('Failed to generate token');
            }
        });

});

function copyToken() {
    const tokenInput = document.getElementById('token');
    tokenInput.select();
    document.execCommand('copy');
    alert('Token copied to clipboard');
}

const FULL_DASH_ARRAY = 339.292; // 2 * π * r (circumference of the circle)
const TIME_LIMIT = 60; // seconds
let timePassed = 0;
let timeLeft = TIME_LIMIT;

const circle = document.querySelector('.progress-ring__circle');
const timerText = document.getElementById('timer');

// Set initial circle dash offset
circle.style.strokeDashoffset = FULL_DASH_ARRAY;

function startTimer() {
    // Reset the timer values for a new cycle
    timePassed = 0;
    timeLeft = TIME_LIMIT;
    const tokenBtn = document.getElementById('generate-token')
    tokenBtn.disabled = true; // Disable the button initially
    tokenBtn.textContent = 'Token Active';
    tokenBtn.style.cursor = "not-allowed"
    tokenBtn.style.backgroundColor = "#ccc";

    const timerInterval = setInterval(() => {
        timePassed += 1;
        timeLeft = TIME_LIMIT - timePassed;

        // Update the circle progress bar
        const progress = FULL_DASH_ARRAY - (timeLeft / TIME_LIMIT) * FULL_DASH_ARRAY;
        circle.style.strokeDashoffset = progress;

        // Update the text inside the circle
        timerText.textContent = `${timeLeft}`;

        // If the time is up, stop the timer
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            tokenBtn.value = ""; // Clear the token input field
           tokenBtn.textContent = 'Regenerate Token'; // Change the button text
           tokenBtn.disabled = false; // Re-enable the button
           tokenBtn.style.backgroundColor = "#a72828";
           tokenBtn.style.cursor = "pointer"
            // Reset the circle progress bar for the next cycle
            circle.style.strokeDashoffset = FULL_DASH_ARRAY;
        }
    }, 1000);
}
