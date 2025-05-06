<?php

namespace App\Services;

use ZipArchive;
use Exception;
use Illuminate\Support\Facades\File; // Para manejo de directorios

class ZipExtractorService
{
    /**
     * Extrae un archivo ZIP protegido por contraseña.
     *
     * @param string $params ->zipFilePath Ruta al archivo ZIP.
     * @param string $params ->password La contraseña del archivo ZIP.
     * @param string $params ->extractToPath Ruta al directorio donde extraer los archivos.
     * @return bool True si la extracción fue exitosa, false en caso contrario.
     * @throws Exception Si ocurre un error (archivo no encontrado, contraseña incorrecta, etc.).
     */
    public function extract(object $params): bool
    {
        $zipFilePath    = $params->zipFilePath;
        $password       = $params->password;
        $extractToPath  = $params->extractToPath;
        $fileName       = $params->fileName;
        if (!extension_loaded('zip')) {
            throw new Exception('La extensión Zip de PHP no está habilitada.');
        }

        if (!file_exists($zipFilePath)) {
            throw new Exception("El archivo ZIP no existe en la ruta: $zipFilePath");
        }

        // Asegúrate de que el directorio de extracción exista y tenga permisos de escritura
        if (!File::isDirectory($extractToPath)) {
            // Intenta crear el directorio recursivamente
            if (!File::makeDirectory($extractToPath, 0755, true, true)) {
                throw new Exception("No se pudo crear el directorio de extracción: $extractToPath");
            }
        } elseif (!File::isWritable($extractToPath)) {
            throw new Exception("El directorio de extracción no tiene permisos de escritura: $extractToPath");
        }


        $zip = new ZipArchive();

        // Abre el archivo ZIP
        $status = $zip->open($zipFilePath);
        if ($status !== true) {
            throw new Exception("No se pudo abrir el archivo ZIP. Código de error: " . $this->getZipOpenError($status));
        }

        // Intenta establecer la contraseña
        if (!$zip->setPassword($password)) {
            $zip->close(); // Cierra el archivo antes de lanzar la excepción
            throw new Exception("No se pudo establecer la contraseña para el archivo ZIP.");
            // Nota: setPassword devuelve true/false pero no siempre indica si la contraseña es *correcta*
            // La verificación real ocurre durante la extracción.
        }

        // Intenta extraer los archivos
        // Si la contraseña es incorrecta, extractTo devolverá false.
        if (!$zip->extractTo($extractToPath)) {
            $extractionError = "Error al extraer el archivo ZIP. Verifica la contraseña o los permisos del directorio de destino.";
            // Intentamos obtener un error más específico si está disponible (depende de la versión de libzip)
            if (method_exists($zip, 'getStatusString')) {
                $extractionError .= " Estado: " . $zip->getStatusString();
            }
            $zip->close();
            throw new Exception($extractionError);
        }

        // Cierra el archivo ZIP
        $zip->close();

        return true;
    }

    /**
     * Convierte el código de error de ZipArchive::open a un mensaje legible.
     *
     * @param int $status El código de error.
     * @return string El mensaje de error.
     */
    private function getZipOpenError(int $status): string
    {
        switch ($status) {
            case ZipArchive::ER_EXISTS:
                return 'El archivo ya existe.';
            case ZipArchive::ER_INCONS:
                return 'Archivo ZIP inconsistente.';
            case ZipArchive::ER_INVAL:
                return 'Argumento inválido.';
            case ZipArchive::ER_MEMORY:
                return 'Error de asignación de memoria.';
            case ZipArchive::ER_NOENT:
                return 'El archivo no existe.';
            case ZipArchive::ER_NOZIP:
                return 'No es un archivo ZIP.';
            case ZipArchive::ER_OPEN:
                return 'No se puede abrir el archivo.';
            case ZipArchive::ER_READ:
                return 'Error de lectura.';
            case ZipArchive::ER_SEEK:
                return 'Error de búsqueda.';
            default:
                return 'Error desconocido (' . $status . ')';
        }
    }
}
