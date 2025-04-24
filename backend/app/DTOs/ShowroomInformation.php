<?php

namespace App\DTOs;

class ShowroomInformation
{
    public mixed $showroom;
    public mixed $showroomAddress;
    public mixed $dataShow;

    public function __construct(array $data)
    {
        $this->showroom = $data['showroom'] ?? null;
        $this->showroomAddress = $data['showroomAddress'] ?? null;
        $this->dataShow = $data['dataShow'] ?? [];
    }
}
