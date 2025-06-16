<?php
session_start();
require 'config.php'; // Ensure database connection
require_once '../../vendor/autoload.php'; // Adjust path if necessary
require_once('../../vendor/tecnickcom/tcpdf/tcpdf.php');

// Initialize logging
$customerId = $_SESSION['customer_id'] ?? null;
checkSession($customerId);
logActivity("✅ Session validated for customer ID: $customerId | Starting statement generation");

// header('Content-Type: application/pdf');
// header('Content-Disposition: inline; filename="statement.pdf"');
// header('Content-Transfer-Encoding: binary');

// Handle only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $errorMsg = "Invalid request method. Expected POST, got " . $_SERVER['REQUEST_METHOD'];
    logActivity("❌ $errorMsg");
    http_response_code(405);
    die(json_encode(["error" => $errorMsg]));
}

class CustomPDF extends TCPDF
{
    public function Header()
    {
        logActivity("📄 Generating PDF header");
        try {
            $this->Image('logo.png', 15, 10, 40);
            $this->SetFont('Helvetica', 'B', 14);
            $this->Cell(0, 10, 'CUSTOMER ACCOUNT STATEMENT', 0, 1, 'C');
            $this->Ln(10);
        } catch (Exception $e) {
            logActivity("❌ PDF Header Error: " . $e->getMessage());
        }
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// Read JSON input
$input = file_get_contents("php://input");
logActivity("📥 Received input data: " . substr($input, 0, 200) . (strlen($input) > 200 ? "..." : ""));

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $errorMsg = "Invalid JSON input: " . json_last_error_msg();
    logActivity("❌ $errorMsg");
    http_response_code(400);
    die(json_encode(["error" => $errorMsg]));
}

$startDate = $data['start_date'] ?? null;
$endDate = $data['end_date'] ?? null;
$format = $data['format'] ?? null;

// Validate required parameters
if (!$startDate || !$endDate || !$format) {
    $errorMsg = "Missing parameters. Required: start_date, end_date, format";
    logActivity("❌ $errorMsg");
    http_response_code(400);
    die(json_encode(["error" => $errorMsg]));
}

// Validate date format
if (!strtotime($startDate) || !strtotime($endDate)) {
    $errorMsg = "Invalid date format: start_date=$startDate, end_date=$endDate";
    logActivity("❌ $errorMsg");
    http_response_code(400);
    die(json_encode(["error" => "Invalid date format."]));
}

if ($startDate > $endDate) {
    $errorMsg = "Invalid date range: start_date > end_date ($startDate > $endDate)";
    logActivity("❌ $errorMsg");
    http_response_code(400);
    die(json_encode(["error" => "Start date cannot be after end date."]));
}

logActivity("📅 Processing statement for period: $startDate to $endDate in $format format");

// Query customer information
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

logActivity("🔍 Executing customer query: " . str_replace(["\n", "\r", "\t"], " ", $sql));

$stmt = $conn->prepare($sql);
if (!$stmt) {
    $errorMsg = "Query preparation failed: " . $conn->error;
    logActivity("❌ $errorMsg");
    die($errorMsg);
}

$end_date = date('Y-m-d 23:59:59', strtotime($endDate));
$stmt->bind_param("ssi", $startDate, $end_date, $customerId);

if (!$stmt->execute()) {
    $errorMsg = "Query execution failed: " . $stmt->error;
    logActivity("❌ $errorMsg");
    die($errorMsg);
}

$result = $stmt->get_result();
$customer = $result->fetch_assoc();
$stmt->close();

if (!$customer) {
    $errorMsg = "No customer found with ID: $customerId";
    logActivity("❌ $errorMsg");
    http_response_code(404);
    die(json_encode(["error" => $errorMsg]));
}

logActivity("👤 Retrieved customer data: " . json_encode([
    'name' => $customer['firstname'] . ' ' . $customer['lastname'],
    'balance' => $customer['balance']
]));

// Fetch transactions
$query = "SELECT * FROM customer_transactions 
          WHERE customer_id = ? AND date_created BETWEEN ? AND ?
          ORDER BY date_created ASC";

logActivity("🔍 Executing transactions query: $query");

$stmt = $conn->prepare($query);
if (!$stmt) {
    $errorMsg = "Transaction query preparation failed: " . $conn->error;
    logActivity("❌ $errorMsg");
    die($errorMsg);
}

$stmt->bind_param("iss", $customerId, $startDate, $end_date);

if (!$stmt->execute()) {
    $errorMsg = "Transaction query execution failed: " . $stmt->error;
    logActivity("❌ $errorMsg");
    die($errorMsg);
}

$result = $stmt->get_result();
$transactions = [];
$transactionCount = 0;

while ($row = $result->fetch_assoc()) {
    $reference = $row['transaction_ref'];
    $truncatedReference = (strlen($reference) > 14) ? substr($reference, 0, 10) . "..." : $reference;

    $transactions[] = [
        "date" => date("d/m/Y", strtotime($row['date_created'])),
        "reference" => $truncatedReference,
        "method" => $row['payment_method'],
        "description" => $row['description'],
        "debit" => ($row['transaction_type'] == 'debit') ? $row['amount'] : "",
        "credit" => ($row['transaction_type'] == 'credit') ? $row['amount'] : "",
    ];
    $transactionCount++;
}

$stmt->close();
$conn->close();

logActivity("💳 Retrieved $transactionCount transactions for the period");

if ($format === 'csv') {
    logActivity("📊 Generating CSV output");
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="account_statement.csv"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Headers
    fputcsv($output, ["Date", "Transaction Reference", "Payment Method", "Description", "Debit (N)", "Credit (N)"]);

    // Data
    foreach ($transactions as $transaction) {
        fputcsv($output, [
            $transaction['date'],
            $transaction['reference'],
            $transaction['method'],
            $transaction['description'],
            $transaction['debit'],
            $transaction['credit'],
        ]);
    }

    fclose($output);
    logActivity("✅ CSV generation completed successfully");
    exit;

} elseif ($format === 'pdf') {
    logActivity("📄 Starting PDF generation");
    try {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="account_statement.pdf"');
        header('Content-Transfer-Encoding: binary');
        $pdf = new CustomPDF();
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 10);

        // Customer Details
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(30, 8, "Name:", 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(60, 8, $customer['firstname'] . " " . $customer['lastname'], 0, 1, 'L');

        // ... [rest of your PDF generation code]
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


        $pdf->SetX(7); // Ensure it starts from the left margin
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell(20, 8, "Date", 1);
        $pdf->Cell(24, 8, "Reference", 1);
        $pdf->Cell(30, 8, "Method", 1);
        $pdf->Cell(17, 8, "Debit (₦)", 1);
        $pdf->Cell(17, 8, "Credit (₦)", 1);
        $pdf->Cell(85, 8, "Description", 1);
        $pdf->Ln(); // Move to the next row

        // Table Data
        $pdf->SetFont('Helvetica', '', 9);
        foreach ($transactions as $transaction) {
            $pdf->SetX(7); // Ensures the row aligns with the left margin
            $pdf->Cell(20, 8, $transaction['date'], 1);
            $pdf->Cell(24, 8, $transaction['reference'], 1);
            $pdf->Cell(30, 8, $transaction['method'], 1);
            $pdf->Cell(17, 8, $transaction['debit'], 1);
            $pdf->Cell(17, 8, $transaction['credit'], 1);
            $pdf->Cell(85, 8, $transaction['description'], 1);
            $pdf->Ln();
        }

        // Output the PDF as inline (view in browser) or as download
        http_response_code(200);
        // (json_encode(["message" => "Your Account Statement has been successfully prepared'."]));

        $pdf->Output("account_statement.pdf", "I");
        logActivity("✅ PDF generated successfully");
        exit;
    } catch (Exception $e) {
        $errorMsg = "PDF generation failed: " . $e->getMessage();
        logActivity("❌ $errorMsg");
        http_response_code(500);
        die(json_encode(["error" => $errorMsg]));
    }
} else {
    $errorMsg = "Invalid format requested: $format";
    logActivity("❌ $errorMsg");
    http_response_code(400);
    die(json_encode(["error" => "Invalid format. Choose 'pdf' or 'csv'."]));
}

