<?php

declare(strict_types=1);

namespace App\Modules\Viafirma\Infrastructure\KeyVault;

use App\Modules\Viafirma\Domain\Contracts\KeyVault;
use App\Modules\Viafirma\Domain\Exceptions\KeyVaultException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Implementación local del {@see KeyVault} basada en Laravel `Crypt`
 * (AES-256-CBC con HMAC SHA-256 — APP_KEY del proyecto).
 *
 * Para producción se usará {@see AwsKmsKeyVault} (Sprint 5, V-501).
 *
 * Estructura en disco:
 *   {vaultPath}/{ref}.bin    → blob cifrado
 *
 * SEGURIDAD:
 *  - El blob cifrado NUNCA se loguea.
 *  - El método `destroy()` sobrescribe el archivo antes de borrarlo (best-effort).
 */
final class EncryptedLocalKeyVault implements KeyVault
{
    public function __construct(
        private readonly Filesystem $disk,
        private readonly Encrypter $crypt,
        private readonly string $vaultPath,
    ) {}

    public function store(string $material, array $metadata = []): string
    {
        if ($material === '') {
            throw new KeyVaultException('No se puede almacenar material vacío.');
        }

        $ref = Str::ulid()->toBase32() . '_' . bin2hex(random_bytes(4));
        $path = $this->pathFor($ref);

        try {
            $encrypted = $this->crypt->encryptString($material);
        } catch (\Throwable $e) {
            throw new KeyVaultException('Fallo al cifrar material para vault: ' . $e->getMessage(), 0, $e);
        }

        if (!$this->disk->put($path, $encrypted)) {
            throw new KeyVaultException("No se pudo escribir el archivo cifrado en '{$path}'.");
        }

        return $ref;
    }

    public function retrieve(string $ref): string
    {
        $path = $this->pathFor($ref);
        if (!$this->disk->exists($path)) {
            throw new KeyVaultException("La referencia '{$ref}' no existe en el vault.");
        }

        $blob = $this->disk->get($path);
        if ($blob === null || $blob === false) {
            throw new KeyVaultException("No se pudo leer el archivo del vault: '{$path}'.");
        }

        try {
            return $this->crypt->decryptString($blob);
        } catch (\Throwable $e) {
            throw new KeyVaultException('Fallo al descifrar material del vault: ' . $e->getMessage(), 0, $e);
        }
    }

    public function destroy(string $ref): void
    {
        $path = $this->pathFor($ref);
        if (!$this->disk->exists($path)) {
            return;
        }

        // Best-effort: overwrite + delete. En filesystems locales reduce la recuperación
        // por undelete; en S3 el versionado puede aún conservar copia (gestionar por lifecycle).
        try {
            $size = (int) $this->disk->size($path);
            if ($size > 0) {
                $this->disk->put($path, str_repeat("\0", min($size, 1024 * 1024)));
            }
        } catch (\Throwable) {
            // ignorar — el delete siguiente es lo importante
        }

        $this->disk->delete($path);
    }

    public function exists(string $ref): bool
    {
        return $this->disk->exists($this->pathFor($ref));
    }

    private function pathFor(string $ref): string
    {
        // Saneamiento defensivo: la ref nunca debería contener path traversal.
        if (preg_match('/[^A-Za-z0-9_\-]/', $ref) === 1) {
            throw new KeyVaultException("Referencia de vault inválida: '{$ref}'.");
        }
        return rtrim($this->vaultPath, '/') . '/' . $ref . '.bin';
    }
}

