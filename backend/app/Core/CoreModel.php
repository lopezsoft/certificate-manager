<?php

namespace App\Core;

use App\Queries\AuditTable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class CoreModel extends Model
{
    use HasFactory;
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    protected $hidden = [
        'timestamp'
    ];

    /**
     * Actualiza solo los campos que han cambiado.
     *
     * @param array $data Los datos a comparar y actualizar.
     * @return array Los campos que se han actualizado.
     */
    public function  updateOnlyChanged(Request $request, array $data): array
    {
        $dataToUpdate = [];

        foreach ($data as $key => $value) {
            if ($this->$key != $value) {
                $dataToUpdate[$key] = $value;
            }
        }

        if (!empty($dataToUpdate)) {
            $this->update($dataToUpdate);
            AuditTable::audit($request->ip(), $this->getTable(), 'UPDATE', $dataToUpdate);
        }

        return $dataToUpdate;
    }

    public function  updateOnlyChangedData(array $data): array
    {
        $dataToUpdate = [];

        foreach ($data as $key => $value) {
            if ($this->$key != $value) {
                $dataToUpdate[$key] = $value;
            }
        }

        if (!empty($dataToUpdate)) {
            $this->update($dataToUpdate);
        }

        return $dataToUpdate;
    }
}
