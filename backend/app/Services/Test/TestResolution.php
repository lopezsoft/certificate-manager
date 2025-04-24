<?php

namespace App\Services\Test;

use App\Models\Settings\Resolution;

class TestResolution
{
    public static function get($type_document_id, $company_id, $prefix = 'SETP', $from = 1, $up = 1000): Resolution
    {
        return new Resolution([
            'company_id'        => $company_id,
            'type_document_id'  => $type_document_id,
            'headerline1'       => 'RESOLUCIÓN DE PRUEBA',
            'headerline2'       => 'RESOLUCIÓN DE PRUEBA',
            'footline1'         => 'RESOLUCIÓN DE PRUEBA',
            'footline2'         => 'RESOLUCIÓN DE PRUEBA',
            'footline3'         => 'RESOLUCIÓN DE PRUEBA',
            'footline4'         => 'RESOLUCIÓN DE PRUEBA',
            'prefix'            => $prefix,
            'range_from'        => $from,
            'range_up'          => $up,
            'date_from'         => '2019-01-19',
            'date_up'           => '2030-01-19',
            'active'            => true,
            'resolution_number' => '18760000001',
            'technical_key'     => 'fc8eac422eba16e22ffd8c6f94b3f40a6e38162c',
        ]);
    }
}
