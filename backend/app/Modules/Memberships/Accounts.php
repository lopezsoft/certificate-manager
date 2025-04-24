<?php

namespace App\Modules\Memberships;

use App\Classes\CompanyClass;
use App\Common\HttpResponseMessages;
use App\Models\Memberships\CompanyMembership;
use App\Models\Memberships\MembershipHistory;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Accounts
{

    public function getMembership(Request $request): JsonResponse
    {
        try {
            $company    = CompanyClass::getCompany($request);
            return HttpResponseMessages::getResponse([
                'dataRecords' => [
                    'data' => [
                        $this->getMembershipData($company)
                    ],
                ],
            ]);
        }catch (Exception $e) {
            return HttpResponseMessages::getResponse([
                'message' => $e->getMessage()
            ]);
        }
    }
    /**
     * @throws \Exception
     */
    public function getActive(Request $request): JsonResponse
    {
        $response     = MembershipManager::getActive($request);
        $query        = $response->query;
        $company      = $response->company;

        return HttpResponseMessages::getResponse([
            'records'       => $query,
            'version'       => env('APP_VERSION'),
            'close_session' => env('CLOSE_SESSION', 0),
            'membership'    => $this->getMembershipData($company),
        ]);
    }
    /**
     * Activa la membresía Gratis
     * @throws \Exception
     */
    public function setActivate(Request $request): JsonResponse
    {
        try {
            $company            = CompanyClass::getCompany($request);
            $query              = DB::select("CALL sp_company_membership(?)", [$company->id]);
            $query              = $query[0];
            $query->isActive    = ($query->active == 1 && $query->days >= 0);

            if($query->isActive ) {
                throw new Exception('La membresía ya se encuentra activa');
            }
            DB::beginTransaction();
            $membership                     = $this->getMembershipData($company);
            $lock_date                      = Carbon::now()->addYear();
            $membership->active             = 1;
            $membership->membership_plan_id = 1; // Free
            $membership->lock_date          = $lock_date ;
            $membership->save();
            MembershipHistory::create([
                'company_id'            => $company->id,
                'membership_plan_id'    => $membership->membership_plan_id,
                'activation_date'       => Carbon::now(),
                'lock_date'             => $lock_date,
                'payment'               => 'ANNUAL',
                'payment_value'         => 0,
                'active'                => 1,
            ]);
            DB::commit();
            return HttpResponseMessages::getResponse([
                'message'       => 'La membresía se ha activado correctamente',
                'membership'    => $this->getMembershipData($company),
            ]);
        }catch (Exception $e) {
            DB::rollBack();
            return HttpResponseMessages::getResponse500([
                'message' => $e->getMessage()
            ]);
        }
    }

    protected function getMembershipData($company): object
    {
        return MembershipManager::getMembershipData($company);
    }
}
