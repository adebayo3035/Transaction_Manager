<?php
include 'config.php';
session_start();

// Log the start of the script
logActivity("Fetching promos script started.");

// Check if either 'unique_id' (for admin) or 'customer_id' (for customer) is set
if (!isset($_SESSION['unique_id']) && !isset($_SESSION['customer_id'])) {
    $error_message = "Not logged in. Session IDs not found.";
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

// Validate pagination
if ($page < 1 || $limit < 1 || $limit > 100) {
    logActivity("Invalid pagination parameters - Page: $page, Limit: $limit");
    echo json_encode(["success" => false, "message" => "Invalid pagination parameters."]);
    exit();
}

// Get filters and validate
$statusFilter = isset($_GET['status']) ? intval($_GET['status']) : null;
$deleteIdFilter = isset($_GET['delete_id']) ? intval($_GET['delete_id']) : null;

$validStatus = [0, 1];
$validDeleteId = [0, 1];

if ($statusFilter !== null && !in_array($statusFilter, $validStatus)) {
    logActivity("Invalid status filter: $statusFilter");
    echo json_encode(["success" => false, "message" => "Invalid status filter."]);
    exit();
}

if ($deleteIdFilter !== null && !in_array($deleteIdFilter, $validDeleteId)) {
    logActivity("Invalid delete_id filter: $deleteIdFilter");
    echo json_encode(["success" => false, "message" => "Invalid delete_id filter."]);
    exit();
}

logActivity("Pagination parameters - Page: $page, Limit: $limit, Offset: $offset");
logActivity("Applied filters - Status: " . ($statusFilter ?? 'All') . ", Delete ID: " . ($deleteIdFilter ?? 'All'));

try {
    // Fetch total count of promos with filters
    $totalQuery = "SELECT COUNT(*) as total FROM promo WHERE 1=1";
    $params = [];
    $types = '';

    if ($statusFilter !== null) {
        $totalQuery .= " AND status = ?";
        $params[] = $statusFilter;
        $types .= 'i';
    }

    if ($deleteIdFilter !== null) {
        $totalQuery .= " AND delete_id = ?";
        $params[] = $deleteIdFilter;
        $types .= 'i';
    }

    $stmt = $conn->prepare($totalQuery);
    if (!$stmt) throw new Exception("Failed to prepare total count query: " . $conn->error);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $totalResult = $stmt->get_result();
    $totalPromos = $totalResult->fetch_assoc()['total'];
    $stmt->close();

    logActivity("Total promos count after filters: " . $totalPromos);

    // Update expired promos
    $now = date('Y-m-d H:i:s');
    $updateExpiredQuery = "UPDATE promo SET status = 0, delete_id = 1 WHERE end_date < ? AND delete_id = 0";
    $stmt = $conn->prepare($updateExpiredQuery);
    if (!$stmt) throw new Exception("Failed to prepare expired promos update query: " . $conn->error);

    $stmt->bind_param("s", $now);
    $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    logActivity("Updated $affectedRows expired promos (status = 0, delete_id = 1).");

    // Fetch paginated promos with filters
    $query = "SELECT * FROM promo WHERE 1=1";
    $params = [];
    $types = '';

    if ($statusFilter !== null) {
        $query .= " AND status = ?";
        $params[] = $statusFilter;
        $types .= 'i';
    }

    if ($deleteIdFilter !== null) {
        $query .= " AND delete_id = ?";
        $params[] = $deleteIdFilter;
        $types .= 'i';
    }

    $query .= " ORDER BY date_last_modified DESC, status DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $stmt = $conn->prepare($query);
    if (!$stmt) throw new Exception("Failed to prepare paginated promos query: " . $conn->error);

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $promos = [];
    while ($row = $result->fetch_assoc()) {
        $promos[] = $row;
    }
    $stmt->close();

    logActivity("Fetched " . count($promos) . " paginated promos.");

    // Fetch ongoing promos (status = 1 and delete_id = 0)
    $ongoingQuery = "SELECT promo_id, promo_name, promo_code, promo_description, discount_type, discount_value, max_discount, delete_id
                     FROM promo 
                     WHERE status = 1 AND delete_id = 0 AND start_date <= ? AND end_date >= ? 
                     ORDER BY date_last_modified DESC";
    $stmt = $conn->prepare($ongoingQuery);
    if (!$stmt) throw new Exception("Failed to prepare ongoing promos query: " . $conn->error);
    $stmt->bind_param("ss", $now, $now);
    $stmt->execute();
    $ongoingResult = $stmt->get_result();

    $ongoingPromos = [];
    while ($row = $ongoingResult->fetch_assoc()) {
        $ongoingPromos[] = $row;
    }
    $stmt->close();

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
        "message" => "Promo records successfully retrieved"
    ]);

    logActivity("Fetching promos script completed successfully.");
} catch (Exception $e) {
    logActivity("Exception: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
} finally {
    $conn->close();
}
