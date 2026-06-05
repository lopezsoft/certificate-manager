<?php

namespace App\Enums;

enum BillingTypeEnum: string
{
    case PREPAID  = 'PREPAID';
    case POSTPAID = 'POSTPAID';
}
