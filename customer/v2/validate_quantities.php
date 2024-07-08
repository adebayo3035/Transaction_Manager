<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['order_items'])) {
        echo json_encode(["success" => false, "message" => "No order items found."]);
        exit();
    }

    $orderItems = $input['order_items'];

    if (!is_array($orderItems)) {
        echo json_encode(["success" => false, "message" => "Invalid order items format."]);
        exit();
    }

    foreach ($orderItems as $item) {
        $food_id = $item['food_id'];
        $quantity = $item['quantity'];

        $query = "SELECT available_quantity, food_name FROM food WHERE food_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $food_id);
        $stmt->execute();
        $stmt->bind_result($available_quantity, $food_name);
        $stmt->fetch();
        $stmt->close();
        $available_food = round($available_quantity/4);
        if ($quantity > $available_food) {
            echo json_encode(["success" => false, "message" => "The requested quantity for $food_name is not available. Available quantity is : $available_food"]);
            exit();
        }
    }

    echo json_encode(["success" => true, "message" => "Quantities are valid."]);
} else {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}

