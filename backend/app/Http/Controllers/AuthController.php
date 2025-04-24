<?php

namespace App\Http\Controllers;

use App\Common\HttpResponseMessages;
use App\Models\User;
use App\Modules\Auth\Login;
use App\Queries\UpdateTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
class AuthController extends Controller
{

    public function types(): JsonResponse
    {
        return Login::userTypes();
    }

    public function login(Request $request): JsonResponse
    {
        return Login::login($request);
    }

    public function logout(Request $request): JsonResponse
    {
        return Login::logout($request);
    }
    public function user(Request $request): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            'dataRecords'   => [
                'data'  => [$request->user()],
            ],
        ]);
    }

    public function updateUser(Request $request, $id): JsonResponse
    {
        $table      = 'users';
        $user       = User::findOrFail($id);
        $records    = json_decode($request->input('records'));
        if (isset($user->pasw)) {
            if (strlen($user->pasw) > 6) {
                $records->password  = bcrypt($user->pasw);
            }
        }
        if (isset($records->imgdata)) {
            //get the base-64 from data
            $base64_str = substr($records->imgdata, strpos($records->imgdata, ",") + 1);
            if (strlen($base64_str)  > 0) {
                //decode base64 string
                $image              = base64_decode($base64_str);
                $imgName            = $records->imgname;
                $records->avatar    = self::putFile($user->id, $image, $imgName);
            }
        }
        $user       = User::findOrFail($id);
        UpdateTable::update($request, $records, $table);
        return HttpResponseMessages::getResponse([
            'user'   => $user,
        ]);
    }

    private static function putFile($user_id, $data, $imgName): string
    {
        $path           = "users/{$user_id}/profile/{$imgName}";
        $disk           = Storage::disk('public');
        $disk->put($path, $data);
        return $path;
    }
}
