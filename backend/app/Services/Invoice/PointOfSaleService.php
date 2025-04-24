<?php

namespace App\Services\Invoice;

use App\Models\Invoice\PointOfSale;

class PointOfSaleService
{
    public static function get($request): ?object
    {
        $pointOfSale = null;
        if (isset($request->point_of_sale)) {
            $pointOfSale = $request->point_of_sale;
            if (is_array($request->point_of_sale)) {
                $pointOfSale = array_merge($request->point_of_sale, []);
            } else if (is_string($request->point_of_sale)) {
                $pointOfSale = json_decode($request->point_of_sale, TRUE);
            }
            $pointOfSale = new PointOfSale($pointOfSale);
        }
        return $pointOfSale;
    }
}
