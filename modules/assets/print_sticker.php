<?php
require_once __DIR__ . '/../../includes/session_config.php';
require_once __DIR__ . '/../../includes/db_connection.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    die("Access denied. Please log in.");
}

// Get Asset ID
$assetId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($assetId <= 0) {
    die("Invalid Asset ID.");
}

// Fetch Asset Details
try {
    $stmt = $pdo->prepare("
        SELECT 
            fa.AssetName, 
            fa.AssetSerial, 
            fa.ProductSerial,
            ac.CategoryName,
            fa.PurchaseDate
        FROM fixedassets fa
        LEFT JOIN assetcategories ac ON fa.CategoryID = ac.CategoryID
        WHERE fa.AssetID = :id
    ");
    $stmt->execute([':id' => $assetId]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$asset) {
        die("Asset not found.");
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Use AssetSerial if available, otherwise "ASSET-{ID}"
$barcodeValue = $asset['AssetSerial'] ?: 'ASSET-' . $assetId;
$displaySerial = $asset['AssetSerial'] ?: 'N/A';
$productSerial = isset($asset['ProductSerial']) ? trim((string)$asset['ProductSerial']) : '';

// Include TCPDF
$tcpdfPath = __DIR__ . '/../../plugins/TCPDF/tcpdf.php';
if (!file_exists($tcpdfPath)) {
    die("Error: TCPDF library not found at $tcpdfPath");
}
require_once $tcpdfPath;

// Initialize PDF
// Landscape, Millimeters, Custom format [101.6, 50.8] => 4x2 inches
$pdf = new TCPDF('L', 'mm', [101.6, 50.8], true, 'UTF-8', false);

// Metadata
$pdf->SetCreator('PHP-HRMS');
$pdf->SetAuthor('System');
$pdf->SetTitle('Asset Sticker - ' . $asset['AssetName']);
$pdf->SetMargins(0, 0, 0); // Use full page for two labels
$pdf->SetAutoPageBreak(false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->AddPage();

// -- Design Layout (Two labels on one 4x2 page) --
$style1D = array(
    'position' => '',
    'align' => 'C',
    'stretch' => false,
    'fitwidth' => true,
    'cellfitalign' => '',
    'border' => false,
    'hpadding' => 'auto',
    'vpadding' => 'auto',
    'fgcolor' => array(0, 0, 0),
    'bgcolor' => false,
    'text' => true,
    'font' => 'helvetica',
    'fontsize' => 7,
    'stretchtext' => 4
);

$style2D = array(
    'border' => 0,
    'vpadding' => 'auto',
    'hpadding' => 'auto',
    'fgcolor' => array(0, 0, 0),
    'bgcolor' => false,
    'module_width' => 1,
    'module_height' => 1
);

$dateStr = $asset['PurchaseDate'] ? date('Y-m-d', strtotime($asset['PurchaseDate'])) : '-';
$catStr = $asset['CategoryName'] ?: 'General';

$renderLabel = function($originX) use ($pdf, $asset, $barcodeValue, $displaySerial, $productSerial, $dateStr, $catStr, $style1D, $style2D) {
    $headerX = $originX + 1.5;
    $labelWidth = 50.8;

    // Header
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetXY($headerX, 4);
    $pdf->Cell($labelWidth - 3, 5, 'PRIME EXPRESS HRMS', 0, 1, 'C');

    // Asset Name
    $pdf->SetFont('helvetica', 'B', 12);
    $displayName = mb_strimwidth($asset['AssetName'], 0, 18, '...');
    $pdf->SetXY($headerX, 10);
    $pdf->Cell($labelWidth - 3, 6, $displayName, 0, 1, 'C');

    // Category & Date line
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetXY($headerX, 15);
    $pdf->Cell($labelWidth - 3, 4, "Cat: $catStr | Date: $dateStr", 0, 1, 'C');

    // Barcode + QR (aligned and centered)
    $pdf->write1DBarcode($barcodeValue, 'C128', $originX + 2.4, 21.5, 30, 16, 0.4, $style1D, 'N');
    $pdf->write2DBarcode($barcodeValue, 'QRCODE,L', $originX + 33.4, 21.5, 15, 15, $style2D, 'N');

    // Serial Number
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetXY($headerX, 39.5);
    $pdf->Cell($labelWidth - 3, 4, "S/N: " . $displaySerial, 0, 1, 'C');

    if ($productSerial !== '') {
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetXY($headerX, 43.5);
        $pdf->Cell($labelWidth - 3, 3.5, "Product Serial No.: " . $productSerial, 0, 1, 'C');
    }
};

// Left label
$renderLabel(0);
// Right label
$renderLabel(50.8);

// Optional separator line in the middle
$pdf->SetDrawColor(220, 220, 220);
$pdf->Line(50.8, 1, 50.8, 49.8);

// Output PDF
$pdf->Output('sticker_' . $barcodeValue . '.pdf', 'I');
