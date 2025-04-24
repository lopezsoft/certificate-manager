<?php

namespace App\Models;

use App\Core\CoreModel;

/**
 * @method static create(array $data)
 */
class EmailSending extends CoreModel
{
    protected $table = 'email_sending';
    protected $fillable = [
        'company_id',
        'shipping_history_id',
        'emails',
        'email_to'
    ];
}
