<?php

namespace App\Services\Xml;
use App\Models\business\Customer;
use App\Models\Location\Cities;
use Lopezsoft\UBL21dian\Templates\SOAP\GetXmlByDocumentKey;
use Lopezsoft\VerificationDigit\VerificationDigit;
use SimpleXMLElement;
use stdClass;

class XmlExtractDataService
{


    /**
     * @throws \Exception
     */
    public function getXmlByDocumentKey($company, $software, $trackId): string
    {
        $response = $this->getXml($company, $software, $trackId);
        return base64_decode($response->Envelope->Body->GetXmlByDocumentKeyResponse->GetXmlByDocumentKeyResult->XmlBytesBase64);
    }


    /**
     * @throws \Exception
     */
    public function getLegalMonetaryTotalData($xmlString): object
    {
        $xml = new SimpleXMLElement($xmlString);

        // Acceder al elemento cac:LegalMonetaryTotal, manejar los espacios de nombres
        $namespaces = $xml->getNamespaces(true);
        $legalMonetaryTotal = $xml->children($namespaces['cac'])->LegalMonetaryTotal;

        $data = new stdClass();

        if ($legalMonetaryTotal) {
            $cbc = $legalMonetaryTotal->children($namespaces['cbc']);
            $data->LineExtensionAmount = (string) $cbc->LineExtensionAmount;
            $data->CurrencyID_LineExtensionAmount = (string) $cbc->LineExtensionAmount->attributes()['currencyID'];
            $data->TaxExclusiveAmount = (string) $cbc->TaxExclusiveAmount;
            $data->TaxInclusiveAmount = (string) $cbc->TaxInclusiveAmount;
            $data->AllowanceTotalAmount = (string) $cbc->AllowanceTotalAmount;
            $data->ChargeTotalAmount = (string) $cbc->ChargeTotalAmount;
            $data->PayableAmount = (string) $cbc->PayableAmount;
        }

        return $data;
    }
    /**
     * @throws \Exception
     */
    public function getBasicInvoiceData($xmlString): object {
        $xml = new SimpleXMLElement($xmlString);

        // Acceder directamente a los elementos cbc, manejar los espacios de nombres
        $namespaces = $xml->getNamespaces(true);
        $cbc = $xml->children($namespaces['cbc']);

        $data = new stdClass();

        // Asumiendo que estos elementos están directamente bajo la raíz del XML
        $data->ProfileExecutionID = isset($cbc->ProfileExecutionID) ? (string) $cbc->ProfileExecutionID : null;
        $data->ID = isset($cbc->ID) ? (string) $cbc->ID : null;
        $data->IssueDate = isset($cbc->IssueDate) ? (string) $cbc->IssueDate : null;
        $data->IssueTime = isset($cbc->IssueTime) ? (string) $cbc->IssueTime : null;
        $data->InvoiceTypeCode = isset($cbc->InvoiceTypeCode) ? (string) $cbc->InvoiceTypeCode : null;

        return $data;
    }


    /**
     * @throws \Exception
     */
    public function getPaymentMeansData($xmlString): int {
        $xml = new SimpleXMLElement($xmlString);

        // Acceder al elemento cac:PaymentMeans, manejar los espacios de nombres
        $namespaces     = $xml->getNamespaces(true);
        $paymentMeans   = $xml->children($namespaces['cac'])->PaymentMeans;

        $data = 0;

        if ($paymentMeans) {
            $cbc    = $paymentMeans->children($namespaces['cbc']);
            $data   = (int) $cbc->ID;
        }

        return $data;
    }
    /**
     * Obtienes los datos del emisor del documento
     * @throws \Exception
     */
    public function getAccountingSupplierPartyData($xmlString, $currentCompany): object
    {
        $xml = new SimpleXMLElement($xmlString);

        // Acceder al elemento cac:AccountingSupplierParty, manejar los espacios de nombres
        $namespaces = $xml->getNamespaces(true);
        $accountingSupplierParty = $xml->children($namespaces['cac'])->AccountingSupplierParty;

        $data = new stdClass();

        if ($accountingSupplierParty) {
            $cbc = $accountingSupplierParty->children($namespaces['cbc']);
            $data->AdditionalAccountID = (string) $cbc->AdditionalAccountID;
            $party = $accountingSupplierParty->children($namespaces['cac'])->Party;

            if ($party) {
                $cac                = $party->children($namespaces['cac']);
                // cac:PartyName
                $partyName          = $cac->PartyName;
                $cbcParty           = $partyName->children($namespaces['cbc']);
                $data->PartyName    = (string) $cbcParty->Name;

                // cac:Address
                $address            = $cac->PhysicalLocation->Address;
                $cbcAddress         = $address->children($namespaces['cbc']);
                $data->CityId       = (string) $cbcAddress->ID;
                $data->PostalZone   = (string) $cbcAddress->PostalZone;
                // cac:AddressLine
                $cacAddressLine     = $address->children($namespaces['cac']);
                $cbcLine            = $cacAddressLine->AddressLine->children($namespaces['cbc']);
                $data->AddressLine  = (string) $cbcLine->Line;

                // cac:PartyTaxScheme
                $partyTaxScheme     = $cac->PartyTaxScheme;
                $cbcPartyTaxScheme  = $partyTaxScheme->children($namespaces['cbc']);
                $data->CompanyID    = (string) $cbcPartyTaxScheme->CompanyID;
                $data->TaxLevelCode = (string) $cbcPartyTaxScheme->TaxLevelCode;
                // cac:TaxScheme
                $taxScheme          = $partyTaxScheme->TaxScheme->children($namespaces['cbc']);
                $data->TaxSchemeID  = (string) $taxScheme->ID;

                //
                $contact            = $cac->Contact;
                $cbcContact         = $contact->children($namespaces['cbc']);
                $data->ContactName  = (string) $cbcContact->Name;
                $data->Telephone    = (string) $cbcContact->Telephone;
                $data->Email        = (string) $cbcContact->ElectronicMail;

                $dni        = trim($data->CompanyID);
                $company    = Customer::query()
                                ->select('id', 'dni')
                                ->where('dni', $dni)
                                ->without(['city', 'type_organization', 'tax_level', 'tax_regime'])
                                ->first();

                if (!$company) {
                    $city           = Cities::query()->where('city_code', $data->CityId)->first();
                    $customerAll    = [
                        'country_id'                    => 45,
                        'city_id'                       => $city?->id ?? 149,
                        'identity_document_id'          => 3,
                        'type_organization_id'          => $data->AdditionalAccountID,
                        'tax_regime_id'                 => 2,
                        'tax_level_id'                  => 5,
                        'company_name'                  => $data->PartyName,
                        'dni'                           => $dni,
                        'dv'                            => VerificationDigit::getDigit(intval($dni)),
                        'address'                       => $data->AddressLine,
                        'postal_code'                   => $data->PostalZone,
                        'mobile'                        => $data->Telephone,
                        'merchant_registration'         => "",
                        'phone'                         => $data->Telephone,
                        'email'                         => strtolower($data?->Email ?? ""),
                        'location'                      => "",
                    ];
                    $company    = Customer::create($customerAll);
                }
                $data       = $company;
            }
        }

        return $data;
    }

