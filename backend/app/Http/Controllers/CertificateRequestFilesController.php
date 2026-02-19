<?php

namespace App\Http\Controllers;

use App\Services\CertificateRequestFilesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateRequestFilesController extends Controller
{
    /**
     * @OA\Post(
     *     path="/certificate-request/{id}/files",
     *     tags={"Archivos"},
     *     summary="Subir archivo a una solicitud",
     *     description="Adjunta un archivo (PDF, imagen o ZIP con certificado P12/PFX) a una solicitud existente. Máximo 6 archivos por solicitud. Límite de tamaño: 2 MB por archivo. Límite: 10 cargas/minuto.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID de la solicitud de certificado", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(property="file", type="string", format="binary", description="Archivo a adjuntar (PDF, JPG, PNG, ZIP)"),
     *                 @OA\Property(property="document_type", type="string", enum={"ATTACHED"}, example="ATTACHED"),
     *                 @OA\Property(property="pin", type="string", nullable=true, description="PIN del certificado (solo para archivos ZIP con P12/PFX)")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Archivo subido correctamente", @OA\JsonContent(ref="#/components/schemas/FileManager")),
     *     @OA\Response(response=400, description="Solicitud no encontrada, límite de archivos alcanzado o archivo demasiado grande", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=429, description="Demasiadas cargas", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function createFile(Request $request, $certificateRequestId): JsonResponse
    {
        return (new CertificateRequestFilesService())->createFile($request, $certificateRequestId);
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
    public function deleteFile($id, $fileId): JsonResponse
    {
        return (new CertificateRequestFilesService())->deleteFile($id, $fileId);
    }
}
