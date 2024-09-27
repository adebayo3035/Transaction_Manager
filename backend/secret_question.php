<?php
// Database connection
include 'config.php'; // Replace with your actual database connection

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve the JSON body from the request
    $input = json_decode(file_get_contents('php://input'), true);

    // Retrieve the inputs (email/phone and password)
    $emailOrPhone = $input['email'] ?? null;
    $password = $input['password'] ?? null;

    // Validate input fields
    if (!$emailOrPhone || !$password) {
        echo json_encode(['success' => false, 'message' => 'Email/Phone and password are required']);
        exit();
    }

    try {
        // Prepare SQL query to check either email or phone
        $stmt = $conn->prepare("SELECT secret_question, password FROM admin_tbl WHERE (email = ? OR phone = ?) LIMIT 1");
        $stmt->bind_param('ss', $emailOrPhone, $emailOrPhone);
        $stmt->execute();
        $result = $stmt->get_result();

        // Check if user exists
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $hashedPassword = $row['password'];

            // Verify the password
            if (md5($password) == $hashedPassword) { // Adjust if you're using another hashing algorithm
                // If password matches, return the secret question
                echo json_encode([
                    'success' => true,
                    'secret_question' => $row['secret_question']
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid password. Please Try Again'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'User Authentication Failed'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching secret question: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}

// Close database connection
$conn->close();

