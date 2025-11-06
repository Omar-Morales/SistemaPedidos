<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DailyClosureExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithEvents
{
    /**
     * @param Collection<int, array<string, mixed>> $details
     * @param array<string, mixed> $summary
     * @param array<string, mixed> $meta
     */
    public function __construct(
        private readonly Collection $details,
        private readonly array $summary,
        private readonly array $meta
    ) {
    }

    public function collection(): Collection
    {
        return $this->details;
    }

    public function headings(): array
    {
        return [
            '#',
            'Cliente',
            'Producto',
            'Cantidad',
            'Unidad',
            'Estado de Pago',
            'Metodo de Pago',
            'Total (S/)',
            'Pagado (S/)',
            'Diferencia (S/)',
            'Pendiente (S/)',
        ];
    }

    public function map($row): array
    {
        return [
            $row['index'] ?? '',
            $row['customer'] ?? '',
            $row['product'] ?? '',
            $row['quantity'] ?? 0,
            $row['unit'] ?? '',
            $row['payment_status']['label'] ?? '',
            $row['payment_method']['label'] ?? '',
            number_format($row['total'] ?? 0, 2, '.', ''),
            number_format($row['amount_paid'] ?? 0, 2, '.', ''),
            number_format($row['difference'] ?? 0, 2, '.', ''),
            number_format($row['pending'] ?? 0, 2, '.', ''),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                $maxColumn = 'K';
                $warehouse = $this->meta['warehouse_label'] ?? 'Almacen';
                $date = $this->meta['date_display'] ?? $this->meta['date'] ?? '';

                // Inserta filas superiores para título y fecha
                $sheet->insertNewRowBefore(1, 2);

                $sheet->setCellValue('A1', "Cierre Diario - {$warehouse}");
                $sheet->mergeCells("A1:{$maxColumn}1");
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1F4E78']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->setCellValue('A2', "Fecha: {$date}");
                $sheet->mergeCells("A2:{$maxColumn}2");
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['italic' => true, 'color' => ['rgb' => '333333']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $dataRows = $this->details->count();
                $headerRow = 3;
                $dataStartRow = $headerRow + 1;
                $dataEndRow = $dataRows > 0 ? $dataStartRow + $dataRows - 1 : $headerRow;

                // Encabezado de la tabla principal
                $headerRange = "A{$headerRow}:{$maxColumn}{$headerRow}";
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1F4E78'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                if ($dataRows > 0) {
                    $dataRange = "A{$headerRow}:{$maxColumn}{$dataEndRow}";

                    $sheet->getStyle($dataRange)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'D9D9D9'],
                            ],
                        ],
                    ]);

                    for ($row = $dataStartRow; $row <= $dataEndRow; $row++) {
                        if ($row % 2 === 0) {
                            $sheet->getStyle("A{$row}:{$maxColumn}{$row}")
                                ->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()
                                ->setRGB('F3F6FB');
                        }
                    }

                    $sheet->getStyle("H{$dataStartRow}:{$maxColumn}{$dataEndRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                $sheet->setAutoFilter("A{$headerRow}:{$maxColumn}{$dataEndRow}");

                $labelTotalsRow = $dataEndRow + 1;
                $valueTotalsRow = $labelTotalsRow + 1;

                $sheet->mergeCells("A{$labelTotalsRow}:G{$labelTotalsRow}");
                $sheet->getStyle("A{$labelTotalsRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $incomeValue = (float) ($this->summary['income_total'] ?? 0);
                $pendingValue = (float) ($this->summary['pending_amount'] ?? 0);
                $changeValue = (float) ($this->summary['change_total'] ?? 0);
                $grandValue = $incomeValue + $pendingValue;

                $sheet->setCellValue("H{$labelTotalsRow}", 'Total General');
                $sheet->setCellValue("I{$labelTotalsRow}", 'Ingresos Cobrados');
                $sheet->setCellValue("J{$labelTotalsRow}", 'Vueltos pendientes');
                $sheet->setCellValue("K{$labelTotalsRow}", 'Total pendiente');

                $sheet->setCellValue("H{$valueTotalsRow}", $grandValue);
                $sheet->setCellValue("I{$valueTotalsRow}", $incomeValue);
                $sheet->setCellValue("J{$valueTotalsRow}", $changeValue);
                $sheet->setCellValue("K{$valueTotalsRow}", $pendingValue);

                $sheet->getStyle("H{$valueTotalsRow}:K{$valueTotalsRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("H{$valueTotalsRow}:K{$valueTotalsRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');

                $sheet->getStyle("H{$labelTotalsRow}:K{$valueTotalsRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D9D9D9'],
                        ],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E9EDF5'],
                    ],
                ]);
                $sheet->getStyle("H{$labelTotalsRow}:K{$labelTotalsRow}")
                    ->getAlignment()
                    ->setWrapText(true);

                $saldoLabelRow = $valueTotalsRow + 1;
                $saldoValueRow = $saldoLabelRow + 1;

                $sheet->setCellValue("J{$saldoLabelRow}", 'Saldo pendiente por cobrar');
                $sheet->getStyle("J{$saldoLabelRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E9EDF5'],
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D9D9D9'],
                        ],
                    ],
                ]);

                $sheet->setCellValue("J{$saldoValueRow}", $pendingValue);
                $sheet->getStyle("J{$saldoValueRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("J{$saldoValueRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D9D9D9'],
                        ],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E9EDF5'],
                    ],
                ]);
                $sheet->getStyle("J{$saldoValueRow}")->getNumberFormat()->setFormatCode('#,##0.00');

                $cashTotalRow = $saldoValueRow + 2;
                $cashCollected = (float) ($this->summary['method_collected_totals']['efectivo'] ?? 0);

                $sheet->setCellValue("A{$cashTotalRow}", 'Total Efectivo Cobrado');
                $sheet->setCellValue("B{$cashTotalRow}", $cashCollected);

                $cashRange = "A{$cashTotalRow}:B{$cashTotalRow}";
                $sheet->getStyle($cashRange)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '2E75B6'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '1F4E78'],
                        ],
                    ],
                ]);
                $sheet->getStyle("B{$cashTotalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            },
        ];
    }
}
