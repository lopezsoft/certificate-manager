<?php

namespace App\Models\Invoice;

use App\Core\CoreModel;
use App\Models\Taxes\TaxTotal;
use App\Models\Types\TypeItemIdentification;
use App\Models\QuantityUnit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends CoreModel
{
    /**
     * With default model.
     *
     * @var array
     */
    protected $with = [
        'quantity_units', 'type_item_identifications', 'reference_price',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'quantity_units_id', 'type_item_identifications_id', 'reference_price_id', 'invoiced_quantity',
        'line_extension_amount', 'free_of_charge_indicator', 'description', 'code', 'price_amount', 'base_quantity',
        'allowance_charges', 'tax_totals', 'discount', 'charge', 'reason', 'tax_amount', 'taxable_amount', 'percent',
        'total', 'mu', 'notes', 'invoicePeriod', 'tax_retentions', 'pack_size_numeric', 'brand_name', 'model_name',
        'sellers_item_identification', 'mandate', 'extra_data'
    ];
    public function quantity_units(): BelongsTo
    {
        return $this->belongsTo(QuantityUnit::class);
    }
    public function type_item_identifications(): BelongsTo
    {
        return $this->belongsTo(TypeItemIdentification::class);
    }
    public function reference_price(): BelongsTo
    {
        return $this->belongsTo(ReferencePrice::class);
    }
    public function getAllowanceChargesAttribute()
    {
        return $this->attributes['allowance_charges'] ?? [];
    }
    public function setAllowanceChargesAttribute(array $data = []): void
    {
        $allowanceCharges = collect();

        foreach ($data as $value) {
            $allowanceCharges->push(new AllowanceCharge($value));
        }

        // $allowanceCharges->push(new AllowanceCharge($data));

        $this->attributes['allowance_charges'] = $allowanceCharges;
    }

    public function getTaxTotalsAttribute()
    {
        return $this->attributes['tax_totals'] ?? [];
    }
    public function setTaxTotalsAttribute(array $data = []): void
    {
        $taxTotals = collect();

        foreach ($data as $value) {
            $taxTotals->push(new TaxTotal($value));
        }

        $this->attributes['tax_totals'] = $taxTotals;
    }
    public function getTaxRetentionsAttribute()
    {
        return $this->attributes['tax_retentions'] ?? [];
    }
    public function setTaxRetentionsAttribute(array $data = []): void
    {
        $taxRetentions = collect();

        foreach ($data as $value) {
            $taxRetentions->push(new TaxTotal($value));
        }

        $this->attributes['tax_retentions'] = $taxRetentions;
    }

    public function getFreeOfChargeIndicatorAttribute(): string
    {
        return ($this->attributes['free_of_charge_indicator']) ? 'true' : 'false';
    }
}