    /**
     * Obtienes los datos del cliente del documento
     * @throws \Exception
     */
    public function getAccountingCustomerPartyData($xmlString): stdClass
    {
        $xml = new SimpleXMLElement($xmlString);

        // Acceder al elemento cac:AccountingCustomerParty, manejar los espacios de nombres
        $namespaces = $xml->getNamespaces(true);
        $accountingCustomerParty = $xml->children($namespaces['cac'])->AccountingCustomerParty;

        $data = new stdClass();

        if ($accountingCustomerParty) {
            $cbc = $accountingCustomerParty->children($namespaces['cbc']);
            $data->AdditionalAccountID = (string) $cbc->AdditionalAccountID;
            $party = $accountingCustomerParty->children($namespaces['cac'])->Party;

            if ($party) {
                $cbc = $party->children($namespaces['cbc']);
                $data->PartyName = (string) $cbc->PartyName->Name;

                $cac = $party->children($namespaces['cac']);
                $address = $cac->PhysicalLocation->Address;
                $cbc = $address->children($namespaces['cbc']);
                $data->CityName = (string) $cbc->CityName;
                $data->CountrySubentity = (string) $cbc->CountrySubentity;
                $data->CountrySubentityCode = (string) $cbc->CountrySubentityCode;
                $data->AddressLine = (string) $address->AddressLine->Line;
                $data->IdentificationCode = (string) $cbc->Country->IdentificationCode;
                $data->CountryName = (string) $cbc->Country->Name;

                $partyTaxScheme = $cac->PartyTaxScheme;
                $cbc = $partyTaxScheme->children($namespaces['cbc']);
                $data->RegistrationName = (string) $cbc->RegistrationName;
                $data->CompanyID = (string) $cbc->CompanyID;
                $data->TaxLevelCode = (string) $cbc->TaxLevelCode;

                $taxScheme = $partyTaxScheme->TaxScheme->children($namespaces['cbc']);
                $data->TaxSchemeID = (string) $taxScheme->ID;
                $data->TaxSchemeName = (string) $taxScheme->Name;

                $partyLegalEntity = $cac->PartyLegalEntity;
                $cbc = $partyLegalEntity->children($namespaces['cbc']);
                $data->LegalEntityRegistrationName = (string) $cbc->RegistrationName;
                $data->LegalEntityCompanyID = (string) $cbc->CompanyID;

                $contact = $cac->Contact;
                $cbc = $contact->children($namespaces['cbc']);
                $data->ContactName = (string) $cbc->Name;
                $data->Telephone = (string) $cbc->Telephone;
                $data->Email = (string) $cbc->ElectronicMail;
            }
        }

        return $data;
    }
    private function getXml($company, $software, $trackId): object
    {
        $GetXmlByDocumentKey = new GetXmlByDocumentKey($company->certificate->path, $company->certificate->password);
        $GetXmlByDocumentKey->trackId = $trackId;
        $GetXmlByDocumentKey->To = $software->url;
        return $GetXmlByDocumentKey->signToSend()->getResponseToObject();
    }
}
