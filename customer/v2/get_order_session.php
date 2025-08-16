<?php
// get_order_session.php
session_start();
header('Content-Type: application/json');

// Check if session data exists using the new structure
if (!isset($_SESSION['order'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'No order data found in session.'
    ]);
    exit;
}

// Retrieve and organize session data
$orderData = [
    'order_items' => $_SESSION['order']['items'] ?? [],
    'packs' => $_SESSION['order']['packs'] ?? [],
    'totals' => [
        'subtotal' => $_SESSION['order']['totals']['subtotal'] ?? 0,
        'service_fee' => $_SESSION['order']['totals']['service_fee'] ?? 0,
        'delivery_fee' => $_SESSION['order']['totals']['delivery_fee'] ?? 0,
        'grand_total' => $_SESSION['order']['totals']['grand_total'] ?? 0
    ],
    'pack_count' => $_SESSION['order']['pack_count'] ?? 0,
    'created_at' => $_SESSION['order']['created_at'] ?? null
];

// Backward compatibility - include flattened data too
$legacyData = [
    'order_items' => $_SESSION['order']['items'] ?? [],
    'total_order' => $_SESSION['order']['totals']['subtotal'] ?? 0,
    'service_fee' => $_SESSION['order']['totals']['service_fee'] ?? 0,
    'delivery_fee' => $_SESSION['order']['totals']['delivery_fee'] ?? 0
];

echo json_encode([
    'success' => true,
    'data' => $orderData,
    // Include legacy structure for backward compatibility
    'legacy_data' => $legacyData
]);