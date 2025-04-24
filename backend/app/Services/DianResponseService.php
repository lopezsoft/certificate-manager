<?php

namespace App\Services;

class DianResponseService
{
    public static function getResponse($response)
    {
        $body               = $response->Envelope->Body;
        if (isset($body->GetStatusZipResponse)) {
            $dianResponse         = $body->GetStatusZipResponse->GetStatusZipResult->DianResponse;
        } else if (isset($body->GetStatusResponse)) {
            $dianResponse         = $body->GetStatusResponse->GetStatusResult;
        } else if (isset($body->SendNominaSyncResponse)) {
            $dianResponse         = $body->SendNominaSyncResponse->SendNominaSyncResult;
        } else if (isset($body->SendTestSetAsyncResponse)) {
            $dianResponse         = $body->SendTestSetAsyncResponse->SendTestSetAsyncResult;
        } else if (isset($body->GetStatusEventResponse)) {
            $dianResponse         = $body->GetStatusEventResponse->GetStatusEventResult;
        } else if (isset($body->SendEventUpdateStatusResponse)) {
            $dianResponse         = $body->SendEventUpdateStatusResponse->SendEventUpdateStatusResult;
        } else {
            $dianResponse         = $body->SendBillSyncResponse->SendBillSyncResult;
        }
        return $dianResponse;
    }
}
