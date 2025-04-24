<?php

namespace App\Models\Memberships;

use App\Core\CoreModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MembershipPlan extends CoreModel
{
    protected $table = 'membership_plans';
    protected $fillable = [
        'membership_id',
        'membership_name',
        'payment',
        'monthly_price',
        'annual_price',
    ];
    protected $appends = [
        'content'
    ];

    public function getContentAttribute(): Collection
    {
        return DB::table('membership_amount_content_plans AS a')
            ->join('membership_content_plans AS b', 'a.content_plan_id', '=', 'b.id')
            ->select('a.id', 'a.content_plan_id', 'a.amount', 'b.document_name')
            ->where('a.plan_id', $this->id)
            ->get();
    }
}
