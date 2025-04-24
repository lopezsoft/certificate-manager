<?php

namespace App\Modules\Documents\Invoice;

use App\Models\Taxes\Tax;
use App\Models\Taxes\TaxTotal;
use Exception;
use Illuminate\Support\Collection;

class Taxes
{
    /**
     * @throws Exception
     */
    public function getTaxTotals($request): Collection
    {
        try {
            $taxes      = $request->tax_totals;
            if (is_object($request->tax_totals)) {
                $taxes      = (array) $request->tax_totals;
            } elseif (is_string($request->tax_totals)) {
                $taxes      = json_decode($request->tax_totals, true);
            }
            $taxesList  = [];
            foreach ($taxes ?? [] as $taxTotal) {
                $tax    = Tax::findOrFail($taxTotal['tax_id']);
                $taxesList[]    = (object) [
                    'tax_id'            => $tax->id,
                    'percent'           => $taxTotal['percent'],
                    'tax_amount'        => $taxTotal['tax_amount'],
                    'taxable_amount'    => $taxTotal['taxable_amount'],
                    'code'              => $tax->code,
                ];
            }

            $taxCollection  = collect($taxesList);
            $taxCollection  = $taxCollection
                ->whereNotIn('code', ['05', '06', '07']);
            return ($taxCollection->count() > 1)
                ? $this->aggregateTaxTotals($taxCollection)
                : $this->simpleTaxTotals($taxCollection);
        }catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function getTaxRetentions($request): Collection
    {
        try {
            $taxes      = $request->tax_totals;
            if (is_object($request->tax_totals)) {
                $taxes      = (array) $request->tax_totals;
            } elseif (is_string($request->tax_totals)) {
                $taxes      = json_decode($request->tax_totals, true);
            }
            $taxesList  = [];
            foreach ($taxes ?? [] as $taxTotal) {
                $tax    = Tax::findOrFail($taxTotal['tax_id']);
                $taxesList[]    = (object) [
                    'tax_id'            => $tax->id,
                    'percent'           => $taxTotal['percent'],
                    'tax_amount'        => $taxTotal['tax_amount'],
                    'taxable_amount'    => $taxTotal['taxable_amount'],
                    'code'              => $tax->code,
                ];
            }

            $taxCollection  = collect($taxesList);
            $taxCollection  = $taxCollection
                ->whereIn('code', ['05', '06', '07']);
            return ($taxCollection->count() > 1)
                ? $this->aggregateTaxTotals($taxCollection)
                : $this->simpleTaxTotals($taxCollection);
        }catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    private function aggregateTaxTotals($taxCollection): Collection
    {
        $taxTotals = collect();
        // Group the taxes by their tax_id and aggregate the necessary values.
        $taxAggregates = $taxCollection->groupBy('tax_id')->map(function ($taxGroup) {
            return [
                'tax_id'   => $taxGroup->first()->tax_id,
                'amount'    => $taxGroup->count(),
                'tax_value' => $taxGroup->sum('tax_amount')
            ];
        });

        // Process the aggregated data.
        $taxAggregates->each(function ($tax) use ($taxCollection, $taxTotals) {
            $tax_id        = $tax['tax_id'];
            $matchingTaxes  = $taxCollection->where('tax_id', $tax_id);

            if ($tax['amount'] > 1) {
                $subtotal = collect();
                $matchingTaxes->each(function ($taxTotal) use ($subtotal) {
                    $subtotal->push(new TaxTotal($this->mapTaxTotal($taxTotal)));
                });
                $taxTotals->push(['tax_subtotal' => $subtotal, 'tax_amount' => $tax['tax_value'], 'tax_id' => $tax_id]);
            } else {
                $taxTotals->push(new TaxTotal($this->mapTaxTotal($matchingTaxes->first())));
            }
        });

        return $taxTotals;
    }

    private function simpleTaxTotals($taxCollection): Collection
    {
        return $taxCollection->map(function ($taxTotal) {
            return new TaxTotal($this->mapTaxTotal($taxTotal));
        });
    }

    private function mapTaxTotal($taxTotal): array
    {
        return [
            'tax_id'        => $taxTotal->tax_id,
            'tax_amount'    => $taxTotal->tax_amount,
            'taxable_amount'=> $taxTotal->taxable_amount,
            'percent'       => $taxTotal->percent,
        ];
    }
}
