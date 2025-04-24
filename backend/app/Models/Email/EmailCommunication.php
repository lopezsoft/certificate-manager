<?php

namespace App\Models\Email;

use App\Core\CoreModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailCommunication extends CoreModel
{
    public $timestamps = true;
    protected $table = 'email_communications';

    protected $fillable = [
        'sender', 'from_address', 'sent_at', 'recipient', 'subject', 'body', 'opened', 'click_count'
    ];

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }
}
