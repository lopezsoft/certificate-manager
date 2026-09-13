<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\Http;

use App\Modules\Viafirma\Application\DTOs\ProfileDescriptor;
use App\Modules\Viafirma\Application\DTOs\StatusResultDto;
use App\Modules\Viafirma\Application\DTOs\SubmitCsrInputDto;
use App\Modules\Viafirma\Application\DTOs\SubmitCsrResultDto;
use App\Modules\Viafirma\Domain\Contracts\ViafirmaClient;
use App\Modules\Viafirma\Domain\Enums\RemoteStatus;
use Illuminate\Support\Facades\Cache;

/**
 * Cliente Mock de Viafirma para entorno Sandbox.
 *
 * Simula las respuestas del API de Viafirma para poder probar todo el
 * ciclo de vida (emisión, polling, descarga y revocación) sin generar
 * certificados reales ni interactuar con la infraestructura externa.
 *
 * ⚠️  REQUISITO DE CACHÉ: Este cliente usa `Cache::put` para persistir el
 * estado simulado entre requests HTTP. Asegúrese de usar un driver de caché
 * con persistencia real (file, redis, database) en el entorno de sandbox.
 * Si el driver es 'array', el estado se pierde en cada request y `getStatus()`
 * siempre devolverá GENERATED_NOT_DOWNLOADED (como si ya estuviera listo),
 * lo que es correcto funcionalmente pero no simula la demora realista de 4 polls
 * — y tampoco ejercita el paso `accreditation` (captura automática del link KYC
 * + correo a la empresa maestra), que requiere que el estado persista entre polls.
 *
 * Activación: VIAFIRMA_SANDBOX_MODE=true en el .env
 */
class MockViafirmaClient implements ViafirmaClient
{

    public function getProfiles(string $raCode): array
    {
        return [
            new ProfileDescriptor(
                codProfile: config('viafirma.cod_profile_corporate', 'FE-PJ'),
                name: 'Certificado Representante Legal (Sandbox)',
                dnPattern: 'CN=#1#, O=#2#, C=CO',
                validity: 730,
                token: 'mock-token-corporate',
                raw: ['mock' => true, 'profile' => 'corporate'],
            ),
            new ProfileDescriptor(
                codProfile: config('viafirma.cod_profile_individual', 'FE-PN'),
                name: 'Certificado Persona Natural (Sandbox)',
                dnPattern: 'CN=#1#, C=CO',
                validity: 730,
                token: 'mock-token-individual',
                raw: ['mock' => true, 'profile' => 'individual'],
            ),
        ];
    }

    public function submitCsr(SubmitCsrInputDto $input): SubmitCsrResultDto
    {
        $codRequest = 'MOCK-REQ-' . strtoupper(uniqid());
        $publicId   = 'MOCK-PUB-' . strtoupper(uniqid());

        // Advertencia si el driver de caché no persiste entre requests HTTP.
        if (config('cache.default') === 'array') {
            \Illuminate\Support\Facades\Log::warning(
                'MockViafirmaClient: El driver de caché es "array". ' .
                'El estado del sandbox no persiste entre requests HTTP. ' .
                'Usa CACHE_DRIVER=file o redis para simular polling multi-request.',
                ['cod_request' => $codRequest]
            );
        }

        // Inicializamos el estado simulado en Cache.
        // Empezará en RUES_CHECK (progressing) y avanzará en polls.
        Cache::put("mock_viafirma_status_{$codRequest}", [
            'polls'    => 0,
            'publicId' => $publicId,
        ], now()->addHours(2));

        // Guardamos la CSR indexada por publicId para que downloadP7b() pueda
        // emitir un certificado con el MISMO subject y la MISMA llave pública
        // que se solicitaron. Sin esto el P7B simulado no correspondería a la
        // llave privada del vault y el ensamblado fallaría — que es justo el
        // error que este mock debe dejar de producir.
        Cache::put("mock_viafirma_csr_{$publicId}", $input->csrBase64, now()->addHours(2));

        return new SubmitCsrResultDto(
            codRequest:    $codRequest,
            publicId:      $publicId,
            initialStatus: RemoteStatus::RUES_CHECK->value, // estado inicial válido (progressing)
            raw: [
                'codRequest' => $codRequest,
                'publicId'   => $publicId,
                'mock'       => true,
            ],
        );
    }

