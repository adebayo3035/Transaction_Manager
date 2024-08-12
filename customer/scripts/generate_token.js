document.getElementById('generate-token').addEventListener('click', function() {
    fetch('../v2/generate_token.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('token').value = data.token;
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