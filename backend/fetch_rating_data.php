<?php
header('Content-Type: application/json');
include 'config.php';

// Main data fetching function
function getDashboardData($conn) {
    $data = [
        // Summary statistics
        'totalRating' => getTotalRatings($conn),
        'topRating' => countRatingsInRange($conn, 'driver_rating', 4.5),
        'averageRating' => countRatingsInRange($conn, 'driver_rating', 3, 4.5),
        'lowRating' => countRatingsInRange($conn, 'driver_rating', 0, 3),
        
        // Rating metrics with order IDs
        'averageDriverRating' => getRatingWithOrderId($conn, 'driver_rating', 'avg'),
        'averageFoodRating' => getRatingWithOrderId($conn, 'food_rating', 'avg'),
        'averagePackageRating' => getRatingWithOrderId($conn, 'packaging_rating', 'avg'),
        'averagedeliveryTimeRating' => getRatingWithOrderId($conn, 'delivery_time_rating', 'avg'),
        
        'topDriverRating' => getRatingWithOrderId($conn, 'driver_rating', 'max'),
        'topFoodRating' => getRatingWithOrderId($conn, 'food_rating', 'max'),
        'topPackageRating' => getRatingWithOrderId($conn, 'packaging_rating', 'max'),
        'topDeliveryTimeRating' => getRatingWithOrderId($conn, 'delivery_time_rating', 'max'),
        
        'lowestDriverRating' => getRatingWithOrderId($conn, 'driver_rating', 'min'),
        'lowestFoodRating' => getRatingWithOrderId($conn, 'food_rating', 'min'),
        'lowestPackageRating' => getRatingWithOrderId($conn, 'packaging_rating', 'min'),
        'lowestDeliveryTimeRating' => getRatingWithOrderId($conn, 'delivery_time_rating', 'min'),
        
        'recentRatings' => getRecentRatings($conn)
    ];

    return $data;
}

// Helper functions
function getTotalRatings($conn) {
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM order_ratings");
    $row = mysqli_fetch_assoc($result);
    return $row['count'];
}

function getRatingWithOrderId($conn, $column, $type) {
    if ($type === 'avg') {
        $avg = calculateAverage($conn, $column);
        $query = "SELECT order_id, $column as value 
                  FROM order_ratings 
                  ORDER BY ABS($column - $avg) ASC 
                  LIMIT 1";
    } elseif ($type === 'max') {
        $query = "SELECT order_id, $column as value 
                  FROM order_ratings 
                  ORDER BY $column DESC 
                  LIMIT 1";
    } else {
        $query = "SELECT order_id, $column as value 
                  FROM order_ratings 
                  ORDER BY $column ASC 
                  LIMIT 1";
    }
    
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_assoc($result);
}

function calculateAverage($conn, $column) {
    $query = "SELECT AVG($column) as avg FROM order_ratings";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return round($row['avg'], 2);
}

function countRatingsInRange($conn, $column, $min, $max = null) {
    $query = "SELECT COUNT(*) as count FROM order_ratings WHERE $column >= $min";
    if ($max !== null) {
        $query .= " AND $column < $max";
    }
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['count'];
}

function getRecentRatings($conn, $limit = 10) {
    $ratings = [];
    $query = "SELECT * FROM order_ratings ORDER BY rated_at DESC LIMIT $limit";
    $result = mysqli_query($conn, $query);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $ratings[] = [
            'order_id' => $row['order_id'],
            'driver_id' => $row['driver_id'],
            'customer_id' => $row['customer_id'],
            'driver_rating' => $row['driver_rating'],
            'food_rating' => $row['food_rating'],
            'packaging_rating' => $row['packaging_rating'],
            'delivery_time_rating' => $row['delivery_time_rating'],
            'rated_at' => $row['rated_at'],
            'dailyAverageRating' => round((
                $row['driver_rating'] + 
                $row['food_rating'] + 
                $row['packaging_rating'] + 
                $row['delivery_time_rating']
            ) / 4, 2)
        ];
    }
    
    return $ratings;
}

// Execute and return data
try {
    $data = getDashboardData($conn);
    echo json_encode($data);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    mysqli_close($conn);
}
