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
     * @OA\Post(
     *     path="/register",
     *     tags={"Autenticación"},
     *     summary="Registrar nueva empresa y usuario",
     *     description="Crea una cuenta de empresa junto con el usuario administrador. Envía un correo de verificación al email registrado.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"city_id","first_name","last_name","company_name","email","password","password_confirmation","dni"},
     *             @OA\Property(property="city_id", type="integer", example=149, description="ID de la ciudad (ver /cities)"),
     *             @OA\Property(property="first_name", type="string", maxLength=100, example="Juan"),
     *             @OA\Property(property="last_name", type="string", maxLength=100, example="Pérez"),
     *             @OA\Property(property="company_name", type="string", maxLength=100, example="Mi Empresa S.A.S."),
     *             @OA\Property(property="email", type="string", format="email", example="juan@empresa.com"),
     *             @OA\Property(property="password", type="string", format="password", example="contraseña123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="contraseña123"),
     *             @OA\Property(property="dni", type="string", maxLength=30, example="900455420", description="NIT de la empresa (único)")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Empresa registrada. Verifique el correo electrónico.", @OA\JsonContent(ref="#/components/schemas/ApiSuccessResponse")),
     *     @OA\Response(response=422, description="Validación fallida (email o NIT duplicado)", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     *
     * Handle an incoming registration request.
     */
    public function store(Request $request): JsonResponse
    {
        $messagesValidate = [
            'city_id.required'       => 'La ciudad es requerida',
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
            'city_id'       => ['required', 'integer', 'exists:cities,id'],
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
