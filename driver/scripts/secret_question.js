document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('adminForm');
    const secret_question = document.getElementById('secret_question');
    // Function to refresh the page after 4 seconds of fetching Data
    const refreshPage = () => {
        setTimeout(() => {
            location.reload();
        }, 4000); // 4 seconds in milliseconds
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        // Fetch values when the form is submitted
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;

        let customerInformation = {
            email: email,
            password: password
        };

        try {
            const response = await fetch('../v2/secret_question.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(customerInformation)
            });

            const data = await response.json();
            secret_question.textContent = `Your secret question is: ${data.secret_question}`;
            secret_question.style.color = data.success ? 'green' : 'red';

            if (data.success) {
                // Display success message
                alert("Your Secret Question is: " + data.secret_question);
                console.log("Your Secret Question is: " + data.secret_question);
            } else {
                console.error('Unable to fetch Secret Question:', data.message);
                alert('Unable to fetch Secret Question: ' + data.message); // Alert failure message
            }
        } catch (error) {
            console.error('An error occurred:', error);
            alert('An error occurred while fetching the secret question.');
        }
        refreshPage();
    });
    
   
});
