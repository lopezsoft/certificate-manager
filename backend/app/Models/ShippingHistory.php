<?php

namespace App\Models;

use App\Core\CoreModel;
use App\Models\business\Customer;
use App\Modules\Documents\Invoice\Customer as CustomerInvoice;
use App\Models\Invoice\MeansPayment;
use App\Models\Invoice\PaymentMethod;
use App\Models\Settings\Resolution;
use App\Models\Types\TypeCurrency;
use App\Models\Types\TypeDocument;
use App\Models\Types\TypeOperation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static create(array $array)
 * @method static find($id)
 * @property mixed $company_id
 * @property mixed $user_id
 * @property mixed $type_document_id
 * @property mixed $operation_type_id
 * @property mixed $document_number
 * @property mixed $resolution_id
 * @property mixed $XmlBase64Bytes
 * @property mixed $XmlDocumentKey
 * @property mixed $XmlDocumentName
 * @property mixed $jsonPath
 * @property mixed $customerPath
 * @property mixed $qrPath
 * @property mixed $pdfPath
 * @property mixed $attachedPath
 * @property mixed $xmlPath
 * @property mixed $is_valid
 * @property mixed $zipPath
 * @property mixed|null $order_number
 * @property mixed|null $invoice_date
 * @property mixed $payable_amount
 * @property int|mixed $send_to_queue
 *
 */
class ShippingHistory extends CoreModel
{
    //
    public $table   = 'shipping_history';
    public $timestamps = true;
    protected $fillable = [
        'user_id', 'company_id', 'type_document_id', 'operation_type_id', 'document_number',
        'resolution_id', 'shipping_method', 'XmlBase64Bytes', 'XmlDocumentKey',
        'XmlDocumentName', 'jsonPath', 'customerPath', 'qrPath',
        'pdfPath', 'attachedPath', 'xmlPath', 'is_valid', 'zipPath', 'attachedZipPath', 'order_number',
        'payable_amount', 'invoice_date', 'status', 'uuid', 'send_to_queue'
    ];
    protected $casts = [
        'invoice_date' => 'date:d-m-Y',
    ];
    protected $appends = [
        'createdAt', 'updatedAt', 'jsonData'
    ];
    protected $hidden = [
        'company_id','user_id',  'created_at', 'updated_at', 'jsonPath', 'customerPath',
    ];

    /**
     * @throws \Exception
     */
    public function getJsonDataAttribute(): ?object
    {
       $jsonData = $this->jJsonData()->first();
       if (!$jsonData) {
           return null;
       }
       $jsonData    = (Object) $jsonData->jdata;
       $dni         = $jsonData->dni ?? null;
        $customerData = $jsonData->customer ?? null;
        if ($customerData) {
            $companyData = is_object($customerData) ? $customerData->company : ($customerData['company'] ?? null);
            if ($companyData) {
                $customer = CustomerInvoice::getCustomerData((array) $companyData);
                $jsonData->customer = $customer->company;
                $jsonData->dni = $customer->company->dni;
            }
        } elseif ($dni) {
            $jsonData->customer = Customer::where('dni', $dni)
                ->withOut(['taxes', 'country', 'identity_document', 'type_organization', 'city', 'tax_level', 'tax_regime'])
                ->first();
        }
       if(!property_exists($jsonData, 'currencyId') && !property_exists($jsonData, 'currency')) {
           $jsonData->currencyId = $jsonData->currency->id ?? 272;
       }
       if(isset($jsonData->currencyId)){
           $jsonData->currency = TypeCurrency::query()->where('id', $jsonData->currencyId)->first();
       }
       $payments = $jsonData->payment ?? [];
       $paymentsList = [];
       $countId = 0;
       foreach ($payments as $payment) {
           if(is_array($payment))
               $payment    = (object) array_merge($payment, []);
           if (isset($payment->paymentMethod) && is_array($payment->paymentMethod))
               $payment->paymentMethod = (object) array_merge($payment->paymentMethod, []);
           if (isset($payment->meansPayment) && is_array($payment->meansPayment))
               $payment->meansPayment = (object) array_merge($payment->meansPayment, []);

           $payment_method_id = isset($payment->paymentMethod) ? $payment->paymentMethod->payment_id : $payment->payment_method_id;
           $means_payment_id = isset($payment->meansPayment) ? $payment->meansPayment->id : $payment->means_payment_id;
           $paymentsList[] = (object) [
               'id'                => $countId,
               'paymentMethod'     => PaymentMethod::findOrFail($payment_method_id),
               'meansPayment'      => MeansPayment::findOrFail($means_payment_id),
               'payment_due_date'  => $payment->payment_due_date ?? null,
               'currency_id'       => $payment->currency_id ?? $jsonData->currencyId ?? 272,
               'value_paid'        => $payment->value_paid ?? 0,
           ];
       }
       $jsonData->payment = $paymentsList;
       return $jsonData;
    }
    public function getCreatedAtAttribute(): string
    {
        return Date('d-m-Y h:i:s a', strtotime($this->attributes['created_at']));
    }
    public function getUpdatedAtAttribute(): string
    {
        return Date('d-m-Y h:i:s a', strtotime($this->attributes['updated_at']));
    }
    public function operationType(): BelongsTo
    {
        return $this->belongsTo(TypeOperation::class, 'operation_type_id');
    }
    public function document(): BelongsTo
    {
        return $this->belongsTo(TypeDocument::class, 'type_document_id');
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function resolution(): BelongsTo
    {
        return $this->belongsTo(Resolution::class, 'resolution_id');
    }
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    // Relación con JsonData
    public function jJsonData(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(JsonData::class, 'shipping_id');
    }
}
