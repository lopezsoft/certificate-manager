<?php

namespace App\Services;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Enums\CertificateRequestStatusEnum;
use App\Mail\SendMail;
use App\Models\CertificateRequest;
use App\Models\ChangeHistory;
use App\Modules\Company\CompanyQueries;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CertificateRequestMailService
{

    public function sendMail(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        try {
            $company    = CompanyQueries::getCompany();
            $query      = CertificateRequest::query()
                ->with(['files' => function ($query) {
                    $query->where('document_type', 'ATTACHED');
                }])
                ->whereHas("files", function ($query) {
                    $query->where('document_type', 'ATTACHED');
                })->where('id', $id)
                ->first();
            if(!$query) {
                throw new Exception("No se ha encontrado la solicitud de certificado.", 400);
            }
            $messageData = (object) [
                'company'   => $company,
                'data'      => $query,
                'subject'   => "Solicitud de certificado para facturación electrónica - {$query->dni}-{$query->dv}",
                'files'     => $query->files,
                'email_from'=> config('mail.from.address'),
                'replyTo'   => config('mail.reply_to.address', config('mail.from.address')),
            ];
            DB::beginTransaction();
            $query->update([
                'request_status' => CertificateRequestStatusEnum::PROCESSING->value,
            ]);
            // Change history status
            ChangeHistory::create([
                'certificate_request_id'=>  $query->id,
                'status'                =>  CertificateRequestStatusEnum::PROCESSING->value,
                'comments'              =>  $request->comments,
                'user_id'               =>  auth()->id(),
                'user_of_change'        =>  'MANAGER',
            ]);
            DB::commit();
            // Send mail
            $receiptMail = config('certificate.mail.receipt_email');
            Mail::to($receiptMail)->queue(new SendMail($messageData));

            $sendToSupport = config('certificate.mail.send_to_support', false);
            if($sendToSupport) {
                $supportEmail = config('certificate.mail.support_address');
                Mail::to($supportEmail)->queue(new SendMail($messageData));
            }
            return HttpResponseMessages::getResponse([
                'dataRecords' => [
                    'data' => [$query],
                ],
            ]);
        }catch (Exception $e) {
            DB::rollBack();
            return MessageExceptionResponse::response($e);
        }
    }
}
