<?php

namespace App\Http\Controllers;

use App\Http\Requests\Certificate\UploadCertificateFileBase64Request;
use App\Services\CertificateRequestFilesService;
use Illuminate\Http\JsonResponse;

class CertificateRequestFilesController extends Controller
{
    public function __construct(
        private readonly CertificateRequestFilesService $filesService
    ) {}

    /**
     * @OA\Post(
     *     path="/certificate-request/{id}/files",
     *     tags={"Archivos"},
     *     summary="Subir archivos en Base64 a una solicitud",
     *     description="Adjunta uno o más archivos en Base64 (PDF, imagen o ZIP con certificado P12/PFX) a una solicitud existente. Máximo 6 archivos por solicitud. Límite de tamaño: 2 MB por archivo. Límite: 10 cargas/minuto.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID de la solicitud de certificado", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"attachments"},
     *                 @OA\Property(property="attachments", type="array", description="Array de archivos en Base64",
     *                     @OA\Items(
     *                         type="object",
     *                         required={"base64"},
     *                         @OA\Property(property="base64", type="string", description="Contenido del archivo en Base64. Soporta prefijo Data URI (data:application/pdf;base64,...) o solo el contenido Base64"),
     *                         @OA\Property(property="name", type="string", nullable=true, description="Nombre del archivo (se genera si no está presente)"),
     *                         @OA\Property(property="type", type="string", nullable=true, description="MIME type (se detecta si no está presente)"),
     *                         @OA\Property(property="size", type="integer", nullable=true, description="Tamaño en bytes")
     *                     )
     *                 ),
     *                 @OA\Property(property="document_type", type="string", enum={"ATTACHED", "PAYMENT"}, example="PAYMENT", description="Tipo de documento"),
     *                 @OA\Property(property="pin", type="string", nullable=true, description="PIN del certificado (solo para archivos ZIP con P12/PFX)")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Archivos subidos correctamente", @OA\JsonContent(ref="#/components/schemas/FileManager")),
     *     @OA\Response(response=400, description="Solicitud no encontrada, límite de archivos alcanzado, archivo demasiado grande o Base64 inválido", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=429, description="Demasiadas cargas", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function createFile(UploadCertificateFileBase64Request $request, mixed $certificateRequestId): JsonResponse
    {
        return $this->filesService->createFilesFromBase64($request, $certificateRequestId);
    }

    /**
     * @OA\Delete(
     *     path="/certificate-request/{id}/files/{fileId}",
     *     tags={"Archivos"},
     *     summary="Eliminar un archivo de una solicitud",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID de la solicitud de certificado", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="fileId", in="path", required=true, description="ID del archivo a eliminar", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Archivo eliminado correctamente", @OA\JsonContent(ref="#/components/schemas/ApiSuccessResponse")),
     *     @OA\Response(response=400, description="Archivo no encontrado", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function deleteFile(mixed $id, mixed $fileId): JsonResponse
    {
        return $this->filesService->deleteFile($id, $fileId);
    }
}
