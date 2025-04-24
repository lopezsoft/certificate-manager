<?php

namespace App\Http\Controllers\Auth;

use App\Common\HttpResponseMessages;
use App\Common\MessageExceptionResponse;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Modules\Auth\CreatedUser;
use App\Modules\Auth\SendingEmail;
use App\Modules\Company\CreatedCompany;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     */
    public function store(Request $request): JsonResponse
    {
        $messagesValidate = [
            'first_name.required'   => 'El nombre es requerido',
            'last_name.required'    => 'El apellido es requerido',
            'company_name.required' => 'El nombre de la empresa es requerido',
            'email.required'        => 'El correo electrónico es requerido',
            'email.email'           => 'El correo electrónico no es válido',
            'email.unique'          => 'El correo electrónico ya existe',
            'dni.required'          => 'El NIT es requerido',
            'dni.unique'            => 'El NIT ya existe',
        ];

        $request->validate([
            'first_name'    => ['required', 'string', 'max:100'],
            'last_name'     => ['required', 'string', 'max:100'],
            'company_name'  => ['required', 'string', 'max:100'],
            'email'         => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'password'      => ['required', 'confirmed', Rules\Password::defaults()],
            'dni'           => ['required', 'string', 'max:30', 'unique:'.Company::class],
        ], $messagesValidate);

        try {

            DB::beginTransaction();
            $user       = CreatedUser::create($request);
            $company    = CreatedCompany::create($request);
            DB::table('business_users')->insert([
                'user_id'       => $user->id,
                'company_id'    => $company->id,
            ]);
            DB::commit();
            SendingEmail::toUser($user);
            Auth::login($user);
            return HttpResponseMessages::getResponse([
                'message' => 'Empresa creada con éxito. Verifique su dirección de correo electrónico: ' . $request->email,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return MessageExceptionResponse::response($e);
        }
    }
}
