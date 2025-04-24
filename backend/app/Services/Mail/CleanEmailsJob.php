<?php

namespace App\Services\Mail;

use App\Models\Email\DocumentEmailJob;

class CleanEmailsJob
{
    /**
     * Libere el correo electrónico de la tabla de los jobs y actualice el estado del envío de correo electrónico
     * @param $company
     * @param $documentId
     * @param $typeDocumentId
     * @return void
     */
    public static function clean($company, $documentId, $typeDocumentId): void
    {
        $saleMaster = DocumentEmailJob::query()
            ->where('company_id', $company->id)
            ->where('document_id', $documentId)
            ->where('type_document_id', $typeDocumentId)
            ->first();
        if ($saleMaster) {
            DocumentEmailJob::query()
                ->where('company_id', $company->id)
                ->where('document_id', $documentId)
                ->where('type_document_id', $typeDocumentId)
                ->delete();
        }
    }

}
