<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KaraKata Staffs Deactivation and Reactivation History</title>
    <link rel="stylesheet" href="customer/css/view_orders.css">
    <link rel="stylesheet" href="customer/css/checkout.css">
    <link rel="stylesheet" href="customer/css/cards.css">
    <link rel="stylesheet" href="css/view_driver.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            outline: 0;
            background-color: rgba(0, 0, 0, 0.5);
           
        }

        .modal-dialog {
            max-width: 500px;
            margin: 7rem auto;
            
        }

        .modal-content {
            position: relative;
            display: flex;
            flex-direction: column;
            width: 100%;
            pointer-events: auto;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid rgba(0, 0, 0, .2);
            border-radius: .3rem;
            outline: 0;
        }
        .modal-body{
            font-size: 12px;
        }
    </style>
</head>

<body>
    <?php include('navbar.php'); ?>
    <div class="container">
        <h1>Staff Deactivation/Reactivation History</h1>
        <div class="livesearch">
            <input type="text" id="liveSearch" placeholder="Search for Order...">
            <button type="submit">Search <i class="fa fa-search" aria-hidden="true"></i></button>
        </div>


    </div>
    <div class="spinner" id="spinner">
        <div class="rect1"></div>
        <div class="rect2"></div>
        <div class="rect3"></div>
        <div class="rect4"></div>
        <div class="rect5"></div>
    </div>

    <div class="table-container">
        <table id="ordersTable" class="orders-table">
            <thead>
                <tr>
                    <th>Deactivation ID</th>
                    <th>Staff ID</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>E-mail Address</th>
                    <th>Deactivated By</th>
                    <th>Date Deactivated</th>
                    <th>Reactivation Status</th>
                    <th>Reactivated By</th>
                    <th>Date Last Updated</th>

                </tr>
            </thead>
            <tbody>
                <!-- Staff Information will be dynamically inserted here -->
            </tbody>
        </table>
    </div>
    <!-- Custom Error Modal -->
    <div id="errorModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Error</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="errorMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="retryButton">Retry</button>
                </div>
            </div>
        </div>
    </div>
    <div id="pagination" class="pagination"></div>

    <script src="scripts/staff_deactivation_reactivation_history.js"></script>

</body>

</html>