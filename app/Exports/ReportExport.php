<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Generic .xlsx export: a title block, a header row, data rows and an optional totals row.
 */
class ReportExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithStyles, WithTitle
{
    /**
     * @param  list<string>  $titleLines
     * @param  list<string>  $headings
     * @param  list<list<mixed>>  $rows
     * @param  list<mixed>|null  $totals
     * @param  array<string, string>  $formats  column letter => number format
     */
    public function __construct(
        private readonly string $sheetTitle,
        private readonly array $titleLines,
        private readonly array $headings,
        private readonly array $rows,
        private readonly ?array $totals = null,
        private readonly array $formats = [],
    ) {}

    public function array(): array
    {
        return [
            ...array_map(fn ($line) => [$line], $this->titleLines),
            [],
            $this->headings,
            ...$this->rows,
            ...($this->totals ? [$this->totals] : []),
        ];
    }

    public function title(): string
    {
        return mb_substr($this->sheetTitle, 0, 31);
    }

    public function columnFormats(): array
    {
        return $this->formats;
    }

    public function styles(Worksheet $sheet): array
    {
        $headerRow = count($this->titleLines) + 2;
        $styles = [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            $headerRow => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F3864']]],
        ];

        if ($this->totals) {
            $styles[$headerRow + count($this->rows) + 1] = ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FBEFD9']]];
        }

        return $styles;
    }
}
