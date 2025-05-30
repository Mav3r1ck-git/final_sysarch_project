<?php
session_start();
require_once 'database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

// Get format from URL parameter
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';
$lab_filter = isset($_GET['lab']) ? $_GET['lab'] : '';
$purpose_filter = isset($_GET['purpose']) ? $_GET['purpose'] : '';

// Prepare the query with filters
$query = "
    SELECT ls.*, u.first_name, u.last_name, u.course 
    FROM logged_out_sitins ls 
    JOIN users u ON ls.user_id = u.user_id 
    WHERE 1=1
";
$params = [];

if (!empty($lab_filter)) {
    $query .= " AND ls.lab = ?";
    $params[] = $lab_filter;
}

if (!empty($purpose_filter)) {
    $query .= " AND ls.purpose = ?";
    $params[] = $purpose_filter;
}

$query .= " ORDER BY ls.logout_time DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$headers = ['Student ID', 'Name', 'Course', 'Lab', 'PC Number', 'Purpose', 'Time In', 'Time Out', 'Status'];

// Set headers based on format
switch ($format) {
    case 'csv':
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="sit_in_data.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, $headers);
        foreach ($data as $row) {
            fputcsv($output, [
                $row['user_id'],
                $row['last_name'] . ', ' . $row['first_name'],
                $row['course'],
                $row['lab'],
                $row['pc_number'],
                $row['purpose'],
                $row['sitin_time'],
                $row['logout_time'],
                $row['was_rewarded'] ? 'Rewarded' : 'Normal'
            ]);
        }
        fclose($output);
        break;

    case 'excel':
        require_once __DIR__ . '/libs/SimpleXLSXGen.php';
        $rows = [];
        $rows[] = $headers;
        foreach ($data as $row) {
            $rows[] = [
                $row['user_id'],
                $row['last_name'] . ', ' . $row['first_name'],
                $row['course'],
                $row['lab'],
                $row['pc_number'],
                $row['purpose'],
                $row['sitin_time'],
                $row['logout_time'],
                $row['was_rewarded'] ? 'Rewarded' : 'Normal'
            ];
        }
        \Shuchkin\SimpleXLSXGen::fromArray($rows)->downloadAs('sit_in_data.xlsx');
        break;

    case 'pdf':
        require_once __DIR__ . '/libs/fpdf.php';
        $pdf = new FPDF('L', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'University of Cebu-Main', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 14);
        $pdf->Cell(0, 8, 'College of Computer Studies', 0, 1, 'C');
        $pdf->Cell(0, 8, 'Computer Laboratory Sitin Monitoring', 0, 1, 'C');
        $pdf->Cell(0, 8, 'System Report', 0, 1, 'C');
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 10);
        foreach ($headers as $header) {
            $pdf->Cell(38, 8, $header, 1, 0, 'C');
        }
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 10);
        foreach ($data as $row) {
            $pdf->Cell(38, 8, $row['user_id'], 1);
            $pdf->Cell(38, 8, $row['last_name'] . ', ' . $row['first_name'], 1);
            $pdf->Cell(38, 8, $row['course'], 1);
            $pdf->Cell(38, 8, $row['lab'], 1);
            $pdf->Cell(38, 8, $row['pc_number'], 1);
            $pdf->Cell(38, 8, $row['purpose'], 1);
            $pdf->Cell(38, 8, $row['sitin_time'], 1);
            $pdf->Cell(38, 8, $row['logout_time'], 1);
            $pdf->Cell(38, 8, $row['was_rewarded'] ? 'Rewarded' : 'Normal', 1);
            $pdf->Ln();
        }
        $pdf->Output('D', 'sit_in_data.pdf');
        break;

    default:
        header("Location: admin_dashboard.php");
        exit();
}
