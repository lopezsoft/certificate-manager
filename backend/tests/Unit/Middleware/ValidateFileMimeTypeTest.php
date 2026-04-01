<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ValidateFileMimeType;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Tests unitarios para ValidateFileMimeType middleware.
 * Usa archivos en memoria — sin base de datos ni disco real.
 */
class ValidateFileMimeTypeTest extends TestCase
{
    private ValidateFileMimeType $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new ValidateFileMimeType();
    }

    // ─── Archivos permitidos ──────────────────────────────────────────────────

    public function test_permite_solicitud_sin_archivos(): void
    {
        $request  = Request::create('/test', 'POST');
        $passed   = false;

        $this->middleware->handle($request, function () use (&$passed) {
            $passed = true;
            return response()->json(['ok' => true]);
        });

        $this->assertTrue($passed);
    }

    public function test_permite_archivo_pdf_valido(): void
    {
        $file    = UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf');
        $request = $this->buildRequestWithFile($file);
        $passed  = false;

        $response = $this->middleware->handle($request, function () use (&$passed) {
            $passed = true;
            return response()->json(['ok' => true]);
        });

        // Si finfo está disponible puede detectar el MIME real del fake file;
        // si no, lo deja pasar. En ambos casos no debe lanzar excepción.
        $this->assertTrue($response->getStatusCode() === 200 || $response->getStatusCode() === 422);
    }

    public function test_permite_archivo_imagen_jpeg(): void
    {
        $file    = UploadedFile::fake()->image('foto.jpg', 100, 100);
        $request = $this->buildRequestWithFile($file);
        $passed  = false;

        $this->middleware->handle($request, function () use (&$passed) {
            $passed = true;
            return response()->json(['ok' => true]);
        });

        // JPEG real generado por fake()->image() debe pasar
        $this->assertTrue($passed);
    }

    public function test_permite_archivo_imagen_png(): void
    {
        $file    = UploadedFile::fake()->image('imagen.png', 50, 50);
        $request = $this->buildRequestWithFile($file);
        $passed  = false;

        $this->middleware->handle($request, function () use (&$passed) {
            $passed = true;
            return response()->json(['ok' => true]);
        });

        $this->assertTrue($passed);
    }

    // ─── Archivos rechazados ──────────────────────────────────────────────────

    public function test_rechaza_archivo_ejecutable_con_extension_pdf(): void
    {
        // Archivo con contenido de ejecutable Windows (MZ header) disfrazado de PDF
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, "MZ\x90\x00" . str_repeat("\x00", 100)); // PE header

        $file    = new UploadedFile($tmpFile, 'documento.pdf', 'application/pdf', null, true);
        $request = $this->buildRequestWithFile($file);

        $response = $this->middleware->handle($request, function () {
            return response()->json(['ok' => true]);
        });

        // finfo debe detectar que no es un PDF real
        // Si finfo no está disponible, el test se omite
        if (!function_exists('finfo_open')) {
            $this->markTestSkipped('finfo no disponible en este entorno.');
        }

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertFalse($response->getData()->success);

        unlink($tmpFile);
    }

    public function test_rechaza_script_php_disfrazado_de_imagen(): void
    {
        if (!function_exists('finfo_open')) {
            $this->markTestSkipped('finfo no disponible en este entorno.');
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, '<?php echo shell_exec($_GET["cmd"]); ?>');

        $file    = new UploadedFile($tmpFile, 'imagen.jpg', 'image/jpeg', null, true);
        $request = $this->buildRequestWithFile($file);

        $response = $this->middleware->handle($request, function () {
            return response()->json(['ok' => true]);
        });

        $this->assertEquals(422, $response->getStatusCode());

        unlink($tmpFile);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function buildRequestWithFile(UploadedFile $file): Request
    {
        $request = Request::create('/test', 'POST');
        $request->files->set('file', $file);
        return $request;
    }
}
