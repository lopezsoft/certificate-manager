<?php

namespace App\Models;

use App\Core\CoreModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Versión publicada de los Términos y Condiciones.
 *
 * Inmutable una vez publicada: `content` y `content_hash` son la evidencia
 * del texto exacto que aceptó cada usuario. Para cambiar el texto se publica
 * una NUEVA versión (php artisan terms:publish).
 */
class TermsVersion extends CoreModel
{
    public $table      = 'terms_versions';
    public $timestamps = true;

    protected $fillable = [
        'version', 'published_at', 'source_url', 'content_hash', 'content', 'is_current',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_current'   => 'boolean',
    ];

    /** El snapshot íntegro no viaja en respuestas JSON por defecto. */
    protected $hidden = ['content'];

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public static function current(): ?self
    {
        return static::query()->current()->orderByDesc('published_at')->first();
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(TermsAcceptance::class, 'terms_version_id');
    }

    /**
     * Normaliza el texto antes de hashearlo para que diferencias triviales
     * de saltos de línea o espacios no produzcan hashes distintos.
     */
    public static function normalizeContent(string $content): string
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = preg_replace('/[ \t]+/', ' ', $content) ?? $content;
        $content = preg_replace('/\n{3,}/', "\n\n", $content) ?? $content;

        return trim($content);
    }

    public static function hashContent(string $normalizedContent): string
    {
        return hash('sha256', $normalizedContent);
    }
}
