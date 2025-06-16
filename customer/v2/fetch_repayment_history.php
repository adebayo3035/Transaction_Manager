<?php
include 'config.php';
session_start();

logActivity("Starting repayment history fetch request");

// Check session and permissions
$customerId = $_SESSION["customer_id"] ?? null;
checkSession($customerId);

// Get input parameters
$data = json_decode(file_get_contents('php://input'), true);
$creditId = isset($data['credit_id']) ? $conn->real_escape_string($data['credit_id']) : null;
$page = isset($data['page']) ? max(1, (int)$data['page']) : 1;
$limit = 5; // Fixed at 5 records per page
$offset = ($page - 1) * $limit;

if (!$creditId) {
    logActivity("Failed to fetch repayment history: Credit ID not provided");
    echo json_encode([
        'success' => false,
        'message' => 'Credit ID is required'
    ]);
    exit();
}

logActivity("Fetching repayment history for credit ID: $creditId (Page: $page, Limit: $limit)");

// 1. First get total count of records
$countQuery = "SELECT COUNT(*) as total FROM repayment_history WHERE credit_order_id = ?";
logActivity("Preparing count query: $countQuery");

$stmt = $conn->prepare($countQuery);
if (!$stmt) {
    logActivity("Count query preparation failed: " . $conn->error);
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
    exit();
}

$stmt->bind_param("s", $creditId);
$stmt->execute();
$countResult = $stmt->get_result();
$totalRecords = $countResult->fetch_assoc()['total'];
$stmt->close();

logActivity("Total repayment records found: $totalRecords");

// 2. Fetch paginated records
$query = "SELECT 
            repayment_id,
            payment_date,
            amount_paid
          FROM repayment_history
          WHERE credit_order_id = ?
          ORDER BY payment_date DESC
          LIMIT ? OFFSET ?";

logActivity("Preparing paginated query: $query");
$stmt = $conn->prepare($query);
if (!$stmt) {
    logActivity("Query preparation failed: " . $conn->error);
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
    exit();
}

$stmt->bind_param("iii", $creditId, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}
$stmt->close();

// Calculate pagination info
$totalPages = ceil($totalRecords / $limit);
$hasNextPage = ($page < $totalPages);
$hasPreviousPage = ($page > 1);

logActivity("Fetched " . count($history) . " records for page $page/$totalPages");

$response = [
    'success' => true,
    'history' => $history,
    'pagination' => [
        'current_page' => $page,
        'per_page' => $limit,
        'total_records' => $totalRecords,
        'total_pages' => $totalPages,
        'has_next_page' => $hasNextPage,
        'has_previous_page' => $hasPreviousPage
    ]
];

if (empty($history)) {
    $response['message'] = 'No repayment history found for this page';
    logActivity("No repayment history found for credit ID: $creditId on page $page");
} else {
    logActivity("Successfully fetched repayment history for credit ID: $creditId");
}

echo json_encode($response);
