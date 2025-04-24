<?php

namespace App\Services;

use App\DTOs\ShowroomInformation;

class ShowroomService
{
    public static function processShowroomInfo($request): ?ShowroomInformation
    {
        $showroomInfo = null;
        if (isset($request->showroomInformation)) {
            $showroomInfoData = $request->showroomInformation;
            // Si showroomInformation es un string JSON, decodificarlo
            if (is_string($showroomInfoData)) {
                $showroomInfoData = json_decode($showroomInfoData, true);
            }
            // Validar que dataShow sea un array
            if (isset($showroomInfoData['dataShow']) && is_string($showroomInfoData['dataShow'])) {
                $showroomInfoData['dataShow'] = json_decode($showroomInfoData['dataShow'], true);
            }
            // Crear el objeto ShowroomInformation usando la data procesada
            $showroomInfo = new ShowroomInformation($showroomInfoData);
        }
        return $showroomInfo;
    }
}
