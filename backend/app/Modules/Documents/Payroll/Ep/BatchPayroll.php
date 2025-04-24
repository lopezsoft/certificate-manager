<?php

namespace App\Modules\Documents\Payroll\Ep;

use App\Models\Language;
use App\Models\ShippingHistory;
use App\Models\Types\TypeCurrency;
use App\Modules\Company\CompanyQueries;
use App\Traits\DocumentTrait;
use App\Traits\ElectronicDocumentsTrait;
use App\Traits\MessagesTrait;
use App\Traits\PayrollTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Lopezsoft\UBL21dian\Templates\SOAP\SendBillAsync;
use Lopezsoft\UBL21dian\Templates\SOAP\SendNominaSync;
use Lopezsoft\UBL21dian\XAdES\SignPayroll;


class BatchPayroll
{
    use MessagesTrait, DocumentTrait, ElectronicDocumentsTrait;
    function processbatch(Request $request) {
        try {
            // User
            $user               = auth()->user();

            $company            = CompanyQueries::getCompany();
            $query              = DB::table('payroll')
                                    ->where('company_id', $company->id)
                                    ->where('status', '!=', 1)
                                    ->get();

                                    // Method async
                                    $async              = $request->async           ?? false;

            foreach ($query as $payroll){

                $language           = $request->language_id         ?? 842;
                $operation_type_id  = $request->operation_type_id   ?? 1;
                $type_document_id   = $request->type_document_id    ?? 13;

                $notes              = $request->notes             ?? null;

                $resolution_number  = $request->resolution_number   ?? null;

                $request->worker_code   = $payroll->dni;

                $worker_code        = $request->worker_code   ?? null;

                $novelty            = $request->novelty   ?? false;

                // User company
                $user->company      = $company;

                $request->type_id   = 2;
                $software           = CompanyQueries::getSoftware($request, $company);

                if($software->environme->code == 2){
                    return MessagesTrait::getResponse400([
                        'message' => "El Software está en modo pruebas, cambielo a producción"
                    ]);
                }

                $request->document_number   = $payroll->document_number;

                // Resolution
                $resolution         = $this->getResolution($request, $company, $type_document_id, $resolution_number);
                if (!$resolution) {
                    return MessagesTrait::getResponse400([
                        'message'   => 'El rango de nómina es incorrecto o no está creado.'
                    ]);
                }

                $document_number    = $this->getDocumentNumber($request, $resolution);
                $sequenceNumber     =  PayrollTrait::getSequenceNumber($request, $resolution);

                $request->generation_city_id    = $company->city_id;

                // Lugar de generación del XML
                $generationPlace    = PayrollTrait::getGenerationPlace($request);

                $company->dv        = MessagesTrait::digitVerificacion(intval($company->dni));

                // Type document
                $language           = Language::findOrFail($language);

                /**
                 * Periodo de la nómina
                 */
                $request->period    = [
                    "date_entry"            => $payroll->date_entry,
                    "departure_date"        => null,
                    "settlement_start_date" => $payroll->settlement_start_date,
                    "settlement_end_date"   => $payroll->settlement_end_date,
                    "time_worked"           => $payroll->worked_days,
                    "generation_date"       => $payroll->generation_date
                ];

                $period             = PayrollTrait::getPeriod($request);
                if(!$period) {
                    return MessagesTrait::getResponse400([
                        'message'           => 'El periodo de la nómina no es correcto.'
                    ]);
                }

                /**
                 * Información general de la nómina
                 */
                $request->general_information = [
                    "generation_date"   => $payroll->generation_date,
                    "generation_time"   => $payroll->generation_time,
                    "period_id"         => 5,
                    "currency_id"       => 272,
                    "trm"               => 0
                ];

                $generalInformation     = PayrollTrait::getGeneralInformation($request);
                if(!$generalInformation) {
                    return MessagesTrait::getResponse400([
                        'message'           => 'La información general de la nómina no es correcto.'
                    ]);
                }


                /**
                 * Información del empleado
                 */
                $request->employee  = [
                    "worker_type"                  => 1,
                    "worker_subtype"               => 1,
                    "high_risk_pension"            => 'false',
                    "identity_document"            => $payroll->identity_document_id ?? 1,
                    "document_number"              => $payroll->dni ?? "",
                    "first_surname"                => $payroll->first_surname ?? "",
                    "second_surname"               => strlen($payroll->second_surname) > 1 ? $payroll->second_surname : null,
                    "first_name"                   => $payroll->first_name ?? "",
                    "other_names"                  => strlen($payroll->other_names) > 1 ? $payroll->other_names : null,
                    "working_country"              => $payroll->working_country_id ?? 45,
                    "work_city"                    => $payroll->work_city_id ?? 149,
                    "work_address"                 => $payroll->address ?? "",
                    "integral_salary"              => $payroll->integral_salary ?? "false",
                    "contract_type"                => $payroll->contract_type_id ?? 1,
                    "salary"                       => $payroll->salary ?? 0,
                    "worker_code"                  => $payroll->dni ?? null,
                ];

                $employee           = PayrollTrait::getEmployee($request);

                if(!$employee) {
                    return MessagesTrait::getResponse400([
                        'message'           => 'La información del empleado no es correcta.'
                    ]);
                }

                // Type document
                $typeDocument       = $resolution->type_document;

                // Date time
                $date               = $payroll->pay_day ?? $this->getDate($request);
                $time               = $this->getTime($request);

                $request->payment   = [
                    "payment_method_id" => 1,
                    "means_payment_id"  => $payroll->bank ? 31 : 10,
                    "bank"              => $payroll->bank ?? "",
                    "account_type"      => $payroll->account_type ?? "",
                    "account_number"    => $payroll->account_number ?? ""
                ];
                // Payment form
                $paymentForm        = PayrollTrait::getPayment($request);

                // currency
                $currency           = TypeCurrency::findOrFail($paymentForm->currency_id ?? 272);

                // Devengado
                $extraHours         = json_decode($payroll->extra_hours);

                $request->earn      = [
                        "basic" => [
                            "worked_days" => $payroll->worked_days,
                            "salary_worked" => $payroll->salary_worked
                        ],
                        "transport" => [
                            "transportation_assistance" => $payroll->transportation_assistance,
                            "viatic_maintenance" => "0.0",
                            "viatic_non_salary_maintenance" => "0.0"
                        ],
                        "HEDs" => [
                            (array)$extraHours->HEDs
                        ],
                        "HENs" => [
                            (array)$extraHours->HENs
                        ],
                        "HRNs" => [
                            (array)$extraHours->HRNs
                        ],
                        "HEDDFs" => [
                            (array)$extraHours->HEDDFs
                        ],
                        "HRDDFs" => [
                            (array)$extraHours->HRDDFs
                        ],
                        "HENDFs" => [
                            (array)$extraHours->HENDFs
                        ],
                        "HRNDFs" => [
                            (array)$extraHours->HRNDFs
                        ],
                ];
                $earn               = Earn::getEarn($request);
                if(!$earn) {
                    return MessagesTrait::getResponse400([
                        'message'           => 'La información del devengado no es correcta.'
                    ]);
                }

                // deductions
                $request->deductions    = [
                    "health" => [
                        "percentage"    => "4",
                        "deduction"     => "43700"
                    ],
                    "pension_fund" => [
                        "percentage"    => "4",
                        "deduction"     => "43700"
                    ]
                ];

                $deductions         = Deductions::getDeductions($request);

                if(!$deductions) {
                    return MessagesTrait::getResponse400([
                        'message'           => 'La información de las deducciones no es correcta.'
                    ]);
                }
                $rounding                      = null;
                if(isset($request->rounding)){
                    $rounding           = $request->rounding;
                }
                $total_earned       = number_format($payroll->total_earned, 2, '.', '')   ?? 0.00;
                $deductiones_total  = number_format($payroll->deductiones_total, 2, '.', '') ?? 0.00;
                $total_voucher      = number_format($payroll->total_voucher, 2, '.', '') ?? 0.00;

                $stringCune         =   $sequenceNumber->number.$generalInformation->generation_date.$generalInformation->generation_time."-05:00".
                                        $total_earned.$deductiones_total.$total_voucher.$company->dni.$employee->document_number.
                                        $typeDocument->code.$software->pin.$software->environme->code;
                $cune               = hash('sha384', $stringCune);

                // Create XML
                $invoice = $this->createXML(compact(
                    'user',
                    'company',
                    'employee',
                    'resolution',
                    'period',
                    'paymentForm',
                    'notes',
                    'typeDocument',
                    'earn',
                    'deductions',
                    'date',
                    'time',
                    'sequenceNumber',
                    'generalInformation',
                    'currency',
                    'language',
                    'generationPlace',
                    'software',
                    'cune',
                    'rounding',
                    'total_earned',
                    'deductiones_total',
                    'total_voucher'
                ));

                // Signature XML
                $signPayroll = new SignPayroll($company->certificate->path, $company->certificate->password);
                $signPayroll->softwareID    = $software->identification;
                $signPayroll->pin           = $software->pin;

                if ($async) {
                    $sendBill               = new SendBillASync($company->certificate->path, $company->certificate->password);
                    $ShippingMethod         = 'SendBillASync';
                } else {
                    $sendBill               = new SendNominaSync($company->certificate->path, $company->certificate->password);
                    $ShippingMethod         = 'SendNominaSync';
                }

                $sendBill->To           = $software->url;
                $sendBill->fileName     = "{$document_number}.xml";
                $sendBill->contentFile  = $this->zipBase64($company, $resolution, $signPayroll->sign($invoice), $document_number);

                $response           = $sendBill->signToSend()->getResponseToObject();
                $qr                 = NULL;

                $base64     = base64_encode($this->getZIP());
                $XmlBase64  = base64_encode($this->getXML());
                $XmlName    = $this->getNameXML();
                $isValid    = true;

                if ($async) {
                    $zipkey         = $response->Envelope->Body->SendBillAsyncResponse->SendBillAsyncResult->ZipKey;
                } else {
                    $dianRespon     = $response->Envelope->Body->SendNominaSyncResponse->SendNominaSyncResult;
                    $zipkey         = $dianRespon->XmlDocumentKey;
                    // $XmlBase64Bytes = $dianRespon->XmlBase64Bytes;
                }

                $response_save  = json_encode($response);

                //Guarda la información del documento para la representación grafica.
                $jsonData   = json_encode([
                    'user'                  => $user,
                    'company'               => $company,
                    'employee'              => $employee,
                    'resolution'            => $resolution,
                    'period'                => $period,
                    'paymentForm'           => $paymentForm,
                    'notes'                 => $notes,
                    'typeDocument'          => $typeDocument,
                    'earn'                  => $earn,
                    'deductions'            => $deductions,
                    'date'                  => $date,
                    'time'                  => $time,
                    'sequenceNumber'        => $sequenceNumber,
                    'generalInformation'    => $generalInformation,
                    'currency'              => $currency,
                    'language'              => $language,
                    'generationPlace'       => $generationPlace,
                    'software'              => $software,
                    'cune'                  => $cune,
                    'rounding'              => $rounding,
                    'total_earned'          => $total_earned,
                    'deductiones_total'     => $deductiones_total,
                    'total_voucher'         => $total_voucher
                ]);

                ShippingHistory::insert(
                    [
                        'user_id'           => $user->id,
                        'company_id'        => $company->id,
                        'type_document_id'  => $type_document_id,
                        'operation_type_id' => $operation_type_id,
                        'invoice_number'    => $document_number,
                        'resolution_number' => $resolution_number,
                        'response_dian'     => $response_save,
                        'shipping_method'   => $ShippingMethod,
                        'zipkey'            => ($software->environme->code == 2) ? $zipkey : null,
                        'XmlDocumentKey'    => ($software->environme->code == 1) ? $zipkey : null,
                        'XmlDocumentName'   => ($XmlName) ? json_encode($XmlName) : null,
                        'customerData'      => ($employee) ? json_encode($employee) : null,
                        'jsonData'          => $jsonData,
                        'XmlBase64Bytes'    => $XmlBase64,
                        'ZipBase64Bytes'    => $base64
                    ]
                );

                $isValid        = ($dianRespon->IsValid == "true") ? TRUE : FALSE;

                $qr = "https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey=".$zipkey;

                $response   = json_decode($payroll->response_dian);

                if(!$response){
                    $response   = [];
                }else {
                    $response   = (array)$response;
                }
                if(!isset($response['response'])){
                    $response['response'] = [];
                }

                $response['response'][] = $response_save;

                $data   = [
                    'response_dian' => $response,
                    'cune'          => $zipkey,
                    'xml'           => $XmlBase64,
                    'qr'            => $qr,
                    'status'        => $isValid ? 1 : 2
                ];

                DB::table('payroll')
                    ->where('id', $payroll->id)
                    ->limit(1)
                    ->update($data);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return MessagesTrait::getResponse500($e->getMessage());
        }
    }

    static function read(Request $request) {
        $company    = CompanyQueries::getCompany();
        $query      = DB::table('payroll')->where('company_id', $company->id)->get();
        return MessagesTrait::getResponse201([
            'records'   => $query,
            'total'     => count($query),
        ]);
    }

    static function import(Request $request) {
        try {
            $company    = CompanyQueries::getCompany();
            $base64_str = substr($request->fileData, strpos($request->fileData, ",") + 1);

            //decode base64 string
            $fileData   = base64_decode($base64_str);
            $fileName   = $request->fileName;

            $date        = date('Ymdhis');
            $path       = "companies/{$company->id}/imports/{$date}/{$fileName}";
            Storage::disk('public')->put($path, $fileData);
            $path       = storage_path("app/public/{$path}");

            $reader     = new Xlsx();
            $reader->setReadDataOnly(TRUE);

            $spreadsheet = $reader->load($path);
            $count      = $spreadsheet->getSheetCount();

            if (!$count == 2) {
                return MessagesTrait::getResponse500("El número de hojas del archivo es incorrecto");
            }

            // $payroll             = $spreadsheet->getSheet(0);
            $payroll            = $spreadsheet->getSheet(1);

            $highestRowHeader   = $payroll->getHighestRow();

            if ($highestRowHeader > 215 ) {
                return MessagesTrait::getResponse500("El número filas de la hoja del encabezado es incorrecto");
            }
            $highestRowDetail   = $payroll->getHighestRow() + 1;

            if ($highestRowHeader > $highestRowDetail || $highestRowDetail <= 1) {
                return MessagesTrait::getResponse500("El número filas de la hoja del detalle es incorrecto");
            }


            DB::beginTransaction();

            for ($ih = 6; $ih <= $highestRowHeader; $ih++) {
                // Encabezado del documento
                $number             = $payroll->getCell("A{$ih}")->getValue();
                $document           = $payroll->getCell("B{$ih}")->getValue() ?? '';
                $document_number    = 1;

                if (strlen($document) > 3) {
                    $extraHours = [];
                    $amount     =  $payroll->getCell("M{$ih}")->getValue() ?? 0;
                    $payment    =  $payroll->getCell("N{$ih}")->getValue() ?? 0;

                    if($amount >= 0 &&  $payment >= 0) {
                        $extraHours["HEDs"] = (object) [
                            "amount"        => $amount,
                            "payment"       => $payment,
                            "percentage"    => "25.00"
                        ];
                    }

                    $amount     =  $payroll->getCell("O{$ih}")->getValue() ?? 0;
                    $payment    =  $payroll->getCell("P{$ih}")->getValue() ?? 0;

                    if($amount >= 0 &&  $payment >= 0) {
                        $extraHours["HENs"] = (object) [
                            "amount"        => $amount,
                            "payment"       => $payment,
                            "percentage"    => "75.00"
                        ];
                    }

                    $amount     =  $payroll->getCell("Q{$ih}")->getValue() ?? 0;
                    $payment    =  $payroll->getCell("R{$ih}")->getValue() ?? 0;

                    if($amount >= 0 &&  $payment >= 0) {
                        $extraHours["HRNs"] = (object) [
                            "amount"        => $amount,
                            "payment"       => $payment,
                            "percentage"    => "35.00"
                        ];
                    }

                    $amount     =  $payroll->getCell("S{$ih}")->getValue() ?? 0;
                    $payment    =  $payroll->getCell("T{$ih}")->getValue() ?? 0;

                    if($amount >= 0 &&  $payment >= 0) {
                        $extraHours["HEDDFs"] = (object) [
                            "amount"        => $amount,
                            "payment"       => $payment,
                            "percentage"    => "100.00"
                        ];
                    }


                    $amount     =  $payroll->getCell("U{$ih}")->getValue() ?? 0;
                    $payment    =  $payroll->getCell("V{$ih}")->getValue() ?? 0;

                    if($amount >= 0 &&  $payment >= 0) {
                        $extraHours["HRDDFs"] = (object) [
                            "amount"        => $amount,
                            "payment"       => $payment,
                            "percentage"    => "75.00"
                        ];;
                    }

                    $amount     =  $payroll->getCell("W{$ih}")->getValue() ?? 0;
                    $payment    =  $payroll->getCell("X{$ih}")->getValue() ?? 0;

                    if($amount >= 0 &&  $payment >= 0) {
                        $extraHours["HENDFs"] = (object) [
                            "amount"        => $amount,
                            "payment"       => $payment,
                            "percentage"    => "150.00"
                        ];;
                    }

                    $amount     =  $payroll->getCell("Y{$ih}")->getValue() ?? 0;
                    $payment    =  $payroll->getCell("Z{$ih}")->getValue() ?? 0;

                    if($amount >= 0 &&  $payment >= 0) {
                        $extraHours["HRNDFs"] = (object) [
                            "amount"        => $amount,
                            "payment"       => $payment,
                            "percentage"    => "110.00"
                        ];;
                    }


                    $health = json_encode((object)[
                            "percentage"    => $payroll->getCell("AD{$ih}")->getValue() ?? 0,
                            "deduction"     => $payroll->getCell("AE{$ih}")->getValue() ?? 0
                    ]);

                    $pension_fund = json_encode((object)[
                            "percentage"    => $payroll->getCell("AF{$ih}")->getValue() ?? 0,
                            "deduction"     => $payroll->getCell("AG{$ih}")->getValue() ?? 0
                    ]);

                    $data   = [
                        'company_id'                => $company->id,
                        'employee_number'           => $number,
                        'document_number'           => $document_number,
                        'dni'                       => $document,
                        'first_name'                => trim($payroll->getCell("C{$ih}")->getValue()) ?? NULL,
                        'other_names'               => trim($payroll->getCell("D{$ih}")->getValue()) ?? NULL,
                        'first_surname'             => trim($payroll->getCell("E{$ih}")->getValue()) ?? NULL,
                        'second_surname'            => trim($payroll->getCell("F{$ih}")->getValue()) ?? NULL,
                        'date_entry'                => self::getRealDateXls($payroll->getCell("G{$ih}")->getValue()),
                        'address'                   => trim($payroll->getCell("H{$ih}")->getValue()) ?? null,
                        'city_id'                   => $payroll->getCell("I{$ih}")->getValue() ?? 149,
                        'salary'                    => $payroll->getCell("J{$ih}")->getValue() ?? 0,
                        'worked_days'               => $payroll->getCell("K{$ih}")->getValue() ?? 30,
                        'salary_worked'             => $payroll->getCell("L{$ih}")->getValue() ?? 0,
                        'extra_hours'               => json_encode($extraHours),
                        'bonus'                     => $payroll->getCell("AA{$ih}")->getValue() ?? 0,
                        'total_earned'              => $payroll->getCell("AB{$ih}")->getValue() ?? 0,
                        'transportation_assistance' => $payroll->getCell("AC{$ih}")->getValue() ?? 0,
                        'health'                    => $health,
                        'pension_fund'              => $pension_fund,
                        'deductiones_total'         => $payroll->getCell("AH{$ih}")->getValue() ?? 0,
                        'settlement_start_date'     => self::getRealDateXls($payroll->getCell("AI{$ih}")->getValue()),
                        'settlement_end_date'       => self::getRealDateXls($payroll->getCell("AJ{$ih}")->getValue()),
                        'generation_date'           => self::getRealDateXls($payroll->getCell("AK{$ih}")->getValue()),
                        'generation_time'           => self::getRealTimeXls($payroll->getCell("AL{$ih}")->getValue()),
                        'pay_day'                   => self::getRealDateXls($payroll->getCell("AM{$ih}")->getValue()),
                        'total_voucher'             => $payroll->getCell("AN{$ih}")->getValue() ?? 0,
                        'bank'                      => $payroll->getCell("AO{$ih}")->getValue() ?? null,
                        'account_number'            => $payroll->getCell("AP{$ih}")->getValue() ?? null,
                        'account_type'              => $payroll->getCell("AQ{$ih}")->getValue() ?? null,
                        'document_number'           => $payroll->getCell("AR{$ih}")->getValue() ?? null,
                        'notes'                     => $payroll->getCell("AS{$ih}")->getValue() ?? null,
                    ];

                    DB::table('payroll')->insert($data);

                }
            }

            DB::commit();
            return response()->json([
                'success'   => true,
                'path'      => $path,
            ]);
        } catch (Exception $e) {
            DB::rollback();
            return MessagesTrait::getResponse500($e->getMessage());
        }
    }
    static function download() {
        return response()->json([
            'success'   => TRUE,
            'pathFile'  => 'assets/templates/template-nomina.xlsx'
        ]);
    }
}
