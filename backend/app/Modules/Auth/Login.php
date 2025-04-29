<?php

namespace App\Modules\Auth;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Models\Company;
use App\Models\user\UserType;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Login
{
    public static function userTypes(): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            'records'                   => UserType::get()
        ]);
    }
    public static function login(Request $request): JsonResponse
    {

        $request->validate([
            'email'       => 'required|string|email',
            'password'    => 'required|string',
            'remember_me' => 'boolean',
        ]);

        $credentials = request(['email', 'password']);
        if (!Auth::attempt($credentials)) {
            return  HttpResponseMessages::getResponse401([
                'message'   => 'Credenciales inválidas.'
            ]);
        }
        $user           = $request->user();
        if ($user->active == 0) {
            return  HttpResponseMessages::getResponse401([
                'message'   => 'El usuario se encuentra inactivo. Comuníquese con el administrador.'
            ]);
        }
        if (!$user->hasVerifiedEmail()) {
            return HttpResponseMessages::getResponse401([
                'message'   => 'El correo electrónico no ha sido verificado. Comuníquese con el administrador.'
            ]);
        }

        $buser      = DB::table('business_users')->where('user_id', $user->id)->first();
        $company    = Company::where('id', $buser->company_id)->first();
        if ($company->active == 0) {
            return  HttpResponseMessages::getResponse401([
                'message'   => 'La empresa se encuentra inactiva. Comuníquese con el administrador.'
            ]);
        }
        if(!$company) {
            return  HttpResponseMessages::getResponse401([
                'message'   => 'No se ha encontrado la empresa asociada al usuario.'
            ]);
        }
        try {
            $tokenResult    = $user->createToken($user->email);
            $token          = $tokenResult->token;
            $token->save();
            $data       = [
                'user_id'       => $user->id,
                'ip'            => $request->ip(),
            ];

            DB::table('access_users')->insertGetId($data);

            return HttpResponseMessages::getResponse([
                'access_token'  => $tokenResult->accessToken,
                'user'          => $user,
                'expires_at'    => Carbon::parse($token->expires_at)->toDateTimeString(),
                'message'       => 'Bienvenido. Su sesión ha sido iniciada con éxito.'
            ]);
        }catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }

    public static function logout(Request $request): JsonResponse
    {
        try {
            $user       = $request->user();
            $data       = [
                'active'            => 0
            ];
            DB::table('access_users')
                ->where('user_id', $user->id)
                ->where('ip', $request->ip())
                ->where('active',1)
                ->limit(1)
                ->update($data);

            $request->user()->token()->revoke();
            return HttpResponseMessages::getResponse(
                ['message'  => 'Successfully logged out']
            );
        } catch (Exception $e) {
            return HttpResponseMessages::getResponse500([
                'message'   => $e->getMessage()
            ]);
        }
    }

}
