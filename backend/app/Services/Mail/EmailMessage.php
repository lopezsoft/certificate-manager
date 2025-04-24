<?php

namespace App\Services\Mail;

class EmailMessage
{
    public string $from;
    public string $to;
    public string $subject;
    public mixed $htmlBody;
    public array $cc = [];
    public array $bcc = [];
    public array $attachments = [];

    public function __construct($from, $to, $subject, $htmlBody)
    {
        $this->from = $from;
        $this->to = $to;
        $this->subject = $subject;
        $this->htmlBody = $htmlBody;
    }

    public function addCc($cc): static
    {
        $this->cc[] = $cc;
        return $this;
    }

    public function addBcc($bcc): static
    {
        $this->bcc[] = $bcc;
        return $this;
    }

    public function addAttachment($filePath): static
    {
        $this->attachments[] = $filePath;
        return $this;
    }
}
