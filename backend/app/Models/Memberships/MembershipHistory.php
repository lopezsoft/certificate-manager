<?php

namespace App\Models\Memberships;

use App\Core\CoreModel;

/**
 * @method static create(array $array)
 */
class MembershipHistory extends CoreModel
{
    protected $table = 'membership_history';
    protected $fillable = [
        'company_id',
        'membership_plan_id',
        'activation_date',
        'lock_date',
        'payment',
        'payment_value',
        'active',
    ];
}
