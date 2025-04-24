<?php

namespace App\Jobs;

use App\Models\business\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigratedJSonDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    /**
     * Create a new job instance.
     */
    public function __construct(
        protected $jsonRecord
    )
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $jsonRecord = $this->jsonRecord;
            $jsonData   = (Object) $jsonRecord->jdata;
            $shipping   = DB::table('shipping_history')
                ->where('id', $jsonRecord->shipping_id)
                ->first();
            if ($shipping) {
                $jsonData->dni      = self::migrateCustomer($shipping, $jsonData);
                // Eliminamos la propiedad customer del objeto
                $jsonData->points   = $jsonData->customer->points ?? 0;
                unset($jsonData->customer);
                // Eliminamos la propiedad user del objeto
                unset($jsonData->user);
                // Eliminamos la propiedad typeDocument del objeto
                unset($jsonData->typeDocument);
                // Eliminamos la propiedad currency del objeto
                if (isset($jsonData->currency)) {
                    $currency = (Object) $jsonData->currency;
                    $jsonData->currencyId = $currency->id;
                    unset($jsonData->currency);
                } else {
                    $jsonData->currencyId = 272;
                }
                // Actualizamos el objeto payment
                $payments       = $jsonData->payment ?? [];
                $paymentsData   = [];
                foreach ($payments as $payment) {
                    $paymentsData[] = (object)[
                        'payment_method_id'    => $payment->paymentMethod->id ?? 1,
                        'means_payment_id'     => $payment->meansPayment->id ?? 10,
                        'value_paid'           => $payment->value_paid ?? 0,
                        'currency_id'          => $payment->currency_id ?? 272,
                        'payment_due_date'     => $payment->payment_due_date ?? null,
                    ];
                }
                $jsonData->payment  = $paymentsData;
                // Actualizamos las lines y eliminamos algunas propiedades del objeto
                $lines = $jsonData->lines ?? [];
                $linesData = [];
                foreach ($lines as $line) {
                    // Eliminamos la propiedad quantity_units del objeto line
                    unset($line['quantity_units']);
                    // Eliminamos la propiedad type_item_identifications del objeto line
                    unset($line['type_item_identifications']);
                    // Guardamos la información de la línea
                    $linesData[] = $line;
                }
                $jsonData->lines    = $linesData;

                // Actualizamos el campo jdata
                DB::table('json_data')
                    ->where('id', $jsonRecord->id)
                    ->update([
                        'jdata' => json_encode((Array) $jsonData),
                        'is_migrated' => 1,
                    ]);
            }
        }catch (\Exception $e) {
            Log::error($e->getMessage());
            throw new \Exception($e->getMessage());
        }
    }

    public static function migrateCustomer($shipping, $jsonData): string
    {
        if (isset($jsonData->customer)) {
            $person = $jsonData->customer->company;
        } else {
            $person = (Object) ['dni' => '222222222222'];
        }
        if ($person && !empty($person->dni) && !isFinalConsumer($person->dni)) {
            $customer = Customer::query()
                ->where('dni', $person->dni)
                ->first();

            $personName = formatNameToUpperCase($person->company_name ?? 'SIN NOMBRE');
            $address    = $person->address ?? null;
            $company    = $shipping->company;
            if ($customer) {
               $customer->updateOnlyChangedData([
                        'country_id' => $person->country_id ?? $customer->country_id,
                        'city_id' => $person->city_id ?? $customer->city_id,
                        'identity_document_id' => $person->identity_document_id ?? 3,
                        'type_organization_id' => $person->type_organization_id ?? 2,
                        'tax_regime_id' => $person->tax_regime_id ?? 2,
                        'tax_level_id' => $person->tax_level_id ?? 5,
                        'company_name' => $personName,
                        'mobile' => $person->mobile ?? $customer->mobile,
                        'phone' => $person->phone ?? $customer->phone,
                        'email' => $person->email ?? $customer->email,
                        'address' => $address ?? $customer->address,
                        'postal_code' => $person->postal_code ?? $customer->postal_code,
                        'city_name' => $person->city_name ?? $customer->city_name,
                    ]);
            } else {
                $customerId = DB::table('customers')
                    ->insertGetId([
                        'country_id' => $person->country_id ?? 45,
                        'city_id' => $person->city_id ?? $company->city_id,
                        'identity_document_id' => $person->identity_document_id ?? 3,
                        'type_organization_id' => $person->type_organization_id ?? 2,
                        'tax_regime_id' => $person->tax_regime_id ?? 2,
                        'tax_level_id' => $person->tax_level_id ?? 5,
                        'company_name' => $personName,
                        'dni' => $person->dni,
                        'mobile' => $person->mobile ?? null,
                        'email' => $person->email ?? null,
                        'address' => $address,
                        'city_name' => $person->city_name ?? null,
                        'postal_code' => $person->postal_code ?? null,
                        'dv' => $person->dv,
                    ]);
                $customer = DB::table('customers')
                    ->where('id', $customerId)
                    ->first();
            }

            $business = DB::table('business_customers')
                ->where('customer_id', $customer->id)
                ->where('company_id', $shipping->company_id)
                ->first();
            if (!$business) {
                DB::table('business_customers')
                    ->insert([
                        'customer_id' => $customer->id,
                        'company_id' => $shipping->company_id,
                        'active' => 1,
                    ]);
            }
        }

        return !empty($person->dni) ? $person->dni : '222222222222';
    }
}