    public function getStatus(string $codRequest): StatusResultDto
    {
        $cacheKey = "mock_viafirma_status_{$codRequest}";
        $state = Cache::get($cacheKey);

        if (!$state) {
            // Si no existe (quizás expiró o es un ID dummy manual), devolvemos éxito directo.
            return new StatusResultDto(
                status: RemoteStatus::GENERATED_NOT_DOWNLOADED,
                codRequest: $codRequest,
                raw: ['mock' => true, 'default_success' => true]
            );
        }

        $state['polls']++;
        Cache::put($cacheKey, $state, now()->addHours(2));

        // Simulamos demora realista usando estados válidos del enum RemoteStatus:
        // Poll 1 -> rues_check    (progressing)
        // Poll 2 -> accreditation (progressing — dispara ViafirmaAccreditationReached
        //           y permite probar en sandbox la captura automática del link KYC
        //           + el correo a la empresa maestra, igual que en producción)
        // Poll 3 -> inProcess     (progressing)
        // Poll 4+ -> Generated_Not_Downloaded (listo!)
        $status = match (true) {
            $state['polls'] === 1 => RemoteStatus::RUES_CHECK,
            $state['polls'] === 2 => RemoteStatus::ACCREDITATION,
            $state['polls'] === 3 => RemoteStatus::IN_PROCESS,
            default              => RemoteStatus::GENERATED_NOT_DOWNLOADED,
        };

        return new StatusResultDto(
            status:    $status,
            codRequest: $codRequest,
            raw: ['mock' => true, 'polls' => $state['polls'], 'simulated_status' => $status->value]
        );
    }

    /**
     * Devuelve un bundle PKCS#7 REAL y parseable, con un certificado emitido
     * sobre la CSR original (mismo subject, misma llave pública) y firmado por
     * una CA autofirmada simulada.
     *
     * Antes devolvía `base64_encode('MOCK_P7B_DATA_...')`, que no es un PKCS#7:
     * OpenSSL fallaba con "asn1 encoding routines::too long | bad object header",
     * el ensamblado nunca llegaba a ASSEMBLED y ningún integrador podía cerrar
     * una emisión completa en Sandbox. Emitir un bundle válido no introduce una
     * diferencia de comportamiento entre entornos: la elimina, porque el
     * Viafirma real tampoco devolvería jamás un bundle inparseable.
     *
     * El certificado se emite CON EL SUBJECT DE LA CSR para no disparar
     * IdentityMismatchException, y CON LA LLAVE PÚBLICA DE LA CSR para que
     * findEndEntityCertificate() lo reconozca como el de nuestra llave privada.
     */
    public function downloadP7b(string $publicId): string
    {
        $csrBase64 = Cache::get("mock_viafirma_csr_{$publicId}");

        if (!is_string($csrBase64) || $csrBase64 === '') {
            throw new \RuntimeException(
                "MockViafirmaClient: no hay CSR en caché para publicId {$publicId}. " .
                'El sandbox necesita la CSR original para simular la emisión; ' .
                'verifica que CACHE_DRIVER no sea "array" y que el trámite se haya ' .
                'creado en esta misma instalación.'
            );
        }

        return $this->issueSimulatedP7b($csrBase64);
    }

