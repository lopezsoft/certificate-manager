<?php

namespace App\Models\Memberships;

use App\Core\CoreModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @method static create(array $array)
 * @property mixed $company_id
 */
class CompanyMembership extends CoreModel
{
    use HasFactory;

    public $table   = "company_membership";

    protected   $fillable = [
        'company_id',
        'membership_plan_id',
        'activation_date',
        'lock_date',
        'active',
    ];
    protected $appends = [
        'consume',
        'additionalContent'
    ];
     public function membership(): BelongsTo
     {
         return $this->belongsTo(MembershipPlan::class, 'membership_plan_id', 'id');
     }
    public function getAdditionalContentAttribute(): Collection
    {
        return DB::table('membership_additional_content AS a')
            ->join('membership_content_plans AS b', 'a.content_plan_id', '=', 'b.id')
            ->select('a.id', 'a.content_plan_id', 'a.amount', 'b.document_name')
            ->where('a.company_id', $this->company_id)
            ->whereRaw("a.lock_date >= CURDATE()")
            ->get();
    }
     public function getConsumeAttribute(): array
     {
         return DB::select("CALL sp_select_membership_content(?)", [$this->company_id]);
     }
}
