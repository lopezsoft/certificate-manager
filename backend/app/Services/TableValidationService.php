<?php

namespace App\Services;

use App\Queries\ShowColumns;
use Illuminate\Support\Facades\Validator;
class TableValidationService
{
    public function generateValidator(array $data, string $tableName): \Illuminate\Validation\Validator
    {
        $columns = $this->getTableColumns($tableName);
        $rules = $this->generateValidationRules($columns);

        return Validator::make($data, $rules);
    }

    private function getTableColumns(string $tableName): array
    {
        return ShowColumns::getColumns($tableName);
    }

    private function generateValidationRules($columns): array
    {
        $rules = [];

        foreach ($columns as $column) {
            if ($this->isRequired($column)) {
                $rules[$column->Field] = $this->determineValidationRules($column);
            }
        }
        return $rules;
    }

    private function isRequired($column): bool
    {
        // Implementar lógica para determinar si la columna es obligatoria
        return $column->Null === 'NO' && ($column->Extra !== 'auto_increment' || $column->Key !== 'PRI') &&
            $column->Default === null && (!preg_match('/^nextval/', $column->Extra) ||
            !preg_match('/^uuid/', $column->Extra) || !preg_match('/^CURRENT_TIMESTAMP/', $column->Default) ||
            !$column->Type == 'timestamp');
    }

    private function determineValidationRules($column): array
    {
        $rules = [];

        // Extraer el tipo de la columna y, si es aplicable, su longitud
        preg_match('/(\w+)(?:\((\d+)\))?/', $column->Type, $matches);
        $type   = $matches[1];
        $length = isset($matches[2]) ? $matches[2] : null;

        // Basar las reglas en el tipo de columna
        switch ($type) {
            case 'int':
            case 'bigint':
            case 'smallint':
            case 'tinyint':
                $rules[] = 'integer';
                if ($length) {
                    $rules[] = "digits_between:1,$length";
                }
                break;

            case 'varchar':
            case 'char':
                $rules[] = 'string';
                if ($length) {
                    $rules[] = "max:$length";
                }
                break;

            case 'text':
                $rules[] = 'string';
                break;

            case 'date':
                $rules[] = 'date';
                break;

            case 'datetime':
                $rules[] = 'date_format:Y-m-d H:i:s';
                break;
        }
        array_unshift($rules, 'required');
        return $rules;
    }

}

