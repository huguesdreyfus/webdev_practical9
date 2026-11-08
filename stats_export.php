<?php
    // stats_export.php - admin-only Excel (.xlsx) export, same filters as stats.php
    require_once 'config.php';
    require_once 'roles.php';
    require_once 'stats_common.php';

    if (!defined('API_AUTHENTICATED')) {
        require_once __DIR__ . '/auth.php';
        require_admin();
    }

    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        http_response_code(500);
        exit('PhpSpreadsheet is not installed. Run: composer require phpoffice/phpspreadsheet');
    }
    require_once $autoload;

    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
    use PhpOffice\PhpSpreadsheet\Cell\DataType;

    $f = stats_filters();
    [$w, $wt, $wp] = stats_where($f);

    $orders = stats_rows($conn, "SELECT o.id, o.created_at, o.customer_name, o.user_id, u.role,
                                        o.status, s.username AS staff_name, o.total
                                 FROM orders o
                                 LEFT JOIN users u ON o.user_id = u.id
                                 LEFT JOIN users s ON o.staff_id = s.id
                                 WHERE $w
                                 ORDER BY o.id DESC", $wt, $wp);

    $lines = stats_rows($conn, "SELECT o.id AS order_id, o.created_at, o.customer_name, o.status,
                                       p.name AS product, oi.quantity, oi.unit_price, oi.discount_percent,
                                       ROUND(oi.quantity * oi.unit_price * (1 - oi.discount_percent / 100), 2) AS line_total
                                FROM order_items oi
                                JOIN orders o   ON oi.order_id = o.id
                                JOIN products p ON oi.product_id = p.id
                                WHERE $w
                                ORDER BY o.id DESC, oi.id", $wt, $wp);

    // Writes one row. Text is always stored as a string cell, never as a formula,
    // so a customer name such as "=HYPERLINK(...)" cannot execute in Excel.
    function xl_write($sheet, int $row, array $values): void
    {
        foreach (array_values($values) as $i => $v) {
            $coord = Coordinate::stringFromColumnIndex($i + 1) . $row;
            if (is_int($v) || is_float($v)) {
                $sheet->setCellValueExplicit($coord, $v, DataType::TYPE_NUMERIC);
            } else {
                $sheet->setCellValueExplicit($coord, (string) ($v ?? ''), DataType::TYPE_STRING);
            }
        }
    }

    // Bold header, frozen first row, filter arrows, auto width.
    function xl_style($sheet, string $lastCol, int $lastRow): void
    {
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastCol}" . max(2, $lastRow));
        for ($i = 1, $n = Coordinate::columnIndexFromString($lastCol); $i <= $n; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }
    }

    $spreadsheet = new Spreadsheet();

    // ---- Sheet 1: Orders (one row per order) ----
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Orders');
    xl_write($sheet, 1, ['Order #', 'Date', 'Customer', 'Customer type', 'Status', 'Staff', 'Total (IDR)']);

    $r = 2;
    $revenue = 0.0;
    $salesCount = 0;
    foreach ($orders as $o) {
        $total = (float) $o['total'];
        if (in_array($o['status'], ['done', 'archived'], true)) {
            $revenue += $total;
            $salesCount++;
        }
        xl_write($sheet, $r++, [
            (int) $o['id'],
            $o['created_at'],
            $o['customer_name'],
            stats_customer_type($o['user_id'], $o['role']),
            $o['status'],
            $o['staff_name'] ?? '',
            $total,
        ]);
    }
    $sheet->getStyle('G2:G' . max(2, $r - 1))->getNumberFormat()->setFormatCode('#,##0');
    xl_style($sheet, 'G', $r - 1);

    // ---- Sheet 2: Order lines (one row per item) ----
    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('Order lines');
    xl_write($sheet2, 1, ['Order #', 'Date', 'Customer', 'Status', 'Product', 'Quantity',
                          'Unit price (IDR)', 'Discount %', 'Line total (IDR)']);

    $r2 = 2;
    foreach ($lines as $l) {
        xl_write($sheet2, $r2++, [
            (int) $l['order_id'],
            $l['created_at'],
            $l['customer_name'],
            $l['status'],
            $l['product'],
            (int) $l['quantity'],
            (float) $l['unit_price'],
            (float) $l['discount_percent'],
            (float) $l['line_total'],
        ]);
    }
    $sheet2->getStyle('G2:G' . max(2, $r2 - 1))->getNumberFormat()->setFormatCode('#,##0');
    $sheet2->getStyle('I2:I' . max(2, $r2 - 1))->getNumberFormat()->setFormatCode('#,##0');
    xl_style($sheet2, 'I', $r2 - 1);

    // ---- Sheet 3: Summary (filters used and revenue, from orders.total) ----
    $sheet3 = $spreadsheet->createSheet();
    $sheet3->setTitle('Summary');
    $summary = [
        ['Generated',                      date('Y-m-d H:i:s')],
        ['From',                           $f['from'] !== '' ? $f['from'] : 'all'],
        ['To',                             $f['to'] !== '' ? $f['to'] : 'all'],
        ['Status filter',                  $f['status']],
        ['Orders in export',               count($orders)],
        ['Done + archived orders',         $salesCount],
        ['Revenue, done + archived (IDR)', $revenue],
        ['Average order value (IDR)',      $salesCount > 0 ? round($revenue / $salesCount, 2) : 0.0],
    ];
    foreach ($summary as $i => $row) {
        xl_write($sheet3, $i + 1, $row);
    }
    $sheet3->getStyle('A1:A' . count($summary))->getFont()->setBold(true);
    $sheet3->getStyle('B7:B8')->getNumberFormat()->setFormatCode('#,##0');
    $sheet3->getColumnDimension('A')->setAutoSize(true);
    $sheet3->getColumnDimension('B')->setAutoSize(true);

    $spreadsheet->setActiveSheetIndex(0);

    // ---- Send the file ----
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="orders_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    (new Xlsx($spreadsheet))->save('php://output');
    exit;
?>