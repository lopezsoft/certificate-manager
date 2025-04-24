<?php

namespace App\Models\Types;

use App\Core\CoreModel;

/**
 * @property mixed $id
 * @property mixed $prefix
 * @property mixed $voucher_name
 * @property mixed $cufe_algorithm
 * @property mixed $code
 * @method static find(int $int)
 * @method static create(array $array)
 */
class TypeDocument extends CoreModel
{

    public $table   = 'accounting_documents';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code', 'voucher_name', 'cufe_algorithm', 'prefix',
    ];
}
