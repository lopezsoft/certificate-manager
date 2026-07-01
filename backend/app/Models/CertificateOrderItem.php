<?php

namespace App\Models;

use App\Models\CertificateRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateOrderItem extends Model
{
    protected $table = 'certificate_order_items';

    protected $fillable = [
        'certificate_order_id',
        'certificate_request_id',
        'status',
        'vigencia',
    ];

    protected $casts = [
        'vigencia' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(CertificateOrder::class, 'certificate_order_id');
    }

    public function certificateRequest(): BelongsTo
    {
        return $this->belongsTo(CertificateRequest::class, 'certificate_request_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    public function isUsed(): bool
    {
        return $this->status === 'USED';
    }
}
