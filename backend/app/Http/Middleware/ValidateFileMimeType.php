<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Valida el tipo MIME real de los archivos subidos usando finfo,
 * evitando que archivos maliciosos pasen solo con una extensión falsa.
 */
class ValidateFileMimeType
{
    /**
     * MIME types permitidos para documentos de certificado.
     */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/zip',
        'application/x-zip-compressed',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
        'application/vnd.ms-excel',                                           // xls
        'application/pkcs12',  // p12/pfx
        'application/x-pkcs12',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        foreach ($request->allFiles() as $file) {
            // Soporta tanto un archivo único como un array de archivos
            $files = is_array($file) ? $file : [$file];

            foreach ($files as $uploadedFile) {
                if (!$uploadedFile || !$uploadedFile->isValid()) {
                    continue;
                }

                $realMime = $this->getRealMimeType($uploadedFile->getRealPath());

                if ($realMime === null || !in_array($realMime, self::ALLOWED_MIME_TYPES, true)) {
                    return response()->json([
                        'success' => false,
                        'message' => "El archivo '{$uploadedFile->getClientOriginalName()}' tiene un tipo de contenido no permitido ({$realMime}).",
                    ], 422);
                }
            }
        }

        return $next($request);
    }

    /**
     * Detecta el MIME real del archivo usando finfo (magic bytes),
     * ignorando completamente lo que declara el cliente.
     */
    private function getRealMimeType(string $path): ?string
    {
        if (!function_exists('finfo_open')) {
            // Si finfo no está disponible, dejamos pasar (degradación segura)
            return self::ALLOWED_MIME_TYPES[0];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $path);
        finfo_close($finfo);

        return $mime ?: null;
    }
}
