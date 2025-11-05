<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

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
            'Método de Pago',
            'Total (S/)',
            'Pagado (S/)',
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
            number_format($row['pending'] ?? 0, 2, '.', ''),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                $summaryStartRow = ($this->details->count() ?: 0) + 3;
                $warehouse = $this->meta['warehouse_label'] ?? 'Almacén';
                $date = $this->meta['date_display'] ?? $this->meta['date'] ?? '';

                $sheet->setCellValue("A{$summaryStartRow}", "Cierre Diario - {$warehouse}");
                $sheet->setCellValue('A' . ($summaryStartRow + 1), "Fecha: {$date}");

                $sheet->setCellValue('A' . ($summaryStartRow + 3), 'Total de productos:');
                $sheet->setCellValue('B' . ($summaryStartRow + 3), $this->summary['total_orders'] ?? 0);

                $sheet->setCellValue('A' . ($summaryStartRow + 4), 'Productos pagados:');
                $sheet->setCellValue('B' . ($summaryStartRow + 4), $this->summary['paid_orders'] ?? 0);

                $sheet->setCellValue('A' . ($summaryStartRow + 5), 'Productos pendientes:');
                $sheet->setCellValue('B' . ($summaryStartRow + 5), $this->summary['pending_orders'] ?? 0);

                $sheet->setCellValue('A' . ($summaryStartRow + 6), 'Ingresos del día (todos los métodos):');
                $sheet->setCellValue('B' . ($summaryStartRow + 6), number_format($this->summary['income_total'] ?? 0, 2, '.', ''));

                $sheet->setCellValue('A' . ($summaryStartRow + 7), 'Ingresos en efectivo:');
                $sheet->setCellValue('B' . ($summaryStartRow + 7), number_format($this->summary['income_cash'] ?? 0, 2, '.', ''));

                $sheet->setCellValue('A' . ($summaryStartRow + 8), 'Saldo pendiente por cobrar:');
                $sheet->setCellValue('B' . ($summaryStartRow + 8), number_format($this->summary['pending_amount'] ?? 0, 2, '.', ''));
            },
        ];
    }
}
