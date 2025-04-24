<?php

namespace App\Http\Controllers;

use App\Modules\Memberships\Accounts;
use App\Modules\Memberships\MembershipManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MembershipsController extends Controller
{
    /**
     * @throws \Exception
     */
    public function getConsumedDocuments(Request $request): JsonResponse
    {
        return MembershipManager::getConsumedDocuments($request);
    }
    /**
     * @throws \Exception
     */
    public function get(Request $request): JsonResponse
    {
        return (new Accounts)->getMembership($request);
    }
    /**
     * @throws \Exception
     */
    public function active(Request $request): JsonResponse
    {
        return (new Accounts)->getActive($request);
    }

    /**
     * @throws \Exception
     */
    public function setActivate(Request $request): JsonResponse
    {
        return (new Accounts)->setActivate($request);
    }
}
