<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="css/new_modal.css">
</head>

<body>
    <!-- Beautiful Modal Component -->
    <div id="appModal" class="app-modal">
        <div class="modal-overlay"></div>
        <div class="modal-container">
            <div class="modal-header">
                <div class="modal-icon"></div>
                <h3 class="modal-title">Modal Title</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <p class="modal-message">Your message here</p>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-btn-secondary">Cancel</button>
                <button class="modal-btn modal-btn-primary">Confirm</button>
            </div>
        </div>
    </div>
    <script src = "scripts/modal.js"></script>
</body>

</html>