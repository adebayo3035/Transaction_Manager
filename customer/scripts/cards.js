document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('.modal .close').addEventListener('click', () => {
        document.getElementById('orderModal').style.display = 'none';
        document.getElementById('addCardsForm').reset();
    });

    window.addEventListener('click', (event) => {
        if (event.target === document.getElementById('orderModal')) {
            document.getElementById('orderModal').style.display = 'none';
        }
    });

    const monthControl = document.getElementById('expiry_date');
    const formattedExpiryDateInput = document.getElementById('formatted_expiry_date');

});

function toggleModal() {
    let modal = document.getElementById("orderModal");
    modal.style.display = (modal.style.display === "none" || modal.style.display === "") ? "block" : "none";
}

const monthControl = document.querySelector('input[type="month"]');

// Function to set the minimum value of the month input
function setMinMonth() {
    // Get the current date
    const now = new Date();

    // Extract the current year and month
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0'); // Months are zero-based, so add 1

    // Format the date in YYYY-MM
    const currentMonth = `${year}-${month}`;

    // Set the min attribute of the input element
    monthControl.min = currentMonth;
}

// Call the function to set the minimum value
setMinMonth();

document.getElementById('addCardsForm').addEventListener('submit', function (e) {
    e.preventDefault();

   
    // Extract the expiry date value
    const expiryDateInput = document.getElementById('expiry_date').value;
    const [year, month] = expiryDateInput.split('-');

    // Format it as MM/YY for display
    const formattedExpiryDate = `${month}/${year.slice(-2)}`;
    document.getElementById('formatted_expiry_date').value = formattedExpiryDate;

    // Format it as YYYY-MM for PHP processing
    const formattedExpiryDateYYYYMM = `${month.padStart(2, '0')}-${year}`;
    const formData = new FormData(this);
    formData.set('formatted_expiry_date', formattedExpiryDateYYYYMM);

    fetch('../v2/add_cards.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log("Card has been Successfully added");
                alert('Your Card has been successfully added');
                modal.style.display = 'none';
                // document.getElementById("orderModal").style.display = "none";
                // window.location.reload();
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error: ' + error.message);
        });
});
