<?php
include 'config.php';
session_start();
header('Content-Type: application/json');

// Debugging: Log the received data
error_log("Received data: " . file_get_contents('php://input'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Decode and validate input
        $data = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON input');
        }

        // Validate required fields
        $requiredFields = ['order_items', 'total_order', 'service_fee', 'delivery_fee', 'pack_count'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        // Validate order items structure
        if (!is_array($data['order_items']) || empty($data['order_items'])) {
            throw new Exception('Invalid order items data');
        }

        // Organize items by pack
        $packs = [];
        foreach ($data['order_items'] as $item) {
            if (!isset($item['pack_id'])) {
                throw new Exception('Missing pack_id in order item');
            }
            $packId = $item['pack_id'];
            if (!isset($packs[$packId])) {
                $packs[$packId] = [];
            }
            $packs[$packId][] = $item;
        }

        // Verify pack count matches
        $actualPackCount = count($packs);
        if ($actualPackCount > $data['pack_count']) {
            throw new Exception("More packs ($actualPackCount) than declared ({$data['pack_count']})");
        }

        // Clear existing order session data
        unset($_SESSION['order']);

        // Store organized order data
        $_SESSION['order'] = [
            'items' => $data['order_items'],
            'packs' => $packs,
            'totals' => [
                'subtotal' => $data['total_order'],
                'service_fee' => $data['service_fee'],
                'delivery_fee' => $data['delivery_fee'],
                'grand_total' => $data['total_order'] + $data['service_fee'] + $data['delivery_fee']
            ],
            'pack_count' => $data['pack_count'],
            'created_at' => date('Y-m-d H:i:s')
        ];

        // Debugging: Log successful save
        error_log("Order saved successfully. Pack count: {$data['pack_count']}, Item count: " . count($data['order_items']));

        echo json_encode([
            'success' => true,
            'pack_count' => $data['pack_count'],
            'item_count' => count($data['order_items'])
        ]);

    } catch (Exception $e) {
        error_log("Error saving order: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. Expected POST.'
    ]);
}