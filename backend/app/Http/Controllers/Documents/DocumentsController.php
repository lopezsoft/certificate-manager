<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Modules\Documents\AttachedDocument;
use App\Modules\Documents\DocumentPdf;
use App\Modules\Documents\Documents;
use App\Modules\Documents\DocumentXml;
use App\Modules\Documents\RunStatusProcessor;
use App\Modules\Documents\SendingEmail;
use App\Modules\Documents\SendingEmailDocuments;
use Illuminate\Http\Request;

class DocumentsController extends Controller
{
    //
    public function sendMailDocuments(Request $request) {
        return Documents::processDocuments($request, null, new SendingEmailDocuments());
    }
    public function sendMail(Request $request, $trackId) {
        return Documents::processDocuments($request, $trackId, new SendingEmail());
    }
    public function getPdf(Request $request, $trackId) {
        return Documents::processDocuments($request, $trackId, new DocumentPdf());
    }
    public function getXmlRequest(Request $request, $trackId) {
        return Documents::processDocuments($request, $trackId, new DocumentXml());
    }
    public function getAttached(Request $request, $trackId) {
        return RunStatusProcessor::execute($request, $trackId, new AttachedDocument());
    }
}
