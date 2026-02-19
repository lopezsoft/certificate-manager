<?php

namespace Tests\Unit\Services;

use App\Services\ZipExtractorService;
use Exception;
use Tests\TestCase;
use ZipArchive;

/**
 * Tests unitarios para ZipExtractorService.
 * Usa ZIPs creados en memoria temporal — sin base de datos.
 */
class ZipExtractorServiceTest extends TestCase
{
    private ZipExtractorService $service;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ZipExtractorService();
        $this->tempDir = sys_get_temp_dir() . '/zip_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Limpieza: eliminar archivos temporales creados durante los tests
        $this->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    // ─── Validaciones de entrada ──────────────────────────────────────────────

    public function test_lanza_excepcion_si_la_extension_zip_no_esta_cargada(): void
    {
        if (!extension_loaded('zip')) {
            $this->expectException(Exception::class);
            $this->expectExceptionMessageMatches('/extensión Zip/i');

            $this->service->extract((object)[
                'zipFilePath'  => '/ruta/falsa.zip',
                'password'     => 'pass',
                'extractToPath'=> $this->tempDir,
                'fileName'     => 'test',
            ]);
        } else {
            $this->markTestSkipped('La extensión ZIP está habilitada — este test verifica cuando NO lo está.');
        }
    }

    public function test_lanza_excepcion_si_el_archivo_zip_no_existe(): void
    {
        if (!extension_loaded('zip')) {
            $this->markTestSkipped('Extensión ZIP no disponible.');
        }

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/no existe/i');

        $this->service->extract((object)[
            'zipFilePath'  => '/ruta/que/no/existe/archivo.zip',
            'password'     => 'password',
            'extractToPath'=> $this->tempDir,
            'fileName'     => 'test',
        ]);
    }

    public function test_lanza_excepcion_si_el_archivo_no_es_un_zip_valido(): void
    {
        if (!extension_loaded('zip')) {
            $this->markTestSkipped('Extensión ZIP no disponible.');
        }

        // Crear un archivo que no es un ZIP
        $fakeZip = $this->tempDir . '/fake.zip';
        file_put_contents($fakeZip, 'esto no es un ZIP');

        $this->expectException(Exception::class);

        $this->service->extract((object)[
            'zipFilePath'  => $fakeZip,
            'password'     => 'password',
            'extractToPath'=> $this->tempDir . '/extract',
            'fileName'     => 'fake',
        ]);
    }

    public function test_extrae_zip_sin_contrasena_correctamente(): void
    {
        if (!extension_loaded('zip')) {
            $this->markTestSkipped('Extensión ZIP no disponible.');
        }

        // Crear un ZIP válido sin contraseña
        $zipPath     = $this->tempDir . '/test.zip';
        $extractPath = $this->tempDir . '/extracted';
        $contentFile = $this->tempDir . '/contenido.txt';

        file_put_contents($contentFile, 'Contenido de prueba');

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFile($contentFile, 'contenido.txt');
        $zip->close();

        $result = $this->service->extract((object)[
            'zipFilePath'  => $zipPath,
            'password'     => 'dummy',
            'extractToPath'=> $extractPath,
            'fileName'     => 'test',
        ]);

        $this->assertTrue($result);
        $this->assertFileExists($extractPath . '/contenido.txt');
    }

    public function test_crea_directorio_de_extraccion_si_no_existe(): void
    {
        if (!extension_loaded('zip')) {
            $this->markTestSkipped('Extensión ZIP no disponible.');
        }

        $zipPath      = $this->tempDir . '/test2.zip';
        $extractPath  = $this->tempDir . '/nuevo_dir_' . uniqid();
        $contentFile  = $this->tempDir . '/doc.txt';

        file_put_contents($contentFile, 'Documento de prueba');

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFile($contentFile, 'doc.txt');
        $zip->close();

        // El directorio no existe aún
        $this->assertDirectoryDoesNotExist($extractPath);

        $this->service->extract((object)[
            'zipFilePath'  => $zipPath,
            'password'     => 'dummy',
            'extractToPath'=> $extractPath,
            'fileName'     => 'test2',
        ]);

        // El servicio debe haberlo creado
        $this->assertDirectoryExists($extractPath);
    }

    // ─── getZipOpenError (código de errores) ─────────────────────────────────

    public function test_retorna_mensaje_de_error_para_archivo_no_zip(): void
    {
        if (!extension_loaded('zip')) {
            $this->markTestSkipped('Extensión ZIP no disponible.');
        }

        $notAZip = $this->tempDir . '/nozip.zip';
        file_put_contents($notAZip, 'no soy un zip');

        try {
            $this->service->extract((object)[
                'zipFilePath'  => $notAZip,
                'password'     => 'pass',
                'extractToPath'=> $this->tempDir . '/out',
                'fileName'     => 'nozip',
            ]);
            $this->fail('Se esperaba una excepción');
        } catch (Exception $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = "$dir/$item";
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
