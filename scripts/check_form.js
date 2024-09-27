// Function to check if all inputs in a form are filled
function checkFormFields(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    
    // Get all required input fields in the form
    const inputs = form.querySelectorAll('input[required]');
    
    // Check if all required fields have a value
    let allFilled = true;
    inputs.forEach(input => {
        if (!input.value.trim()) {
            allFilled = false;
        }
    });
    
    // Show or hide the submit button based on the filled status
    if (allFilled) {
        submitBtn.style.display = 'block'; // Show the submit button
    } else {
        submitBtn.style.display = 'none';  // Hide the submit button
    }
}