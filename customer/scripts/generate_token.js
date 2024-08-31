document.addEventListener('DOMContentLoaded', () => {
    const btnCopyToken = document.getElementById('copy-token');
    btnCopyToken.disabled = true;
    btnCopyToken.style.cursor = "not-allowed"
    btnCopyToken.style.backgroundColor = "#ccc";

    document.getElementById('generate-token').addEventListener('click', function () {
        fetch('../v2/generate_token.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('token').value = data.token;
                    btnCopyToken.disabled = false;
                    btnCopyToken.style.backgroundColor = '#2883a7';
                    btnCopyToken.style.cursor = 'pointer';
                    startTimer(); // Start the timer after the token is successfully generated
                } else {
                    alert('Failed to generate token');
                }
            });

    });
})


document.getElementById('copy-token').addEventListener('click', function() {
    const tokenInput = document.getElementById('token');
    const copyButton = document.getElementById('copy-token');

    if (tokenInput.value.trim() === '') {
        alert('Nothing to copy!');
        return;
    }

    navigator.clipboard.writeText(tokenInput.value).then(() => {
        alert('Token copied successfully!');
        copyButton.disabled = true;
        copyButton.style.backgroundColor = '#ccc';
        copyButton.style.cursor = 'not-allowed';
        
    }).catch(err => {
        console.error('Failed to copy: ', err);
        alert('Failed to copy token.');
    });
    tokenInput.value = "";
});



const FULL_DASH_ARRAY = 339.292; // 2 * π * r (circumference of the circle)
const TIME_LIMIT = 30; // seconds
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
    const btnCopyToken = document.getElementById('copy-token');
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
        const tokenInput = document.getElementById('token');

        // If the time is up, stop the timer
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            tokenBtn.value = ""; // Clear the token input field
            tokenBtn.textContent = 'Regenerate Token'; // Change the button text
            tokenBtn.disabled = false; // Re-enable the button
            tokenBtn.style.backgroundColor = "#a72828";
            tokenBtn.style.cursor = "pointer"
            tokenInput.value = "";
            btnCopyToken.disabled = true;
            btnCopyToken.style.cursor = "not-allowed"
            btnCopyToken.style.backgroundColor = "#ccc";
            timerText.textContent = "";
            // Reset the circle progress bar for the next cycle
            circle.style.strokeDashoffset = FULL_DASH_ARRAY;
        }
    }, 1000);
}
