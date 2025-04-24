<?php

namespace App\Services\Mail;

class SMTPConfig
{
    public $host;
    public $port;
    public $username;
    public $password;
    public $encryption;

    public function __construct($host, $port, $username, $password, $encryption = 'tls')
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->encryption = $encryption;
    }
}
