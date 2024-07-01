<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Funds</title>
    <link rel="stylesheet" href="../css/add_funds.css">
</head>
<body>
    <?php include('../customerNavBar.php'); ?>
    <div class="add-funds-container">
        <h2>Add Funds to Wallet</h2>
        <form id="addFundsForm">
            <div class="form-input">
                <label for="amount">Enter Amount:</label>
                <input type="number" id="amount" name="amount" min="1" required>
            </div>
            <button type="submit">Add Funds</button>
        </form>
        <div id="message"></div>
    </div>

    <script>
        document.getElementById('addFundsForm').addEventListener('submit', function(event) {
            event.preventDefault();

            const amount = document.getElementById('amount').value;

            fetch('../v2/add_funds.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    'amount': amount
                })
            })
            .then(response => response.json())
            .then(data => {
                const message = document.getElementById('message');
                if (data.success) {
                    alert('Funds Added to Wallet Successfully.' + data.message);
                    location.reload(); // Refresh the page
                } else {
                    alert('Failed to add Funds to Wallet: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('message').textContent = 'An error occurred. Please try again.';
            });
        });
    </script>
</body>
</html>
