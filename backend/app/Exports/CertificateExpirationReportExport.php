<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection as BaseCollection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * Export de certificados próximos a vencer para una empresa
 *
 * Genera un archivo Excel con el detalle de todos los certificados
 * próximos a vencer de una empresa específica.
 */
class CertificateExpirationReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    private BaseCollection $certificates;

    /**
     * Create a new export instance.
     *
     * @param BaseCollection $certificates
     */
    public function __construct(BaseCollection $certificates)
    {
        $this->certificates = $certificates;
    }

    /**
     * Get the data collection.
     *
     * @return BaseCollection
     */
    public function collection(): BaseCollection
    {
        return $this->certificates;
    }

    /**
     * Get the headings for the spreadsheet.
     *
     * @return array
     */
    public function headings(): array
    {
        return [
            'NIT/DNI',
            'Dígito Verificador',
            'Representante Legal',
            'Teléfono',
            'Fecha de Vencimiento',
            'Días Restantes',
            'Nivel de Urgencia',
            'Solicitud ID',
        ];
    }

    /**
     * Map the data for each row.
     *
     * @param mixed $row
     * @return array
     */
    public function map($row): array
    {
        $daysRemaining = now()->diffInDays(Carbon::parse($row->expiration_date), false);
        $urgency = $this->getUrgencyLevel($daysRemaining);

        return [
            $row->dni ?? 'N/A',
            $row->dv ?? 'N/A',
            $row->legal_representative ?? 'N/A',
            $row->phone ?? 'N/A',
            Carbon::parse($row->expiration_date)->format('d/m/Y'),
            $daysRemaining,
            $urgency,
            $row->id,
        ];
    }

    /**
     * Get the urgency level label for a given number of days.
     *
     * @param int $daysRemaining
     * @return string
     */
    private function getUrgencyLevel(int $daysRemaining): string
    {
        if ($daysRemaining <= 0) {
            return 'VENCIDO';
        } elseif ($daysRemaining <= 7) {
            return 'CRÍTICO';
        } elseif ($daysRemaining <= 15) {
            return 'ALTA PRIORIDAD';
        } elseif ($daysRemaining <= 30) {
            return 'MEDIA PRIORIDAD';
        }

        return 'BAJO RIESGO';
    }
}
