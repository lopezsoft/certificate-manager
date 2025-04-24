<?php

namespace App\Models\Email;

use App\Core\CoreModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailAttachment extends CoreModel
{
    public $timestamps = true;
    protected $table = 'email_attachments';

    protected $fillable = [
        'email_communication_id', 'file_name', 'file_path', 'mime_type'
    ];

    public function emailCommunication(): BelongsTo
    {
        return $this->belongsTo(EmailCommunication::class);
    }
}