    /**
     * Emite un PKCS#7 (DER) que contiene el certificado del titular más la CA
     * simulada que lo firma, replicando la estructura que entrega Viafirma.
     */
    private function issueSimulatedP7b(string $csrBase64): string
    {
        $csrPem = $this->normalizeCsrToPem($csrBase64);

        $csr = @openssl_csr_get_subject($csrPem);
        if ($csr === false) {
            throw new \RuntimeException('MockViafirmaClient: la CSR almacenada no se pudo parsear.');
        }

        // Mismo openssl.cnf que usa OpenSslCryptoService: en Windows/WAMP sin
        // OPENSSL_CONF en el entorno, openssl_pkey_new() y openssl_csr_new()
        // fallan sin él.
        $sslOpts = ['digest_alg' => 'sha256'];
        $conf    = config('viafirma.crypto.openssl_conf');
        if (is_string($conf) && is_file($conf)) {
            $sslOpts['config'] = $conf;
        }

        // ── CA simulada (efímera, sólo para firmar en sandbox) ──────────────
        $caKey = openssl_pkey_new($sslOpts + [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($caKey === false) {
            throw new \RuntimeException(
                'MockViafirmaClient: no se pudo generar la llave de la CA simulada: '
                . $this->collectOpenSslErrors()
            );
        }

        $caCsr = openssl_csr_new(
            ['CN' => 'MATICERTS Sandbox Mock CA', 'O' => 'MATICERTS', 'C' => 'CO'],
            $caKey,
            $sslOpts,
        );
        if ($caCsr === false) {
            throw new \RuntimeException('MockViafirmaClient: no se pudo generar la CSR de la CA simulada.');
        }

        // CA autofirmada ($caCert = null la vuelve self-signed).
        $caCert = openssl_csr_sign($caCsr, null, $caKey, 3650, $sslOpts, random_int(1, PHP_INT_MAX));
        if ($caCert === false) {
            throw new \RuntimeException('MockViafirmaClient: no se pudo autofirmar la CA simulada.');
        }

        // ── Certificado del titular, firmado por la CA simulada ─────────────
        // Se firma la CSR REAL: conserva subject y llave pública originales.
        $endEntityCert = openssl_csr_sign(
            $csrPem,
            $caCert,
            $caKey,
            (int) config('viafirma.mock_cert_validity_days', 730),
            $sslOpts,
            random_int(1, PHP_INT_MAX),
        );
        if ($endEntityCert === false) {
            throw new \RuntimeException(
                'MockViafirmaClient: no se pudo firmar el certificado del titular: '
                . $this->collectOpenSslErrors()
            );
        }

        // ── Empaquetar como PKCS#7 (titular + cadena CA) ────────────────────
        openssl_x509_export($endEntityCert, $endEntityPem);
        openssl_x509_export($caCert, $caPem);

        return $this->buildPkcs7Der($endEntityPem, $caPem, $caKey);
    }

    /**
     * Acepta la CSR tal como viaja en el DTO (base64 del PEM, o base64 del DER)
     * y la devuelve siempre como PEM.
     */
    private function normalizeCsrToPem(string $csrBase64): string
    {
        $decoded = base64_decode($csrBase64, true);

        if (is_string($decoded) && str_contains($decoded, 'BEGIN CERTIFICATE REQUEST')) {
            return $decoded;
        }

        // Ya venía en PEM sin codificar.
        if (str_contains($csrBase64, 'BEGIN CERTIFICATE REQUEST')) {
            return $csrBase64;
        }

        // Era DER en base64: reconstruimos el envoltorio PEM.
        return "-----BEGIN CERTIFICATE REQUEST-----\n"
            . chunk_split($csrBase64, 64, "\n")
            . "-----END CERTIFICATE REQUEST-----\n";
    }

    /**
     * Construye un contenedor PKCS#7 con el certificado del titular y su CA.
     *
     * Se intenta primero un PKCS#7 DER real (vía openssl_pkcs7_sign sobre un
     * contenido vacío, la única forma que expone PHP de construir un
     * contenedor con cadena). Si el entorno no lo permite, se cae a un P7B en
     * PEM concatenado: `OpenSslCryptoService::extractCertsFromPemP7b()` ya
     * contempla ese formato explícitamente como fallback, así que el
     * ensamblado funciona igual.
     */
    private function buildPkcs7Der(string $endEntityPem, string $caPem, \OpenSSLAsymmetricKey $caKey): string
    {
        $pemBundle = $endEntityPem . "\n" . $caPem;

        $conf       = config('viafirma.crypto.openssl_conf');
        $exportArgs = (is_string($conf) && is_file($conf)) ? ['config' => $conf] : null;

        $exported = $exportArgs !== null
            ? openssl_pkey_export($caKey, $caKeyPem, null, $exportArgs)
            : openssl_pkey_export($caKey, $caKeyPem);

        if (!$exported) {
            return $pemBundle;
        }

        $chainFile   = tempnam(sys_get_temp_dir(), 'mockca_');
        $contentFile = tempnam(sys_get_temp_dir(), 'mockp7_');
        $outFile     = tempnam(sys_get_temp_dir(), 'mockp7out_');

        try {
            file_put_contents($chainFile, $endEntityPem);
            file_put_contents($contentFile, '');

            // Firma la CA (de la que sí tenemos la llave); el certificado del
            // titular viaja como cadena adjunta. Lo relevante no es la firma,
            // sino que el contenedor incluya ambos certificados.
            $signed = @openssl_pkcs7_sign(
                $contentFile,
                $outFile,
                $caPem,
                $caKeyPem,
                [],
                PKCS7_BINARY | PKCS7_NOATTR,
                $chainFile,
            );

            if ($signed === false) {
                return $pemBundle;
            }

            // openssl_pkcs7_sign emite S/MIME; extraemos el cuerpo base64 y lo
            // devolvemos como DER binario, que es lo que entrega Viafirma.
            $smime = (string) file_get_contents($outFile);
            if (preg_match('/\r?\n\r?\n(.+)$/s', $smime, $m)) {
                $der = base64_decode(preg_replace('/\s+/', '', $m[1]) ?? '', true);
                if (is_string($der) && $der !== '') {
                    return $der;
                }
            }

            return $pemBundle;
        } finally {
            @unlink($chainFile);
            @unlink($contentFile);
            @unlink($outFile);
        }
    }

    private function collectOpenSslErrors(): string
    {
        $errors = [];
        while (($e = openssl_error_string()) !== false) {
            $errors[] = $e;
        }
        return $errors === [] ? '(sin detalle)' : implode(' | ', $errors);
    }

    public function revokeCertificate(string $revokingCode, int $revocationReason): string
    {
        return 'MOCK-REVOKED-REQ-' . strtoupper(uniqid());
    }

    /**
     * En Sandbox no hay una pasarela MetaMap real que abrir. Antes se devolvía
     * `https://sandbox.viafirma.com/accreditation/success?...`: un host real
     * con una ruta inexistente, así que el enlace daba 404 y nadie podía
     * completar el KYC por navegador (Posyma tuvo que invocar el callback a mano).
     *
     * Se apunta directamente a nuestro callback público —el mismo destino al
     * que MetaMap redirige en producción tras la verificación—, de modo que
     * abrir el enlace simula el flujo completo: registra la finalización y
     * reenvía al destino configurado. No requiere página nueva en el front.
     */
    public function getAccreditationLink(string $codRequest, string $publicId): string
    {
        return route('viafirma.kyc-callback', ['publicId' => $publicId]);
    }

    public function getRevocationCode(string $codRequest): string
    {
        return 'MOCK-REV-CODE-' . strtoupper(substr(md5($codRequest), 0, 8));
    }

    public function uploadFiles(string $codRequest, array $files): array
    {
        $cacheKey = "mock_viafirma_files_{$codRequest}";
        $existing = Cache::get($cacheKey, []);

        $uploaded = [];
        foreach ($files as $file) {
            $record = [
                'id'             => 'MOCK-FILE-' . strtoupper(uniqid()),
                'name'           => $file['name'],
                'uploadedByUser' => false,
                'size'           => strlen((string) base64_decode($file['base64'], true)),
                'dateAdded'      => (int) (microtime(true) * 1000),
            ];
            $existing[] = $record;
            $uploaded[] = $record;
        }

        Cache::put($cacheKey, $existing, now()->addHours(2));

        return $uploaded;
    }

    public function listFiles(string $codRequest): array
    {
        return Cache::get("mock_viafirma_files_{$codRequest}", []);
    }
}
