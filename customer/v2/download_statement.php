<?php
session_start();
require 'config.php';
require_once '../../vendor/autoload.php';

// Initialize logging
$customerId = $_SESSION['customer_id'] ?? null;
checkSession($customerId);
logActivity("Starting statement generation for customer ID: $customerId");

// Clear any previous output
if (ob_get_level() > 0) {
    ob_end_clean();
}

// Handle only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $errorMsg = "Invalid request method. Expected POST, got " . $_SERVER['REQUEST_METHOD'];
    logActivity("❌ $errorMsg");
    http_response_code(405);
    die(json_encode(["error" => $errorMsg]));
}

// Read and validate JSON input
$input = file_get_contents("php://input");
logActivity("Received input data: " . substr($input, 0, 200));

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $errorMsg = "Invalid JSON input: " . json_last_error_msg();
    logActivity("❌ $errorMsg");
    http_response_code(400);
    die(json_encode(["error" => $errorMsg]));
}

// Validate required parameters
$startDate = $data['start_date'] ?? null;
$endDate = $data['end_date'] ?? null;
$format = $data['format'] ?? null;

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

logActivity("Processing statement for period: $startDate to $endDate in $format format");

// Database operations
try {
    $conn->begin_transaction();

    // Query customer information
    $sql = "SELECT 
                c.firstname, c.lastname, c.email, c.address, w.balance,
                COALESCE(SUM(CASE WHEN t.transaction_type = 'debit' THEN t.amount END), 0) AS total_debit,
                COALESCE(SUM(CASE WHEN t.transaction_type = 'credit' THEN t.amount END), 0) AS total_credit,
                COALESCE(COUNT(CASE WHEN t.transaction_type = 'debit' THEN 1 END), 0) AS debit_count,
                COALESCE(COUNT(CASE WHEN t.transaction_type = 'credit' THEN 1 END), 0) AS credit_count
            FROM customers c
            LEFT JOIN wallets w ON c.customer_id = w.customer_id
            LEFT JOIN customer_transactions t ON c.customer_id = t.customer_id 
                AND t.date_created BETWEEN ? AND ?
            WHERE c.customer_id = ?
            GROUP BY c.customer_id, c.firstname, c.lastname, c.email, c.address, w.balance";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . $conn->error);
    }

    $end_date = date('Y-m-d 23:59:59', strtotime($endDate));
    $stmt->bind_param("ssi", $startDate, $end_date, $customerId);

    if (!$stmt->execute()) {
        throw new Exception("Query execution failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();
    $stmt->close();

    if (!$customer) {
        throw new Exception("No customer found with ID: $customerId");
    }

    // Fetch transactions
    $query = "SELECT * FROM customer_transactions 
              WHERE customer_id = ? AND date_created BETWEEN ? AND ?
              ORDER BY date_created ASC";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Transaction query preparation failed: " . $conn->error);
    }

    $stmt->bind_param("iss", $customerId, $startDate, $end_date);

    if (!$stmt->execute()) {
        throw new Exception("Transaction query execution failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = [
            "date" => date("d/m/Y", strtotime($row['date_created'])),
            "reference" => strlen($row['transaction_ref']) > 14 ? substr($row['transaction_ref'], 0, 10) . "..." : $row['transaction_ref'],
            "method" => $row['payment_method'],
            "description" => $row['description'],
            "debit" => $row['transaction_type'] == 'debit' ? $row['amount'] : "",
            "credit" => $row['transaction_type'] == 'credit' ? $row['amount'] : "",
        ];
    }
    $stmt->close();
    $conn->commit();

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    logActivity("❌ Database error: " . $e->getMessage());
    http_response_code(500);
    die(json_encode(["error" => $e->getMessage()]));
}

