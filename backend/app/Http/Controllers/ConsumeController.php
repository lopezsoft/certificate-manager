<?php

namespace App\Http\Controllers;

use App\Services\ConsumeService;

class ConsumeController extends Controller
{
    public function readByYear($year): \Illuminate\Http\JsonResponse
    {
        return (new ConsumeService())->readByYear($year);
    }

    public function readByMonth($year, $month): \Illuminate\Http\JsonResponse
    {
        return (new ConsumeService())->readByMonth($year, $month);
    }
}
