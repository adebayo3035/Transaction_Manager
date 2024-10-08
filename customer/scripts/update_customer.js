document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('customerInfoForm');
    const updateOption = document.getElementById('update_option');
    const updateFields = document.getElementById('updateFields');
    const currentDataInput = document.getElementById('current_data');
    const newDataInput = document.getElementById('new_data');
    const confirmNewDataInput = document.getElementById('confirm_new_data');
    const customerInfoMessage = document.getElementById('customerInfoMessage');

    updateOption.addEventListener('change', function () {
       
        if (this.value !== "") {
            // Show the update fields
            updateFields.style.display = 'block';
    
            // Update placeholders
            currentDataInput.placeholder = `Current ${updateOption.value.replace('_', ' ')}`;
            newDataInput.placeholder = `New ${updateOption.value.replace('_', ' ')}`;
            confirmNewDataInput.placeholder = `Confirm New ${updateOption.value.replace('_', ' ')}`;
    
            // Update input types based on selected option
            switch (updateOption.value) {
                case 'email':
                    currentDataInput.type = 'email';
                    currentDataInput.style.textTransform = 'lowercase';
                    currentDataInput.setAttribute("autocapitalize","off");
                    newDataInput.type = 'email';
                    newDataInput.style.textTransform = 'lowercase';
                    confirmNewDataInput.type = 'email';
                    confirmNewDataInput.style.textTransform = 'lowercase';
                    break;
                case 'phone_number':
                    currentDataInput.type = 'number'; // 'tel' is better for phone numbers
                    newDataInput.type = 'number';
                    confirmNewDataInput.type = 'number';
                    break;
                case 'password':
                    currentDataInput.type = 'password';
                    newDataInput.type = 'password';
                    confirmNewDataInput.type = 'password';
                    break;
                default:
                    // Default type for other cases, if needed
                    currentDataInput.type = 'text';
                    newDataInput.type = 'text';
                    confirmNewDataInput.type = 'text';
                    break;
            }
        } else {
            // Hide the update fields if no option is selected
            updateFields.style.display = 'none';
        }
    });
    

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const currentData = currentDataInput.value.trim();
        const newData = newDataInput.value.trim();
        const confirmNewData = confirmNewDataInput.value.trim();
        const token = document.getElementById('token').value.trim();
        const secretAnswer = document.getElementById('secret_answer').value.trim();

        if (newData !== confirmNewData) {
            customerInfoMessage.textContent = 'New data and confirmation do not match.';
            customerInfoMessage.style.color = 'red';
            return;
        }

        let customerInformation = {
            updateOption : updateOption.value,
            currentData: currentData,
            newData : newData,
            confirmNewData : confirmNewData,
            token: token,
            secretAnswer: secretAnswer
        };

        const response = await fetch('../v2/update_customer.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(customerInformation)
        });

        const data = await response.json();
        customerInfoMessage.textContent = data.message;
        customerInfoMessage.style.color = data.success ? 'green' : 'red';

        if (data.success) {
            // Normalize the redirect URL
            let redirectUrl = data.redirect.replace(/\\\//g, '/'); // Ensure slashes are correctly formatted
    
            // Log the URL to confirm correctness
            console.log('Redirect URL:', redirectUrl);
    
            // Display success message
            alert(data.message);
            
            // Redirect after a short delay
            setTimeout(() => {
                window.location.href = redirectUrl;
            }, 2000); // 2 seconds delay
        }  else {
            console.error('Customer Information Update failed:', data.message);
            alert('Update failed: ' + data.message); // Alert failure message
        }
    });
});
