<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\KeyVault;

use App\Modules\Viafirma\Domain\Contracts\KeyVault;
use App\Modules\Viafirma\Domain\Exceptions\KeyVaultException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Implementación del KeyVault basada en AWS KMS para producción (V-501).
 *
 * Usa AWS KMS Encrypt/Decrypt con Data Keys (envelope encryption):
 *  1. store(): KMS GenerateDataKey → cifra material local → persiste envelope en Storage (S3)
 *  2. retrieve(): Lee envelope desde Storage (con cache read-through para performance)
 *                 → KMS Decrypt data key → descifra material
 *  3. destroy(): Elimina del Storage Y del cache
 *
 * IMPORTANTE: El envelope se guarda en Storage PERSISTENTE (no solo en cache).
 *   En producción configurar VIAFIRMA_VAULT_DISK=s3 para que los keys
 *   sobrevivan reinicios de Redis/Memcached.
 *
 * Prerequisitos:
 *  - `aws/aws-sdk-php` en composer.json (ya presente por S3 driver)
 *  - Variable `AWS_KMS_KEY_ID` configurada con el ARN del CMK
 *  - IAM role con permisos kms:GenerateDataKey, kms:Decrypt
 *  - VIAFIRMA_VAULT_DISK=s3 (o el disco persistente deseado)
 */
final class AwsKmsKeyVault implements KeyVault
{
    private readonly string $kmsKeyId;
    private readonly string $vaultDisk;
    private readonly string $vaultPath;
    private readonly string $cachePrefix;
    /** TTL del cache read-through en segundos (1 hora). Solo lectura rápida. */
    private const CACHE_TTL = 3600;

    public function __construct()
    {
        $this->kmsKeyId    = (string) config('viafirma.crypto.aws_kms_key_id');
        $this->vaultDisk   = (string) config('viafirma.crypto.vault_disk', 'local');
        $this->vaultPath   = (string) config('viafirma.crypto.vault_path', 'viafirma/vault');
        $this->cachePrefix = 'viafirma:kms:';

        if ($this->kmsKeyId === '') {
            throw new KeyVaultException(
                'AWS_KMS_KEY_ID no está configurado. Requerido para AwsKmsKeyVault.'
            );
        }
    }

    public function store(string $material, array $metadata = []): string
    {
        if ($material === '') {
            throw new KeyVaultException('No se puede almacenar material vacío.');
        }

        $ref = 'kms_' . Str::ulid()->toBase32() . '_' . bin2hex(random_bytes(4));

        try {
            $kms = $this->getKmsClient();

            // Envelope encryption: KMS genera data key efímera
            $dataKeyResult = $kms->generateDataKey([
                'KeyId'   => $this->kmsKeyId,
                'KeySpec' => 'AES_256',
            ]);

            $plaintextKey = $dataKeyResult['Plaintext'];
            $encryptedKey = base64_encode($dataKeyResult['CiphertextBlob']);

            // Cifrar material con data key — AES-256-GCM (authenticated encryption)
            $iv  = random_bytes(12);
            $tag = '';
            $encrypted = openssl_encrypt(
                $material, 'aes-256-gcm', $plaintextKey, OPENSSL_RAW_DATA, $iv, $tag
            );

            // Borrar plaintext key de memoria inmediatamente
            $plaintextKey = str_repeat("\0", strlen($plaintextKey));
            unset($plaintextKey);

            if ($encrypted === false) {
                throw new KeyVaultException('Fallo al cifrar material con data key KMS.');
            }

            $envelope = json_encode([
                'encrypted_data_key' => $encryptedKey,
                'iv'                 => base64_encode($iv),
                'tag'                => base64_encode($tag),
                'ciphertext'         => base64_encode($encrypted),
                'metadata'           => $metadata,
                'stored_at'          => now()->toISOString(),
            ], JSON_THROW_ON_ERROR);

            // ── Persistencia principal: Storage (S3 en prod, local en dev) ──
            $path = $this->pathFor($ref);
            if (!Storage::disk($this->vaultDisk)->put($path, $envelope)) {
                throw new KeyVaultException("No se pudo escribir el envelope KMS en '{$path}'.");
            }

            // Cache read-through (performance) — NO es la fuente de verdad
            Cache::put($this->cachePrefix . $ref, $envelope, self::CACHE_TTL);

        } catch (KeyVaultException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new KeyVaultException('Error almacenando en AWS KMS vault: ' . $e->getMessage(), 0, $e);
        }

        return $ref;
    }

