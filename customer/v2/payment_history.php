<?php
// fetch_transactions.php
include 'config.php';
session_start();
logActivity("🔹 Starting Session validation for User");

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

// Check if 'customer_id' is set in session
$customerId = $_SESSION["customer_id"] ?? null;
if (!$customerId) {
    logActivity("❌ Session validation failed: Customer ID not found");
    echo json_encode(["error" => "Unauthorized access. Please log in."]);
    exit;
}

checkSession($customerId);
logActivity("✅ Session validated for customer ID: $customerId");

// Extract filter parameters
$start_date = $data['start_date'] ?? null;
$end_date = $data['end_date'] ?? null;
$transaction_type = $data['type'] ?? null;
$payment_method = $data['payment_method'] ?? null;
$description = $data['description'] ?? null;
$page = isset($data['page']) ? (int)$data['page'] : 1;
$limit = 15;  // Records per page
$offset = ($page - 1) * $limit;  // Offset calculation

// Get today's date
$current_date = date("Y-m-d");

// Validate date inputs
if (!empty($start_date) && !empty($end_date)) {
    if ($start_date > $end_date) {
        logActivity("❌ Validation Error: End date ($end_date) is earlier than start date ($start_date)");
        echo json_encode(["error" => "End date cannot be earlier than the start date."]);
        exit;
    }
    if ($end_date > $current_date) {
        logActivity("❌ Validation Error: End date ($end_date) is in the future.");
        echo json_encode(["error" => "End date cannot be in the future."]);
        exit;
    }
    if ($start_date > $current_date) {
        logActivity("❌ Validation Error: Start date ($start_date) is in the future.");
        echo json_encode(["error" => "Start date cannot be in the future."]);
        exit;
    }
}

logActivity("✅ Date validation passed. Start Date: $start_date, End Date: $end_date");

// Build query dynamically using prepared statements
$sql = "SELECT * FROM customer_transactions WHERE customer_id = ?";
$params = [$customerId];
$types = "s"; // 's' for string (customer ID)

// Apply date range filter
if (!empty($start_date) && !empty($end_date)) {
    $sql .= " AND date_created BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss"; // Two strings for date range
}

// Apply transaction type filter
if (!empty($transaction_type) && $transaction_type !== "all") {
    $sql .= " AND transaction_type = ?";
    $params[] = $transaction_type;
    $types .= "s";
}

// Apply payment method filter
if (!empty($payment_method) && $payment_method !== "all") {
    $sql .= " AND payment_method = ?";
    $params[] = $payment_method;
    $types .= "s";
}

// Apply description filter (partial match using LIKE)
if (!empty($description)) {
    $sql .= " AND description LIKE ?";
    $params[] = "%" . $description . "%";
    $types .= "s";
}

// Count total records for pagination
$count_sql = str_replace("SELECT *", "SELECT COUNT(*) AS total", $sql);
$stmt_count = $conn->prepare($count_sql);
$stmt_count->bind_param($types, ...$params);
$stmt_count->execute();
$count_result = $stmt_count->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);
$stmt_count->close();

// Add pagination limits
$sql .= " LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii"; // Two integers for limit and offset

logActivity("🔹 Executing SQL Query: $sql");

// Prepare and execute query
$stmt = $conn->prepare($sql);
if (!$stmt) {
    logActivity("❌ SQL Preparation Error: " . $conn->error);
    echo json_encode(["error" => "Database error. Please try again later."]);
    exit;
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}

logActivity("✅ Query executed successfully. Retrieved " . count($transactions) . " transactions.");

// Return JSON response with pagination details
echo json_encode([
    "transactions" => $transactions,
    "total_pages" => $total_pages,
    "current_page" => $page
]);

// Close statement and connection
$stmt->close();
$conn->close();
logActivity("🔹 Database connection closed.");

