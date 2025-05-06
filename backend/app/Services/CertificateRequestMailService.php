<?php

namespace App\Services;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
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
                ->where('id', $id)
                ->first();
            if(!$query) {
                throw new Exception("No se ha encontrado la solicitud de certificado.", 400);
            }
            $messageData = (object) [
                'company'   => $company,
                'data'      => $query,
                'subject'   => 'Solicitud de certificado para facturación electrónica',
                'files'     => $query->files,
                'email_from'=> env('MAIL_FROM','soporte@matias.com.co'),
                'replyTo'   => env('REPLY_TO_MAIL', 'soporte@matias.com.co')
            ];
            DB::beginTransaction();
            $query->update([
                'request_status' => 'PROCESSING'
            ]);
            // Change history status
            ChangeHistory::create([
                'certificate_request_id'=>  $query->id,
                'status'                =>  'PROCESSING',
                'comments'              =>  $request->comments,
                'user_id'               =>  auth()->user()->id,
                'user_of_change'        =>  'MANAGER',
            ]);
            DB::commit();
            // Send mail
            $receiptMail = env('RECEIPT_EMAIL', 'gerencia@lopezsoft.net.co');
            Mail::to($receiptMail)->queue(new SendMail($messageData));
            $send = env('SEND_MAIL_TO_SUPPORT', false);
            if($send) {
                Mail::to('gerencia@lopezsoft.net.co')->queue(new SendMail($messageData));
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
