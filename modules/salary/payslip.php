<?php
/**
 * Generate PDF Payslip
 */
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';
require_once '../../plugins/tcpdf/tcpdf.php'; // Adjust path if necessary based on actual structure

if (!is_logged_in()) {
    die('Permission denied');
}

$detail_id = (int)($_GET['id'] ?? 0);

// Fetch Payroll Detail
$stmt = $pdo->prepare("SELECT pd.*, pr.month, pr.year, e.first_name, e.last_name, e.join_date, d.title as designation, b.name as branch
                       FROM payroll_details pd
                       JOIN payroll_runs pr ON pd.payroll_run_id = pr.id
                       JOIN employees e ON pd.employee_id = e.emp_id
                       LEFT JOIN designations d ON e.designation_id = d.id
                       LEFT JOIN branches b ON e.branch_id = b.id
                       WHERE pd.id = ?");
$stmt->execute([$detail_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) die('Payslip not found');

// Fetch Items
$stmt = $pdo->prepare("SELECT * FROM payroll_items WHERE payroll_detail_id = ?");
$stmt->execute([$detail_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$earnings = array_filter($items, fn($i) => $i['component_type'] === 'earning');
$deductions = array_filter($items, fn($i) => $i['component_type'] === 'deduction');

// Initialize TCPDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('PHP HRMS');
$pdf->SetAuthor('HR Department');
$pdf->SetTitle('Payslip - ' . $data['first_name']);

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->AddPage();

// Company Header
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'PHP HRMS Company Ltd.', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 5, '123 Business Park, Tech City', 0, 1, 'C');
$pdf->Cell(0, 5, 'Payslip for ' . date('F Y', mktime(0,0,0,$data['month'], 1, $data['year'])), 0, 1, 'C');
$pdf->Ln(10);

// Employee Details
$pdf->SetFont('helvetica', '', 10);
$html = '
<table border="0" cellpadding="5">
    <tr>
        <td width="15%"><strong>Emp ID:</strong></td>
        <td width="35%">' . $data['employee_id'] . '</td>
        <td width="15%"><strong>Name:</strong></td>
        <td width="35%">' . $data['first_name'] . ' ' . $data['last_name'] . '</td>
    </tr>
    <tr>
        <td><strong>Designation:</strong></td>
        <td>' . ($data['designation'] ?? '-') . '</td>
        <td><strong>Branch:</strong></td>
        <td>' . ($data['branch'] ?? '-') . '</td>
    </tr>
    <tr>
        <td><strong>Date of Join:</strong></td>
        <td>' . $data['join_date'] . '</td>
        <td><strong>Payable Days:</strong></td>
        <td>' . $data['payable_days'] . '</td>
    </tr>
</table>
<hr>';
$pdf->writeHTML($html, true, false, true, false, '');

// Salary Details Table
$html = '
<table border="1" cellpadding="5">
    <tr style="background-color:#f2f2f2;">
        <th width="50%"><strong>Earnings</strong></th>
        <th width="50%"><strong>Deductions</strong></th>
    </tr>
    <tr>
        <td width="50%">
            <table border="0">
';

foreach ($earnings as $earn) {
    $html .= '<tr><td>' . $earn['component_name'] . '</td><td align="right">' . number_format($earn['amount'], 2) . '</td></tr>';
}

$html .= '
            </table>
        </td>
        <td width="50%">
            <table border="0">
';

foreach ($deductions as $ded) {
    $html .= '<tr><td>' . $ded['component_name'] . '</td><td align="right">' . number_format($ded['amount'], 2) . '</td></tr>';
}

$html .= '
            </table>
        </td>
    </tr>
    <tr style="background-color:#f9f9f9;">
        <td align="right"><strong>Total Earnings: ' . number_format($data['gross_salary'], 2) . '</strong></td>
        <td align="right"><strong>Total Deductions: ' . number_format($data['total_deductions'], 2) . '</strong></td>
    </tr>
</table>
<br><br>
<table border="0" cellpadding="5">
    <tr>
        <td align="right" style="font-size:14px; font-weight:bold;">Net Pay: Rs. ' . number_format($data['net_salary'], 2) . '</td>
    </tr>
</table>
<br><br><br>
<p style="font-size:9px;">This is a computer-generated document and does not require a signature.</p>
';

$pdf->writeHTML($html, true, false, true, false, '');

// Output
$pdf->Output('Payslip_' . $data['employee_id'] . '_' . $data['month'] . '_' . $data['year'] . '.pdf', 'I');
