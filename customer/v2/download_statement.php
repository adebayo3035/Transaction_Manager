<?php
session_start();
require 'config.php'; // Ensure database connection
require_once '../../vendor/autoload.php'; // Adjust path if necessary
require_once('../../vendor/tecnickcom/tcpdf/tcpdf.php');
$customerId = $_SESSION['customer_id'];
checkSession($customerId);
logActivity("✅ Session validated for customer ID: $customerId");

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="statement.pdf"');
header('Content-Transfer-Encoding: binary');

// Handle only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    die(json_encode(["error" => "Invalid request method."]));
}

class CustomPDF extends TCPDF
{
    // Custom Header
    public function Header()
    {
        // Add the bank logo
        $this->Image('logo.png', 15, 10, 40); // Adjust logo position (X:15, Y:10, Width:40)

        // Move to the right and set font
        $this->SetFont('Helvetica', 'B', 14);
        $this->Cell(0, 10, 'CUSTOMER ACCOUNT STATEMENT', 0, 1, 'C');
        $this->Ln(10);
    }
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);

        // Set font
        $this->SetFont('Helvetica', 'I', 8);

        // Page number (Centered)
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// Read JSON input
$data = json_decode(file_get_contents("php://input"), true);
$startDate = $data['start_date'] ?? null;
$endDate = $data['end_date'] ?? null;
$format = $data['format'] ?? null;

if (!$startDate || !$endDate || !$format) {
    http_response_code(400); // Bad Request
    die(json_encode(["error" => "Start date, end date, and format are required."]));
}

// Validate date format
if (!strtotime($startDate) || !strtotime($endDate) || $startDate > $endDate) {
    http_response_code(400);
    die(json_encode(["error" => "Invalid date range."]));
}

//Query to return customer information to be included in the header
// SQL Query

// Prepare SQL query with placeholders
$sql = "SELECT 
            c.firstname, 
            c.lastname, 
            c.email, 
            c.address, 
            w.balance, 
            COALESCE(SUM(CASE WHEN t.transaction_type = 'debit' THEN t.amount END), 0) AS total_debit,
            COALESCE(SUM(CASE WHEN t.transaction_type = 'credit' THEN t.amount END), 0) AS total_credit,
            COALESCE(COUNT(CASE WHEN t.transaction_type = 'debit' THEN 1 END), 0) AS debit_count,
            COALESCE(COUNT(CASE WHEN t.transaction_type = 'credit' THEN 1 END), 0) AS credit_count
        FROM 
            customers c
        LEFT JOIN 
            wallets w ON c.customer_id = w.customer_id
        LEFT JOIN 
            customer_transactions t 
            ON c.customer_id = t.customer_id 
            AND t.date_created BETWEEN ? AND ?
        WHERE 
            c.customer_id = ?
        GROUP BY 
            c.customer_id, c.firstname, c.lastname, c.email, c.address, w.balance";

// Prepare the statement
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Query preparation failed: " . $conn->error);
}

// Bind parameters (string for dates, integer for customer ID)
$stmt->bind_param("ssi", $startDate, $endDate, $customerId);

// Execute query
$stmt->execute();

// Get result
$result = $stmt->get_result();
$customer = $result->fetch_assoc();

// Close resources


// Fetch transactions
$query = "SELECT * 
          FROM customer_transactions 
          WHERE date_created BETWEEN ? AND ? 
          ORDER BY date_created ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = [
        "date" => date("d/m/Y", strtotime($row['date_created'])), // Format the date
        "reference" => $row['transaction_ref'],
        "method" => $row['payment_method'],
        "description" => $row['description'],
        "debit" => ($row['transaction_type'] == 'debit') ? $row['amount'] : "",
        "credit" => ($row['transaction_type'] == 'credit') ? $row['amount'] : "",
    ];
}
$stmt->close();
$conn->close();
if ($format === 'csv') {
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="account_statement.csv"');
    
        $output = fopen('php://output', 'w');
    
        // Set UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
        // Headers
        fputcsv($output, ["Date", "Transaction Reference", "Payment Method", "Description", "Debit (N)", "Credit (N)"]);
    
        // Data
        foreach ($transactions as $transaction) {
            fputcsv($output, [
                $transaction['date'],
                $transaction['reference'],
                $transaction['method'],
                $transaction['description'],
               ($transaction['debit']), // Format numbers
                ($transaction['credit'])
            ]);
        }
    
        fclose($output);
        exit;
    }
    
} elseif ($format === 'pdf') {
    // Use TCPDF for PDF generation
    // Create new PDF document
    $pdf = new CustomPDF();
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 10);

    // Customer Details (Left Section)
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(30, 8, "Name:", 0, 0, 'L');
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(60, 8, $customer['firstname'] . " " . $customer['lastname'], 0, 1, 'L');

    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(30, 8, "Address:", 0, 0, 'L');
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(60, 8, $customer['address'], 0, 1, 'L');

    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(30, 8, "Period:", 0, 0, 'L');
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(60, 8, $startDate . " - " . $endDate, 0, 1, 'L');

    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(30, 8, "Print Date:", 0, 0, 'L');
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(60, 8, date("d-M-Y"), 0, 1, 'L');
    $pdf->Ln(5);

    // Right-Side Account Summary Table
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(50, 8, "Account Number", 1, 0, 'L', true);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(50, 8, $customerId, 1, 1, 'R');

    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(50, 8, "Currency", 1, 0, 'L', true);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(50, 8, "NGN", 1, 1, 'R');

    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(50, 8, "Current Balance", 1, 0, 'L', true);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(50, 8, number_format($customer['balance'], 2), 1, 1, 'R');

    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(50, 8, "Total Debit", 1, 0, 'L', true);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(50, 8, number_format($customer['total_debit'], 2), 1, 1, 'R');

    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(50, 8, "Total Credit", 1, 0, 'L', true);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(50, 8, number_format($customer['total_credit'], 2), 1, 1, 'R');

    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(50, 8, "Debit Count", 1, 0, 'L', true);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(50, 8, $customer['debit_count'], 1, 1, 'R');

    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(50, 8, "Credit Count", 1, 0, 'L', true);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(50, 8, $customer['credit_count'], 1, 1, 'R');

    $pdf->Ln(10);


    // Add the transaction table here as you already have


    $pdf->SetX(15); // Ensure it starts from the left margin
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(30, 8, "Date", 1);
    $pdf->Cell(70, 8, "Reference", 1);
    $pdf->Cell(40, 8, "Method", 1);
    $pdf->Cell(20, 8, "Debit (₦)", 1);
    $pdf->Cell(20, 8, "Credit (₦)", 1);
    $pdf->Ln(); // Move to the next row

    // Table Data
    $pdf->SetFont('Helvetica', '', 10);
    foreach ($transactions as $transaction) {
        $pdf->SetX(15); // Ensures the row aligns with the left margin
        $pdf->Cell(30, 8, $transaction['date'], 1);
        $pdf->Cell(70, 8, $transaction['reference'], 1);
        $pdf->Cell(40, 8, $transaction['method'], 1);
        $pdf->Cell(20, 8, $transaction['debit'], 1);
        $pdf->Cell(20, 8, $transaction['credit'], 1);
        $pdf->Ln();
    }

    // Output the PDF as inline (view in browser) or as download
    $pdf->Output("account_statement.pdf", "I");  // Use "I" for inline view, "D" for download
    exit;
} else {
    http_response_code(400);
    die(json_encode(["error" => "Invalid format. Choose 'pdf' or 'csv'."]));
}
