<?php
include 'config.php';
session_start();

// Log the start of the script
logActivity("Fetching promos script started.");

// Check if either 'unique_id' (for admin) or 'customer_id' (for customer) is set
if (!isset($_SESSION['unique_id']) && !isset($_SESSION['customer_id'])) {
    $error_message = "Not logged in. Session IDs not found.";
    error_log($error_message);
    logActivity($error_message);
    echo json_encode(["success" => false, "message" => $error_message]);
    exit();
}

// Log session IDs for debugging
if (isset($_SESSION['unique_id'])) {
    logActivity("Admin unique_id found in session: " . $_SESSION['unique_id']);
} elseif (isset($_SESSION['customer_id'])) {
    logActivity("Customer customer_id found in session: " . $_SESSION['customer_id']);
}

header('Content-Type: application/json');



// Get pagination parameters
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

// Log pagination parameters
logActivity("Pagination parameters - Page: $page, Limit: $limit, Offset: $offset");

try {
    // Fetch total count of promos
    $totalQuery = "SELECT COUNT(*) as total FROM promo WHERE delete_id = 0";
    $stmt = $conn->prepare($totalQuery);
    if (!$stmt) {
        logActivity("Failed to prepare total count query: " . $conn->error);
        throw new Exception("Failed to prepare total count query: " . $conn->error);
    }
    $stmt->execute();
    $totalResult = $stmt->get_result();
    $totalPromos = $totalResult->fetch_assoc()['total'];
    $stmt->close();

    // Log total promos count
    logActivity("Total promos count: " . $totalPromos);

    // Update expired promos: set status = 0 and delete_id = 1 where end_date has passed
    $now = date('Y-m-d H:i:s');
    $updateExpiredQuery = "UPDATE promo SET status = 0, delete_id = 1 WHERE end_date < ? AND delete_id = 0";
    $stmt = $conn->prepare($updateExpiredQuery);
    if (!$stmt) {
        logActivity("Failed to prepare expired promos update query: " . $conn->error);
        throw new Exception("Failed to prepare expired promos update query: " . $conn->error);
    }
    $stmt->bind_param("s", $now);
    $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    // Log number of expired promos updated
    logActivity("Updated $affectedRows expired promos (status = 0, delete_id = 1).");


    // Fetch paginated promos
    $query = "SELECT * FROM promo ORDER BY date_last_modified DESC, status DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        logActivity("Failed to prepare paginated promos query: " . $conn->error);
        throw new Exception("Failed to prepare paginated promos query: " . $conn->error);

    }
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $promos = [];
    while ($row = $result->fetch_assoc()) {
        $promos[] = $row;
    }
    $stmt->close();

    // Log the number of paginated promos fetched
    logActivity("Fetched " . count($promos) . " paginated promos.");

    // Fetch ongoing promos
    $now = date('Y-m-d H:i:s');
    $ongoingQuery = "SELECT promo_id, promo_name, promo_code, promo_description, discount_type, discount_value, max_discount,delete_id
                     FROM promo 
                     WHERE status = 1 AND delete_id = 0 AND start_date <= ? AND end_date >= ? 
                     ORDER BY date_last_modified DESC";
    $stmt = $conn->prepare($ongoingQuery);
    if (!$stmt) {
        throw new Exception("Failed to prepare ongoing promos query: " . $conn->error);
    }
    $stmt->bind_param("ss", $now, $now);
    $stmt->execute();
    $ongoingResult = $stmt->get_result();

    $ongoingPromos = [];
    while ($row = $ongoingResult->fetch_assoc()) {
        $ongoingPromos[] = $row;
    }
    $stmt->close();

    // Log the number of ongoing promos fetched
    logActivity("Fetched " . count($ongoingPromos) . " ongoing promos.");

    // Return both paginated and ongoing promos
    echo json_encode([
        "success" => true,
        "promos" => [
            "all" => $promos,
            "ongoing" => $ongoingPromos
        ],
        "total" => $totalPromos,
        "page" => $page,
        "limit" => $limit,
        "message" => "Promo Record has been successfully retrieved"
    ]);

    // Log the successful completion of the script
    logActivity("Fetching promos script completed successfully.");
} catch (Exception $e) {
    // Log the exception
    error_log("Exception: " . $e->getMessage());
    logActivity("Exception: " . $e->getMessage());

    // Return an error response
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
} finally {
    // Close the database connection
    $conn->close();
}

