<?php
/**
 * Report Export to Excel (.xlsx)
 * Generates a real Excel file using SpreadsheetML (XML-based, no external library needed)
 * MOMAI PLYWOOD - Core PHP
 */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth_guard.php';

$pdo   = get_db();
$type  = $_GET['type'] ?? 'pending';
$valid = ['pending', 'out_of_stock', 'low_stock', 'all'];

if (!in_array($type, $valid, true)) {
    http_response_code(400);
    exit('Invalid report type.');
}

// ─── Fetch Data ───────────────────────────────────────────────────────────────

$pendingBills       = [];
$outOfStockProducts = [];
$lowStockProducts   = [];

if ($type === 'pending' || $type === 'all') {
    $stmt = $pdo->query("
        SELECT bill_number, customer_name, customer_phone, created_at,
               grand_total, paid_amount, due_amount, payment_status
        FROM bills
        WHERE deleted_at IS NULL AND (due_amount > 0 OR payment_status IN ('partial','unpaid'))
        ORDER BY id DESC
    ");
    $pendingBills = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($type === 'out_of_stock' || $type === 'all') {
    $stmt = $pdo->query("
        SELECT sku, name, company_name, category, selling_price, stock_quantity, unit
        FROM products
        WHERE deleted_at IS NULL AND stock_quantity <= 0
        ORDER BY id DESC
    ");
    $outOfStockProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($type === 'low_stock' || $type === 'all') {
    $stmt = $pdo->query("
        SELECT sku, name, company_name, category, selling_price, stock_quantity, min_alert_stock, unit
        FROM products
        WHERE deleted_at IS NULL AND stock_quantity > 0 AND stock_quantity <= min_alert_stock
        ORDER BY stock_quantity ASC
    ");
    $lowStockProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ─── Helper: escape XML cell value ───────────────────────────────────────────

function xv(string $value): string
{
    return htmlspecialchars($value, ENT_XML1, 'UTF-8');
}

/**
 * Build a single worksheet XML string
 *
 * @param  array  $headers  Column header labels
 * @param  array  $rows     Array of rows; each row is an array of cell values
 */
function build_sheet(array $headers, array $rows): string
{
    $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n";
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $xml .= '<sheetData>';

    // Header row (row 1)
    $xml .= '<row r="1">';
    $col = 0;
    foreach ($headers as $h) {
        $colLetter = col_letter($col++);
        $xml .= '<c r="' . $colLetter . '1" t="inlineStr" s="1"><is><t>' . xv($h) . '</t></is></c>';
    }
    $xml .= '</row>';

    // Data rows
    $rowNum = 2;
    foreach ($rows as $row) {
        $xml .= '<row r="' . $rowNum . '">';
        $col = 0;
        foreach ($row as $cell) {
            $colLetter = col_letter($col++);
            $cellRef   = $colLetter . $rowNum;
            if (is_numeric($cell) && $cell !== '') {
                $xml .= '<c r="' . $cellRef . '"><v>' . xv((string) $cell) . '</v></c>';
            } else {
                $xml .= '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . xv((string) $cell) . '</t></is></c>';
            }
        }
        $xml .= '</row>';
        $rowNum++;
    }

    $xml .= '</sheetData></worksheet>';
    return $xml;
}

function col_letter(int $index): string
{
    $letter = '';
    $index++;
    while ($index > 0) {
        $index--;
        $letter = chr(65 + ($index % 26)) . $letter;
        $index  = (int) ($index / 26);
    }
    return $letter;
}

// ─── Build worksheets ─────────────────────────────────────────────────────────

$sheets     = [];
$sheetNames = [];

if ($type === 'pending' || $type === 'all') {
    $headers = ['Bill No', 'Customer', 'Phone', 'Date', 'Grand Total (Rs)', 'Paid (Rs)', 'Balance Due (Rs)', 'Status'];
    $rows    = [];
    foreach ($pendingBills as $b) {
        $rows[] = [
            $b['bill_number'],
            $b['customer_name'],
            $b['customer_phone'] ?? '',
            date('d-m-Y', strtotime($b['created_at'])),
            number_format((float) $b['grand_total'], 2, '.', ''),
            number_format((float) $b['paid_amount'], 2, '.', ''),
            number_format((float) $b['due_amount'], 2, '.', ''),
            ucfirst($b['payment_status']),
        ];
    }
    $sheets[]     = build_sheet($headers, $rows);
    $sheetNames[] = 'Pending Bills';
}

if ($type === 'out_of_stock' || $type === 'all') {
    $headers = ['SKU', 'Product Name', 'Company', 'Category', 'Selling Price (Rs)', 'Stock', 'Unit'];
    $rows    = [];
    foreach ($outOfStockProducts as $p) {
        $rows[] = [
            $p['sku'],
            $p['name'],
            $p['company_name'] ?? '',
            $p['category'] ?? '',
            number_format((float) $p['selling_price'], 2, '.', ''),
            0,
            $p['unit'] ?? '',
        ];
    }
    $sheets[]     = build_sheet($headers, $rows);
    $sheetNames[] = 'Out of Stock';
}

if ($type === 'low_stock' || $type === 'all') {
    $headers = ['SKU', 'Product Name', 'Company', 'Category', 'Selling Price (Rs)', 'Remaining Stock', 'Min Alert Level', 'Unit'];
    $rows    = [];
    foreach ($lowStockProducts as $p) {
        $rows[] = [
            $p['sku'],
            $p['name'],
            $p['company_name'] ?? '',
            $p['category'] ?? '',
            number_format((float) $p['selling_price'], 2, '.', ''),
            $p['stock_quantity'],
            $p['min_alert_stock'],
            $p['unit'] ?? '',
        ];
    }
    $sheets[]     = build_sheet($headers, $rows);
    $sheetNames[] = 'Low Stock Alert';
}

// ─── Build .xlsx (ZIP) ────────────────────────────────────────────────────────

$date     = date('Y-m-d');
$filename = "MOMAI_PLYWOOD_Report_{$date}.xlsx";

// [Content_Types].xml
$contentTypes  = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n";
$contentTypes .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
$contentTypes .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
$contentTypes .= '<Default Extension="xml" ContentType="application/xml"/>';
$contentTypes .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
$contentTypes .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
for ($i = 0; $i < count($sheets); $i++) {
    $si = $i + 1;
    $contentTypes .= '<Override PartName="/xl/worksheets/sheet' . $si . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
}
$contentTypes .= '</Types>';

// _rels/.rels
$rels  = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n";
$rels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
$rels .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>';
$rels .= '</Relationships>';

// xl/_rels/workbook.xml.rels
$wbRels  = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n";
$wbRels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
$wbRels .= '<Relationship Id="rId100" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
for ($i = 0; $i < count($sheets); $i++) {
    $si = $i + 1;
    $wbRels .= '<Relationship Id="rId' . $si . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $si . '.xml"/>';
}
$wbRels .= '</Relationships>';

// xl/workbook.xml
$workbook  = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n";
$workbook .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
$workbook .= '<sheets>';
for ($i = 0; $i < count($sheets); $i++) {
    $si       = $i + 1;
    $shName   = xv($sheetNames[$i]);
    $workbook .= '<sheet name="' . $shName . '" sheetId="' . $si . '" r:id="rId' . $si . '"/>';
}
$workbook .= '</sheets></workbook>';

// xl/styles.xml - minimal with bold header style (s=1)
$styles  = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n";
$styles .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
$styles .= '<fonts count="2"><font><sz val="10"/><name val="Calibri"/></font><font><b/><sz val="10"/><name val="Calibri"/></font></fonts>';
$styles .= '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>';
$styles .= '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>';
$styles .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
$styles .= '<cellXfs count="2">';
$styles .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'; // s=0 normal
$styles .= '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>'; // s=1 bold
$styles .= '</cellXfs>';
$styles .= '</styleSheet>';

// ─── Create ZIP ───────────────────────────────────────────────────────────────

$tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
$zip     = new ZipArchive();

if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('Failed to create Excel file. ZipArchive not available.');
}

$zip->addFromString('[Content_Types].xml', $contentTypes);
$zip->addFromString('_rels/.rels', $rels);
$zip->addFromString('xl/workbook.xml', $workbook);
$zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
$zip->addFromString('xl/styles.xml', $styles);

for ($i = 0; $i < count($sheets); $i++) {
    $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $sheets[$i]);
}

$zip->close();

// ─── Stream to browser ────────────────────────────────────────────────────────

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: max-age=0');
header('Pragma: public');

readfile($tmpFile);
unlink($tmpFile);
exit;