// Generate output based on format
if ($format === 'csv') {
    try {
        // Clear any previous output
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="account_statement_' . date('Ymd_His') . '.csv"');

        $output = fopen('php://output', 'w');
        // Add BOM for UTF-8
        fwrite($output, "\xEF\xBB\xBF");

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
                $transaction['credit']
            ]);
        }

        fclose($output);
        logActivity("✅ CSV generated successfully");
        exit;

    } catch (Exception $e) {
        logActivity("❌ CSV generation failed: " . $e->getMessage());
        http_response_code(500);
        die(json_encode(["error" => "Failed to generate CSV"]));
    }

} elseif ($format === 'pdf') {
    try {
        // Clear any previous output
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        require_once('../../vendor/tecnickcom/tcpdf/tcpdf.php');

        class CustomPDF extends TCPDF
        {
            public function Header()
            {
                try {
                    $image_file = 'logo.png';
                    if (file_exists($image_file)) {
                        $this->Image($image_file, 15, 10, 40);
                    }
                    $this->SetFont('helvetica', 'B', 14);
                    $this->Cell(0, 10, 'CUSTOMER ACCOUNT STATEMENT', 0, 1, 'C');
                    $this->Ln(10);
                } catch (Exception $e) {
                    logActivity("PDF Header Error: " . $e->getMessage());
                }
            }

            public function Footer()
            {
                $this->SetY(-15);
                $this->SetFont('helvetica', 'I', 8);
                $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
            }
        }

        $pdf = new CustomPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Your Company');
        $pdf->SetTitle('Account Statement');
        $pdf->SetSubject('Customer Account Statement');

        // Set margins
        $pdf->SetMargins(15, 25, 15);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(TRUE, 25);

        // Add a page
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        // Customer Details
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(30, 8, "Name:", 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(60, 8, $customer['firstname'] . " " . $customer['lastname'], 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(30, 8, "Address:", 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(60, 8, $customer['address'], 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(30, 8, "Period:", 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(60, 8, $startDate . " - " . $endDate, 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(30, 8, "Print Date:", 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(60, 8, date("d-M-Y"), 0, 1, 'L');
        $pdf->Ln(5);

        // Account Summary Table
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(50, 8, "Account Number", 1, 0, 'L', true);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(50, 8, $customerId, 1, 1, 'R');

        // ... [rest of your summary table rows]

        // Transactions Table
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(20, 8, "Date", 1);
        $pdf->Cell(24, 8, "Reference", 1);
        $pdf->Cell(30, 8, "Method", 1);
        $pdf->Cell(17, 8, "Debit (₦)", 1);
        $pdf->Cell(17, 8, "Credit (₦)", 1);
        $pdf->Cell(85, 8, "Description", 1);
        $pdf->Ln();

        $pdf->SetFont('helvetica', '', 9);
        foreach ($transactions as $transaction) {
            $pdf->MultiCell(20, 8, $transaction['date'], 1, 'L', false, 0);
            $pdf->MultiCell(24, 8, $transaction['reference'], 1, 'L', false, 0);
            $pdf->MultiCell(30, 8, $transaction['method'], 1, 'L', false, 0);
            $pdf->MultiCell(17, 8, $transaction['debit'], 1, 'R', false, 0);
            $pdf->MultiCell(17, 8, $transaction['credit'], 1, 'R', false, 0);
            $pdf->MultiCell(85, 8, $transaction['description'], 1, 'L', false, 1);
            $pdf->Ln();
        }


        // Output PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="account_statement_' . date('Ymd_His') . '.pdf"');
        $pdf->Output('account_statement.pdf', 'I');
        logActivity("✅ PDF generated successfully");
        exit;

    } catch (Exception $e) {
        logActivity("❌ PDF generation failed: " . $e->getMessage());
        http_response_code(500);
        die(json_encode(["error" => "Failed to generate PDF"]));
    }
} else {
    $errorMsg = "Invalid format requested: $format";
    logActivity("❌ $errorMsg");
    http_response_code(400);
    die(json_encode(["error" => "Invalid format. Choose 'pdf' or 'csv'."]));
}