<?php

namespace App\Http\Controllers;

use App\Common\HttpResponseMessages;
use App\Modules\Company\CompanyQueries;
use App\Queries\CallExecute;
use App\Services\GeneraSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Company\CompanyModel;

class CompanyController extends Controller
{
    public function updateSetting(Request $request): JsonResponse
    {
        return GeneraSettingsService::updateSetting($request);
    }
    /**
     * @throws \Exception
     */
    public function getSetting(): JsonResponse
    {
        $company    = CompanyQueries::getCompany();
        CallExecute::execute("sp_create_general_settings(?)", [$company->id]);
        return HttpResponseMessages::getResponse([
            'settings' => $company->settings,
        ]);
    }

    /**
     * @throws \Exception
     */
    public function deleteCustomer($id): JsonResponse
    {
        return CompanyModel::deleteCustomer($id);
    }
    /**
     * @throws \Exception
     */
    public function customers(Request $request): JsonResponse
    {
        return CompanyModel::customers($request);
    }

    /**
     * @throws \Exception
     */
    public function read(Request $request): JsonResponse
    {
        return CompanyModel::read($request);
    }
    public function update(Request $request, $id): JsonResponse
    {
        return CompanyModel::update($request, $id);
    }
}
