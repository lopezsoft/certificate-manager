<?php

declare(strict_types=1);

namespace App\Services;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Common\VerificationDigit;
use App\Models\Company;
use App\Modules\Company\CompanyQueries;
use App\Queries\CallExecute;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Servicio de negocio para operaciones de empresa.
 *
 * Centraliza la lógica que anteriormente residía como métodos estáticos
 * en CompanyModel y GeneraSettingsService, aplicando SRP e inyección
 * de dependencias.
 */
class CompanyService
{
    /**
     * Obtener la empresa autenticada o una empresa específica por UID.
     *
     * @throws Exception
     */
    public function read(?int $uid = null): JsonResponse
    {
        try {
            $company = $uid
                ? Company::where('id', $uid)->first()
                : CompanyQueries::getCompany();

            return HttpResponseMessages::getResponse([
                'dataRecords' => [
                    'data' => [$company],
                ],
                'total' => 1,
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    /**
     * Actualizar los datos de una empresa.
     *
     * @throws Exception
     */
    public function update(array $inputData, int $id): JsonResponse
    {
        try {
            $company = Company::query()->where('id', $id)->first();

            if (!$company) {
                throw new Exception('La empresa no existe.', 404);
            }

            $records = json_decode($inputData['records'] ?? '');

            if (!$records) {
                throw new Exception('La propiedad RECORDS no está definida.');
            }

            if (($records->country_id ?? null) === 45) {
                $dv = VerificationDigit::getDigit($records->dni);
            }
            $records->dv = $dv ?? $company->dv;

            if (isset($records->imgdata)) {
                $base64_str = substr($records->imgdata, strpos($records->imgdata, ",") + 1);

                if (!empty($base64_str)) {
                    $image = base64_decode($base64_str);
                    $imgName = $records->imgname;
                    $records->image = $this->putFile($company->id, $image, $imgName);
                }
            }

            $company->updateOnlyChanged(request(), (array) $records);

            return HttpResponseMessages::getResponse([
                'dataRecords' => $company,
                'message'     => 'Registro actualizado correctamente.',
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    /**
     * Listar los clientes (auxiliary_companies) de la empresa autenticada.
     *
     * @throws Exception
     */
    public function customers(?string $search = null): JsonResponse
    {
        $company = CompanyQueries::getCompany();

        $query = Company::select('companies.*', 'b.company_id')
            ->join('auxiliary_companies AS b', 'b.customer_id', '=', 'companies.id')
            ->where('b.company_id', $company->id)
            ->where('b.active', 1);

        if ($search) {
            $query = $query->where('companies.dni', 'like', "%$search%")
                ->orWhere('companies.company_name', 'like', "%$search%");
        }

        return HttpResponseMessages::getResponse([
            'dataRecords' => $query->paginate(),
        ]);
    }

    /**
     * Eliminar (desactivar) un cliente de la empresa autenticada.
     *
     * @throws Exception
     */
    public function deleteCustomer(int $id): JsonResponse
    {
        try {
            $company = CompanyQueries::getCompany();
            $customer = Company::query()->where('id', $id)->first();

            if (!$customer) {
                throw new Exception('El cliente no existe.', 404);
            }

            DB::table('auxiliary_companies')
                ->where('company_id', $company->id)
                ->where('customer_id', $customer->id)
                ->limit(1)
                ->update(['active' => 0]);

            return HttpResponseMessages::getResponse([
                'message' => 'Cliente eliminado correctamente.',
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    /**
     * Obtener la configuración general de la empresa autenticada.
     *
     * @throws Exception
     */
    public function getSetting(): JsonResponse
    {
        $company = CompanyQueries::getCompany();
        CallExecute::execute("sp_create_general_settings(?)", [$company->id]);

        return HttpResponseMessages::getResponse([
            'settings' => $company->settings,
        ]);
    }

    /**
     * Actualizar la configuración general de la empresa.
     *
     * @throws Exception
     */
    public function updateSetting(array $inputData): JsonResponse
    {
        try {
            CompanyQueries::getCompany();
            $records = json_decode($inputData['records'] ?? '');

            foreach ($records as $value) {
                DB::table('general_setting_companies')
                    ->where('id', $value->id)
                    ->limit(1)
                    ->update(['value' => $value->value]);
            }

            return HttpResponseMessages::getResponse([
                'message' => 'Configuración actualizada',
            ]);
        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    /**
     * Almacenar el logo de la empresa en disco.
     */
    private function putFile(int $companyId, string $data, string $imgName): string
    {
        $extension = pathinfo($imgName, PATHINFO_EXTENSION);
        $imageName = Str::uuid() . '.' . $extension;
        $path = "/companies/{$companyId}/logo/" . $imageName;
        Storage::disk('public')->put($path, $data);

        return "/storage{$path}";
    }
}
