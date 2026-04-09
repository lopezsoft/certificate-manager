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

    /**
     * @OA\Get(
     *     path="/profile/types",
     *     tags={"Perfil"},
     *     summary="Listar tipos de usuario",
     *     description="Retorna los tipos de usuario disponibles en el sistema.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Lista de tipos de usuario",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Administrador")
     *                 ))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function types(): JsonResponse
    {
        return Login::userTypes();
    }

    /**
     * @OA\Post(
     *     path="/auth/login",
     *     tags={"Autenticación"},
     *     summary="Iniciar sesión",
     *     description="Autentica al usuario y retorna el token Bearer de acceso OAuth 2.0.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="usuario@empresa.com"),
     *             @OA\Property(property="password", type="string", format="password", example="contraseña123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login exitoso",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1Qi..."),
     *             @OA\Property(property="token_type", type="string", example="Bearer")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Credenciales incorrectas", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function login(Request $request): JsonResponse
    {
        return Login::login($request);
    }

    /**
     * @OA\Get(
     *     path="/auth/logout",
     *     tags={"Autenticación"},
     *     summary="Cerrar sesión",
     *     description="Revoca el token Bearer activo del usuario autenticado.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Sesión cerrada exitosamente", @OA\JsonContent(ref="#/components/schemas/ApiSuccessResponse")),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        return Login::logout($request);
    }

    /**
     * @OA\Get(
     *     path="/profile",
     *     tags={"Perfil"},
     *     summary="Obtener perfil del usuario autenticado",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Datos del usuario autenticado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="first_name", type="string", example="Juan"),
     *                     @OA\Property(property="last_name", type="string", example="Pérez"),
     *                     @OA\Property(property="email", type="string", example="juan@empresa.com"),
     *                     @OA\Property(property="company_id", type="integer", example=7)
     *                 ))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function user(Request $request): JsonResponse
    {
        return HttpResponseMessages::getResponse([
            'dataRecords'   => [
                'data'  => [$request->user()],
            ],
        ]);
    }

    /**
     * @OA\Put(
     *     path="/profile/{id}",
     *     tags={"Perfil"},
     *     summary="Actualizar perfil de usuario",
     *     description="Actualiza los datos del perfil. Los campos a modificar se envían como JSON serializado en 'records'. Permite subir avatar en base64 dentro del JSON.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID del usuario", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="records", type="string", description="JSON serializado con los campos a actualizar. Ejemplo: {first_name: Juan, last_name: Perez}")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Perfil actualizado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="Juan"),
     *                 @OA\Property(property="last_name", type="string", example="Pérez"),
     *                 @OA\Property(property="email", type="string", example="juan@empresa.com")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
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
