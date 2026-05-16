<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\KeyVault;

use App\Modules\Viafirma\Domain\Contracts\KeyVault;
use App\Modules\Viafirma\Domain\Exceptions\KeyVaultException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Implementación del KeyVault basada en AWS KMS para producción (V-501).
 *
 * Usa AWS KMS Encrypt/Decrypt con Data Keys (envelope encryption):
 *  1. store(): KMS GenerateDataKey → cifra material local → guarda {ciphertext + encrypted_data_key} en cache/storage
 *  2. retrieve(): Lee {ciphertext + encrypted_data_key} → KMS Decrypt → descifra material
 *  3. destroy(): Elimina la entrada del storage
 *
 * Prerequisitos:
 *  - `aws/aws-sdk-php` en composer.json (ya presente por S3 driver)
 *  - Variable `AWS_KMS_KEY_ID` configurada con el ARN del CMK
 *  - IAM role con permisos kms:GenerateDataKey, kms:Decrypt, kms:Encrypt
 *
 * NOTA: Si `aws/aws-sdk-php` no está instalado, se lanza excepción clara.
 *       Para desarrollo/staging, usar `EncryptedLocalKeyVault`.
 */
final class AwsKmsKeyVault implements KeyVault
{
    private readonly string $kmsKeyId;
    private readonly string $cachePrefix;

    public function __construct()
    {
        $this->kmsKeyId = (string) config('viafirma.crypto.aws_kms_key_id');
        $this->cachePrefix = 'viafirma:vault:';

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

            // Envelope encryption: generar data key, cifrar material con ella
            $dataKeyResult = $kms->generateDataKey([
                'KeyId'   => $this->kmsKeyId,
                'KeySpec' => 'AES_256',
            ]);

            $plaintextKey  = $dataKeyResult['Plaintext'];
            $encryptedKey  = base64_encode($dataKeyResult['CiphertextBlob']);

            // Cifrar material con la data key usando OpenSSL AES-256-GCM
            $iv  = random_bytes(12);
            $tag = '';
            $encrypted = openssl_encrypt($material, 'aes-256-gcm', $plaintextKey, OPENSSL_RAW_DATA, $iv, $tag);

            if ($encrypted === false) {
                throw new KeyVaultException('Fallo al cifrar material con data key.');
            }

            // Limpiar plaintext key de memoria
            $plaintextKey = str_repeat("\0", strlen($plaintextKey));
            unset($plaintextKey);

            // Almacenar envelope: encrypted_data_key + iv + tag + ciphertext
            $envelope = json_encode([
                'encrypted_data_key' => $encryptedKey,
                'iv'                 => base64_encode($iv),
                'tag'                => base64_encode($tag),
                'ciphertext'         => base64_encode($encrypted),
                'metadata'           => $metadata,
            ], JSON_THROW_ON_ERROR);

            // Guardar en cache con TTL largo (7 días — la purga se encarga del cleanup)
            Cache::put($this->cachePrefix . $ref, $envelope, now()->addDays(7));

        } catch (KeyVaultException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new KeyVaultException('Error almacenando en AWS KMS vault: ' . $e->getMessage(), 0, $e);
        }

        return $ref;
    }

    public function retrieve(string $ref): string
    {
        $envelopeJson = Cache::get($this->cachePrefix . $ref);

        if ($envelopeJson === null) {
            throw new KeyVaultException("Referencia KMS vault '{$ref}' no encontrada.");
        }

        try {
            $envelope = json_decode($envelopeJson, true, 512, JSON_THROW_ON_ERROR);
            $kms = $this->getKmsClient();

            // Descifrar data key con KMS
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
        Cache::forget($this->cachePrefix . $ref);
    }

    public function exists(string $ref): bool
    {
        return Cache::has($this->cachePrefix . $ref);
    }

    /**
     * @return \Aws\Kms\KmsClient
     */
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
