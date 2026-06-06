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
     *             required={"type_id","city_id","first_name","last_name","company_name","email","password","password_confirmation","dni"},
     *             @OA\Property(property="type_id", type="integer", example=2, description="Tipo de usuario: 2=Casa de Software, 3=Arrendamiento en Servidor, 4=Partner"),
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
            'type_id.required'       => 'El tipo de usuario es requerido',
            'type_id.exists'         => 'El tipo de usuario no es válido',
            'type_id.not_in'         => 'El tipo de usuario seleccionado no está permitido para registro',
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
            'type_id'       => ['required', 'integer', 'exists:user_types,id', 'not_in:1'],
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

            $typeId = (int) $request->type_id;

            // Tipos 2 (Casa de Software) y 3 (Arrendamiento en Servidor):
            // Usuarios creados desde nuestras apps — se auto-verifica el email sin fricción.
            // Tipo 4 (Partner): registro externo — requiere verificación por email.
            if (in_array($typeId, [2, 3], true)) {
                $user->markEmailAsVerified();
                Auth::login($user);

                return HttpResponseMessages::getResponse([
                    'message' => 'Empresa creada con éxito.',
                ]);
            }

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

    /**
     * @OA\Post(
     *     path="/sync-account",
     *     tags={"Sincronización"},
     *     summary="Sincronizar cuenta desde sistema externo (ERP/API)",
     *     description="Crea o actualiza un usuario y su empresa desde un sistema externo. El password se recibe ya hasheado (bcrypt). Autenticación vía HMAC-SHA256.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user", "company"},
     *             @OA\Property(property="user", type="object",
     *                 required={"email", "password_hash", "first_name", "last_name", "type_id"},
     *                 @OA\Property(property="email", type="string", format="email"),
     *                 @OA\Property(property="password_hash", type="string", description="Bcrypt hash (60 chars)"),
     *                 @OA\Property(property="first_name", type="string", maxLength=100),
     *                 @OA\Property(property="last_name", type="string", maxLength=100),
     *                 @OA\Property(property="type_id", type="integer", enum={2,3,4})
     *             ),
     *             @OA\Property(property="company", type="object",
     *                 required={"company_name", "dni"},
     *                 @OA\Property(property="company_name", type="string", maxLength=100),
     *                 @OA\Property(property="dni", type="string", maxLength=30)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Cuenta creada"),
     *     @OA\Response(response=200, description="Cuenta actualizada"),
     *     @OA\Response(response=401, description="Autenticación HMAC fallida"),
     *     @OA\Response(response=422, description="Validación fallida")
     * )
     *
     * Sincroniza una cuenta de usuario desde un sistema externo (ERP/API).
     * El password llega ya hasheado (bcrypt) y se asigna directamente.
     */
    public function syncAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Usuario
            'user.email'         => ['required', 'email', 'max:255'],
            'user.password_hash' => ['required', 'string', 'min:60', 'max:60'],
            'user.first_name'    => ['required', 'string', 'max:100'],
            'user.last_name'     => ['required', 'string', 'max:100'],
            'user.type_id'       => ['required', 'integer', 'exists:user_types,id', 'not_in:1'],

            // Empresa
            'company.company_name' => ['required', 'string', 'max:100'],
            'company.dni'          => ['required', 'string', 'max:30'],
            'company.dv'           => ['nullable', 'string', 'max:2'],
            'company.email'        => ['nullable', 'email', 'max:255'],
            'company.phone'        => ['nullable', 'string', 'max:30'],
            'company.address'      => ['nullable', 'string', 'max:255'],
            'company.city_id'      => ['nullable', 'integer', 'exists:cities,id'],
            'company.country_id'   => ['nullable', 'integer'],
        ], [
            'user.email.required'         => 'El email del usuario es requerido',
            'user.email.email'            => 'El email no es válido',
            'user.password_hash.required' => 'El hash del password es requerido',
            'user.password_hash.min'      => 'El hash debe ser un bcrypt válido (60 caracteres)',
            'user.first_name.required'    => 'El nombre es requerido',
            'user.last_name.required'     => 'El apellido es requerido',
            'user.type_id.not_in'         => 'No se permite sincronizar usuarios administradores',
            'company.company_name.required' => 'El nombre de la empresa es requerido',
            'company.dni.required'          => 'El NIT es requerido',
        ]);

        try {
            $result = DB::transaction(function () use ($data) {
                $userData    = $data['user'];
                $companyData = $data['company'];

                // ── 1. Upsert usuario ──────────────────────────────────────
                $user = User::where('email', $userData['email'])->first();
                $userAction = 'updated';

                if (! $user) {
                    $user = User::create([
                        'email'      => $userData['email'],
                        'password'   => $userData['password_hash'], // Bcrypt directo
                        'first_name' => $userData['first_name'],
                        'last_name'  => $userData['last_name'],
                        'type_id'    => $userData['type_id'],
                        'active'     => 1,
                    ]);
                    $userAction = 'created';
                } else {
                    $user->update([
                        'first_name' => $userData['first_name'],
                        'last_name'  => $userData['last_name'],
                        'password'   => $userData['password_hash'],
                        'active'     => 1,
                    ]);
                }

                // Marcar email como verificado (confiamos en el sistema origen)
                if (! $user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                }

                // ── 2. Upsert empresa ──────────────────────────────────────
                $company = Company::where('dni', $companyData['dni'])->first();
                $companyAction = 'updated';

                if (! $company) {
                    $company = Company::create([
                        'company_name'         => $companyData['company_name'],
                        'dni'                  => $companyData['dni'],
                        'dv'                   => $companyData['dv'] ?? null,
                        'email'                => $companyData['email'] ?? $userData['email'],
                        'phone'                => $companyData['phone'] ?? null,
                        'address'              => $companyData['address'] ?? null,
                        'city_id'              => $companyData['city_id'] ?? null,
                        'country_id'           => $companyData['country_id'] ?? 45,
                        'identity_document_id' => $companyData['identity_document_id'] ?? 3,
                        'type_organization_id' => $companyData['type_organization_id'] ?? 1,
                        'active'               => 1,
                    ]);
                    $companyAction = 'created';
                } else {
                    $company->update(array_filter([
                        'company_name' => $companyData['company_name'],
                        'email'        => $companyData['email'] ?? null,
                        'phone'        => $companyData['phone'] ?? null,
                        'address'      => $companyData['address'] ?? null,
                    ]));
                }

                // ── 3. Vincular user ↔ company (idempotente) ───────────────
                $pivotExists = DB::table('business_users')
                    ->where('user_id', $user->id)
                    ->where('company_id', $company->id)
                    ->exists();

                if (! $pivotExists) {
                    DB::table('business_users')->insert([
                        'user_id'    => $user->id,
                        'company_id' => $company->id,
                    ]);
                }

                return [
                    'user_action'    => $userAction,
                    'company_action' => $companyAction,
                    'user_id'        => $user->id,
                    'email'          => $user->email,
                    'company_id'     => $company->id,
                    'company_dni'    => $company->dni,
                ];
            });

            $httpStatus = $result['user_action'] === 'created' ? 201 : 200;
            $message    = $result['user_action'] === 'created'
                ? 'Cuenta sincronizada exitosamente'
                : 'Cuenta actualizada exitosamente';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => $result,
            ], $httpStatus);

        } catch (Exception $e) {
            return MessageExceptionResponse::response($e);
        }
    }
}
