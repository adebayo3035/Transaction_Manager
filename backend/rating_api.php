<?php
header('Content-Type: application/json');
require 'config.php';
session_start();

// Verify admin role
if (!isset($_SESSION['unique_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_ratings':
            // Get ratings with filters
            $page = $_GET['page'] ?? 1;
            $limit = 10;
            $offset = ($page - 1) * $limit;
            
            // Build filters
            $filters = [];
            $params = [];
            $types = '';
            
            if (isset($_GET['driver_id'])) {
                $filters[] = "r.driver_id = ?";
                $params[] = $_GET['driver_id'];
                $types .= 'i';
            }
            
            if (isset($_GET['date_from'])) {
                $filters[] = "r.rated_at >= ?";
                $params[] = $_GET['date_from'];
                $types .= 's';
            }
            
            $filterQuery = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';
            
            // Get ratings
            $query = "SELECT r.*, 
                      d.firstname as driver_firstname, d.lastname as driver_lastname,
                      o.order_id, o.customer_id
                      FROM order_ratings r
                      JOIN driver d ON r.driver_id = d.id
                      JOIN orders o ON r.order_id = o.order_id
                      $filterQuery
                      ORDER BY r.rated_at DESC
                      LIMIT ? OFFSET ?";
            
            $stmt = $conn->prepare($query);
            $params[] = $limit;
            $params[] = $offset;
            $types .= 'ii';
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $ratings = [];
            while ($row = $result->fetch_assoc()) {
                $ratings[] = $row;
            }
            
            // Get total count for pagination
            $countQuery = "SELECT COUNT(*) as total FROM order_ratings r $filterQuery";
            $countStmt = $conn->prepare($countQuery);
            $slice_obj = array_slice($params, 0, -2);
            if ($filters) {
                $countStmt->bind_param($types, ...$slice_obj);
            }
            $countStmt->execute();
            $total = $countStmt->get_result()->fetch_assoc()['total'];
            
            echo json_encode([
                'success' => true,
                'data' => $ratings,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        case 'get_analytics':
            // Get rating analytics
            $timeframe = $_GET['timeframe'] ?? 'week'; // week, month, year
            
            $analytics = [
                'average_ratings' => [],
                'driver_performance' => [],
                'rating_distribution' => []
            ];
            
            // 1. Average ratings over time
            $avgQuery = "SELECT 
                DATE_FORMAT(rated_at, '%Y-%m-%d') as date,
                AVG(food_rating) as avg_rating,
                COUNT(*) as rating_count
                FROM order_ratings
                WHERE rated_at >= DATE_SUB(NOW(), INTERVAL 1 $timeframe)
                GROUP BY date
                ORDER BY date";
                
            $result = $conn->query($avgQuery);
            while ($row = $result->fetch_assoc()) {
                $analytics['average_ratings'][] = $row;
            }
            
            // 2. Top/Bottom driver
            $driverQuery = "SELECT 
                d.id, d.firstname, d.lastname,
                AVG(r.driver_rating) as avg_rating,
                COUNT(r.rating_id) as rating_count
                FROM driver d
                LEFT JOIN order_ratings r ON d.id = r.driver_id
                GROUP BY d.id
                ORDER BY avg_rating DESC
                LIMIT 10";
                
            $result = $conn->query($driverQuery);
            while ($row = $result->fetch_assoc()) {
                $analytics['driver_performance'][] = $row;
            }
            
            // 3. Rating distribution (1-5 stars)
            $distQuery = "SELECT 
                driver_rating,
                COUNT(*) as count
                FROM order_ratings
                GROUP BY food_rating
                ORDER BY packaging_rating";
                
            $result = $conn->query($distQuery);
            while ($row = $result->fetch_assoc()) {
                $analytics['rating_distribution'][] = $row;
            }
            
            echo json_encode(['success' => true, 'data' => $analytics]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}