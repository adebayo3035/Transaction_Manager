<?php
// Include database connection
include('config.php');

// Get the JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['revenueID'])) {
    echo json_encode(['success' => false, 'message' => 'Revenue Identification is missing.']);
    exit();
}

$revenue_id = $data['revenueID'];

// Fetch transaction details
$query = "SELECT  c.firstname, c.lastname, c.email, r.*, rt.revenue_type_name, rt.revenue_type_description, o.delivery_status
          FROM revenue r
          LEFT JOIN customers c ON r.customer_id = c.customer_id
          LEFT JOIN revenue_types rt ON r.revenue_type_id = rt.revenue_type_id 
          LEFT JOIN orders o ON r.order_id = o.order_id
          WHERE r.order_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $revenue_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode(['success' => true, 'revenue_details' => $row]);
} else {
    echo json_encode(['success' => false, 'message' => 'Revenue Details cannot be found.']);
}

