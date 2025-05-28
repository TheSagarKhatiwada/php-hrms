<?php
// Apply cache control to prevent showing old versions of the page
require_once 'includes/cache_control.php';

// Include necessary files
include("includes/db_connection.php");
require_once("plugins/tcpdf/tcpdf.php"); // Include TCPDF library from plugins folder
require_once 'includes/report-templates/monthly-attendance.php';

// Suppress warnings and errors to prevent output before PDF generation
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

// Process data and generate PDF
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // First, get the report data by including the data processing file
    // We'll set a flag to prevent the redirect
    $_POST['isAjax'] = true;
    
    // Include the file to get the processed data
    ob_start();
    include 'fetch-periodic-report-data-new.php';
    $jsonOutput = ob_get_clean();
    
    // Parse the JSON response
    $processedData = json_decode($jsonOutput, true);
    
    if (!$processedData || isset($processedData['error'])) {
        // Handle error
        echo "Error: " . ($processedData['error'] ?? "Failed to process report data.");
        exit;
    }
    
    // Create new PDF document
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information    $pdf->SetCreator('PHP-HRMS');
    $pdf->SetAuthor('Prime Express Courier & Cargo');
    $pdf->SetTitle('Periodic Attendance Report');
    $pdf->SetSubject('Employee Attendance');
    
    // Set default header and footer data
    $pdf->setHeaderData('', 0, 'Periodic Attendance Report', 'Generated: ' . date('Y-m-d H:i:s'));
    
    // Set header and footer fonts
    $pdf->setHeaderFont(Array('helvetica', '', 10));
    $pdf->setFooterFont(Array('helvetica', '', 8));
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont('courier');
    
    // Set margins
    $pdf->SetMargins(10, 15, 10);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Set image scale factor
    $pdf->setImageScale(1.25);
    
    // Set font
    $pdf->SetFont('helvetica', '', 8);
    
    // Generate PDF for each employee
    foreach ($processedData['employees'] as $employee) {
        // Add a new page for each employee
        $pdf->AddPage();
        
        // Get the HTML content for this employee
        $html = renderMonthlyAttendanceTable($employee, $processedData['report_meta']['date_range'], true);
        
        // Add some CSS for the table formatting
        $html = '
        <style>
            table {
                border-collapse: collapse;
                width: 100%;
                margin-bottom: 10px;
            }
            th, td {
                border: 0.5px solid #ddd;
                padding: 4px;
                font-size: 8pt;
            }
            th {
                background-color: #f2f2f2;
                font-weight: bold;
                text-align: center;
            }
            .text-center {
                text-align: center;
            }
            .text-end {
                text-align: right;
            }
            .text-start {
                text-align: left;
            }
            tfoot th {
                background-color: #e6e6e6;
            }
        </style>
        ' . $html;
        
        // Output the HTML content
        $pdf->writeHTML($html, true, false, true, false, '');
    }
      // Close and output PDF document
    $pdf->Output('periodic_attendance_report.pdf', 'I');
    exit;
}

// If not a POST request, redirect back to the report page
header('Location: periodic-report.php');
exit;
?>
