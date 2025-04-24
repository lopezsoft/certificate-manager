<?php

namespace App\Imports;

use App\Common\DateFunctions;
use App\Models\Events\DocumentReception;
use App\Models\Settings\Software;
use App\Services\Events\EventDispatchService;
use App\Services\Xml\XmlExtractDataService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\BeforeImport;

class DocumentReceptionsImport implements ToCollection, WithHeadingRow, WithEvents
{
    use Importable;
    private array $documentId = [
        'Nota de débito electrónica' => 4,
        'Nota de crédito electrónica' => 5,
        'Factura electrónica' => 7,
    ];
    private array $documentTypeList = [
        'Nota de débito electrónica',
        'Nota de crédito electrónica',
        'Factura electrónica'
    ];
    private array $expectedHeaders = [
        'Tipo de documento',
        'CUFE/CUDE',
        'folio',
        'prefijo',
        'Fecha Emisión',
        'Fecha Recepción',
        'NIT Emisor',
        'Nombre Emisor',
        'NIT Receptor',
        'Nombre Receptor',
        'iva',
        'ica',
        'ipc',
        'total',
        'estado',
        'grupo',
    ];
    public function __construct(
        public $company,
        public $resolution
    ) {
    }

    /**
     * @param Collection $collection
     * @throws Exception
     */
    public function collection(Collection $collection): void
    {
        try {
            $company    = $this->company;
            $resolution = $this->resolution;
            $next       = 0;
            $xmlService = new XmlExtractDataService();
            $software   = Software::query()->where('company_id', $company->id)->where('type_id', 1)->first();
            $documentReceptionListId    = [];
            DB::beginTransaction();
            foreach ($collection as $row)
            {
                $next++;
                if($next >= 1){
                    $cufe   = $row['cufecude'];
                    $dni    = $row['nit_receptor'];
                    if ($dni !== $company->dni) {
                        throw new Exception("El NIT del receptor no coincide con el NIT de la empresa. ".
                            "Nit receptor {$dni}, Nit empresa {$company->dni}", 400);
                    }
                    if(empty($cufe)) {
                        throw new Exception("El CUFE/CUDE no puede estar vacío", 400);
                    }
                    $group  = $row['grupo'];
                    if ($group !== 'Recibido') {
                        continue;
                    }
                    $table  = DB::table('document_receptions')
                    ->where('company_id', $company->id)
                    ->where('cufe_cude', $cufe)
                    ->first();
                    $trackId                = $row['cufecude'];
                    $xml                    = $xmlService->getXmlByDocumentKey($company, $software, $trackId);
                    $people                 = $xmlService->getAccountingSupplierPartyData($xml, $company);
                    if($table) {
                        DB::table('document_receptions')
                            ->where('company_id', $company->id)
                            ->where('cufe_cude', $trackId)
                            ->update([
                                'people_id' => $people->id,
                            ]);
                        continue;
                    }
                    if (in_array($row['tipo_de_documento'], $this->documentTypeList, true)) {
                        $documentId             = $this->documentId[$row['tipo_de_documento']];
                        $paymentId              = $xmlService->getPaymentMeansData($xml);
                        if ($paymentId === 1) {
                            continue;
                        }
                        $prefix                 = $row['prefijo'] ?? '';
                        $data   =[
                            'company_id'        => $company->id,
                            'people_id'         => $people->id,
                            'document_type_id'  => $documentId,
                            'payment_method_id' => $paymentId,
                            'cufe_cude'         => $trackId,
                            'folio'             => $prefix.$row['folio'],
                            'issue_date'        => DateFunctions::transformDate($row['fecha_emision']),
                            'total'             => $row['total'],
                            'document_origin'   => 'IMPORTED',
                        ];
                        $documentReception  = DocumentReception::create($data);
                        $documentReceptionListId[] = $documentReception->id;
                    }
                }
            }
            DB::commit();
            $documentReceptions = DocumentReception::query()
                ->whereIn('id', $documentReceptionListId)
                ->get();
            foreach ($documentReceptions as $documentReception) {
                EventDispatchService::dispatch($company, $documentReception, $resolution);
            }
        }catch (Exception $e) {
            DB::rollback();
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }
    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function(BeforeImport $event) {
                $headerRow = $event->getReader()->getActiveSheet()->getRowIterator(1)->current();
                $fileHeaders = [];

                foreach ($headerRow->getCellIterator() as $cell) {
                    $fileHeaders[] = strtolower($cell->getValue());
                }

                $expectedHeadersLowercase = array_map('strtolower', $this->expectedHeaders);

                $matchCount         = count(array_intersect($fileHeaders, $expectedHeadersLowercase));
                $totalExpected      = count($expectedHeadersLowercase);
                $percentageMatch    = ($matchCount / $totalExpected) * 100;

                if ($percentageMatch < 90) {
                    throw new Exception("Encabezados del archivo no coinciden con los esperados. Coincidencia: {$percentageMatch}%. Se requiere al menos un 90%.");
                }
            },
        ];
    }

}