    public function retrieve(string $ref): string
    {
        try {
            // 1. Intentar cache read-through (evita S3 en el camino caliente)
            $envelopeJson = Cache::get($this->cachePrefix . $ref);

            // 2. Cache miss → leer desde Storage (fuente de verdad)
            if ($envelopeJson === null) {
                $path = $this->pathFor($ref);
                if (!Storage::disk($this->vaultDisk)->exists($path)) {
                    throw new KeyVaultException("Referencia KMS vault '{$ref}' no encontrada en storage.");
                }
                $envelopeJson = Storage::disk($this->vaultDisk)->get($path);
                if ($envelopeJson === null || $envelopeJson === false) {
                    throw new KeyVaultException("No se pudo leer el envelope KMS desde storage: '{$path}'.");
                }
                // Repoblar cache
                Cache::put($this->cachePrefix . $ref, $envelopeJson, self::CACHE_TTL);
            }

            $envelope = json_decode($envelopeJson, true, 512, JSON_THROW_ON_ERROR);
            $kms      = $this->getKmsClient();

            // KMS descifra la data key cifrada
            $decryptResult = $kms->decrypt([
                'KeyId'          => $this->kmsKeyId,
                'CiphertextBlob' => base64_decode($envelope['encrypted_data_key']),
            ]);

            $plaintextKey = $decryptResult['Plaintext'];

            // Descifrar material con la data key
            $material = openssl_decrypt(
                base64_decode($envelope['ciphertext']),
                'aes-256-gcm',
                $plaintextKey,
                OPENSSL_RAW_DATA,
                base64_decode($envelope['iv']),
                base64_decode($envelope['tag']),
            );

            // Limpiar plaintext key
            $plaintextKey = str_repeat("\0", strlen($plaintextKey));
            unset($plaintextKey);

            if ($material === false) {
                throw new KeyVaultException('Fallo al descifrar material del vault KMS.');
            }

            return $material;

        } catch (KeyVaultException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new KeyVaultException('Error recuperando de AWS KMS vault: ' . $e->getMessage(), 0, $e);
        }
    }

    public function destroy(string $ref): void
    {
        // Eliminar de storage persistente
        $path = $this->pathFor($ref);
        if (Storage::disk($this->vaultDisk)->exists($path)) {
            // Best-effort: sobrescribir con ceros antes de borrar
            try {
                $size = (int) Storage::disk($this->vaultDisk)->size($path);
                if ($size > 0) {
                    Storage::disk($this->vaultDisk)->put($path, str_repeat("\0", min($size, 8192)));
                }
            } catch (\Throwable) {
                // ignorar — el delete es lo importante
            }
            Storage::disk($this->vaultDisk)->delete($path);
        }

        // Invalidar cache
        Cache::forget($this->cachePrefix . $ref);
    }

    public function exists(string $ref): bool
    {
        // Cache hit o Storage
        return Cache::has($this->cachePrefix . $ref)
            || Storage::disk($this->vaultDisk)->exists($this->pathFor($ref));
    }

    private function pathFor(string $ref): string
    {
        if (preg_match('/[^A-Za-z0-9_\-]/', $ref) === 1) {
            throw new KeyVaultException("Referencia de vault inválida: '{$ref}'.");
        }
        return rtrim($this->vaultPath, '/') . '/' . $ref . '.bin';
    }

    /** @return \Aws\Kms\KmsClient */
    private function getKmsClient()
    {
        if (!class_exists(\Aws\Kms\KmsClient::class)) {
            throw new KeyVaultException(
                'aws/aws-sdk-php no está instalado. Ejecute: composer require aws/aws-sdk-php'
            );
        }

        return new \Aws\Kms\KmsClient([
            'version' => 'latest',
            'region'  => config('services.ses.region', config('aws.region', 'us-east-1')),
        ]);
    }
}
