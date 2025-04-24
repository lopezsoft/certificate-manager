<?php

namespace App\Models\FileManagers;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @method static create(array $array)
 * @method static find(mixed $fileId)
 * @method static where(string $string, $id)
 * @property mixed $state
 */
class FileManager extends Model
{
    use HasFactory;

    /**
     * @var string[]
     */
    protected $fillable = [
        'user_id', 'file_name', 'extension_file', 'mime_type', 'file_size',
        'last_modified', 'file_path', 'file_description', 'uuid', 'url', 'state'
    ];

    protected $appends = ['state_string'];
    protected static function boot() {
        parent::boot();

        static::creating(function ($fileManager) {
            $fileManager->uuid =  Str::uuid();
        });
    }

    public function getStateStringAttribute(): string
    {
        $state = $this->state;
        $stateString = '';
        switch ($state) {
            case 0:
                $stateString = 'Error generando archivo';
                break;
            case 1:
                $stateString = 'El archivo se esta generando, vuelva mas tarde';
                break;
            case 2:
                $stateString = 'Archivo listo, descárguelo';
                break;
            case 3:
                $stateString = 'Archivo borrado';
                break;
        }
        return $stateString;
    }
}
