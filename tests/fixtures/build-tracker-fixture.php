<?php

/**
 * Builds tests/fixtures/tracker_fixture.xlsx — a small copy of Ream_Business_Tracker_v3.xlsx
 * (same sheets, header on row 4, data from row 5; formula columns left empty) with the
 * tracker's data plus SAMPLE rows, a "Sample Reseller" and a blank row that must be skipped.
 *
 *   php tests/fixtures/build-tracker-fixture.php
 */

require __DIR__.'/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Excel serial dates: 46277 = Sep 12, 2026 · 46292 = Sep 27, 2026
$sheets = [
    'Products' => [
        'header' => ['Product Code', 'Description', 'Reseller Price / Ream', 'Retail Price / Ream', 'Reorder Level (reams)'],
        'rows' => [
            ['CR', 'CR ream', null, null, 5],
            ['CB', 'CB ream', null, null, 2],
            ['PR', 'PR ream', null, null, 2],
            ['CC', 'CC ream', null, null, 1],
        ],
    ],
    'Resellers' => [
        'header' => ['Reseller Name', 'Contact / Location', 'Total Reams Taken', 'Total Amount Taken', 'Total Remitted', 'Balance to Remit', 'Status'],
        'rows' => [
            ['Plaza', 'San Juan'],
            ['Tito Richie', 'San Juan'],
            ['Kariz Store', 'Cansinala'],
            ['Sample Reseller', 'Nowhere'],
        ],
    ],
    'PriceHistory' => [
        'header' => ['Product', 'Effective Date', 'Reseller (blank = All)', 'Reseller Price / Ream', 'Retail Price / Ream', 'Notes'],
        'rows' => [
            ['CR', 46277, null, 740, null, 'Starting price'],
            ['CB', 46277, null, 810, null, 'Starting price'],
            ['PR', 46277, null, 350, null, 'Starting price'],
            ['CC', 46277, null, 800, null, 'Starting price'],
            ['CR', 46292, null, 730, null, 'Price change 740 → 730'],
            ['CB', 46292, null, 800, null, 'Price change 810 → 800'],
            ['CR', 46277, 'Plaza', 800, null, 'Plaza special price'],
            ['CB', 46277, 'Plaza', 850, null, 'Plaza special price'],
            ['PR', 46277, 'Plaza', 400, null, 'Plaza special price'],
            ['CC', 46277, 'Plaza', 800, null, 'Plaza special price'],
        ],
    ],
    'Purchases' => [
        'header' => ['Date', 'Product', 'Qty (reams)', 'Cost / Ream', 'Total Cost', 'Supplier', 'Notes'],
        'rows' => [
            [46276, 'CR', 999, 1, null, 'Nobody', 'SAMPLE entry – delete'],
            [46276, 'CR', 16, 680, null, 'Tatay'],
            [46276, 'CB', 10, 760, null, 'Tatay'],
            [46276, 'PR', 5, 250, null, 'Tatay'],
            [46283, 'PR', 5, 250, null, 'Tatay'],
            [46287, 'CR', 10, 680, null, 'Tatay'],
            [46289, 'CC', 1, 680, null, 'Online'],
            [46292, 'CR', 10, 680, null, 'Tatay'],
            [46294, 'CR', 5, 680, null, 'Tatay'],
            [46294, 'CB', 5, 750, null, 'Tatay'],
            [],
            [46299, 'CR', 50, 690, null, 'Tatay'],
            [46299, 'CB', 2, 750, null, 'Tatay'],
            [46300, 'PR', 3, 250, null, 'Tatay'],
            [46300, 'CB', 3, 750, null, 'Tatay'],
            [46302, 'CB', 3, 730, null, 'Tatay'],
        ],
    ],
    'Distribution' => [
        'header' => ['Date', 'Reseller', 'Product', 'Qty (reams)', 'Price / Ream', 'Total Amount', 'Notes'],
        'rows' => [
            [46277, 'Sample Reseller', 'CR', 5],
            [46277, 'Tito Richie', 'CR', 7],
            [46277, 'Tito Richie', 'CB', 5],
            [46277, 'Tito Richie', 'PR', 1],
            [46283, 'Tito Richie', 'CR', 1],
            [46286, 'Tito Richie', 'CR', 3],
            [46288, 'Tito Richie', 'CR', 4],
            [46288, 'Tito Richie', 'CB', 3],
            [46290, 'Tito Richie', 'CR', 3],
            [46291, 'Tito Richie', 'CR', 2],
            [46292, 'Tito Richie', 'CR', 5],
            [46293, 'Tito Richie', 'CR', 3],
            [46294, 'Tito Richie', 'CR', 1],
            [46295, 'Tito Richie', 'CR', 4],
            [46295, 'Tito Richie', 'CB', 4],
            [46298, 'Tito Richie', 'PR', 1],
            [46300, 'Tito Richie', 'CR', 10],
            [46300, 'Tito Richie', 'CB', 3],
            [46301, 'Plaza', 'CR', 2, null, null, 'sample only'],
        ],
    ],
    'Remittance' => [
        'header' => ['Date', 'Reseller', 'Amount Remitted', 'Mode', 'Reference No.', 'Balance After (auto)', 'Notes'],
        'rows' => [
            [46283, 'Tito Richie', 2300, 'Cash'],
            [46286, 'Tito Richie', 1700, 'Cash'],
            [46288, 'Tito Richie', 4040, 'Cash'],
            [46291, 'Tito Richie', 3500, 'Cash'],
            [46294, 'Tito Richie', 6620, 'Cash'],
            [46297, 'Tito Richie', 4000, 'Cash'],
            [46298, 'Tito Richie', 3000, 'Cash'],
            [46300, 'Tito Richie', 4500, 'Cash'],
            [46302, 'Tito Richie', 3300, 'Cash'],
            [46302, 'Sample Reseller', 500, 'GCash', '123'],
        ],
    ],
    'Expenses' => [
        'header' => ['Date', 'Category', 'Description', 'Amount', 'Paid By / Mode', 'Notes'],
        'rows' => [
            [46289, 'Other', 'Fake CR and CB', 1120, 'CASH', 'Online Purchase'],
            [46291, 'Other', 'softdrinks, CR/CC sample', 210, 'CASH'],
            [46292, 'Other', 'CR', 85, 'CASH'],
            [46298, 'Other', 'CR', 85, 'CASH'],
            [46299, 'Other', 'Gas', 100, 'CASH'],
            [46299, 'Delivery', 'Test', 999, 'CASH', 'SAMPLE'],
        ],
    ],
];

$book = new Spreadsheet;
$book->removeSheetByIndex(0);

foreach ($sheets as $name => $sheet) {
    $ws = $book->createSheet();
    $ws->setTitle($name);
    $ws->setCellValue('A1', $name);
    $ws->setCellValue('A2', 'Fixture for tests — same layout as the real tracker.');
    $ws->fromArray($sheet['header'], null, 'A4');

    foreach ($sheet['rows'] as $i => $row) {
        if ($row !== []) {
            $ws->fromArray($row, null, 'A'.(5 + $i));
        }
    }
}

(new Xlsx($book))->save(__DIR__.'/tracker_fixture.xlsx');

echo "Wrote tests/fixtures/tracker_fixture.xlsx\n";
