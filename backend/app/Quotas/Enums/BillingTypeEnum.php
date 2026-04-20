<?php

namespace App\Quotas\Enums;

enum BillingTypeEnum: string
{
    case PREPAID  = 'PREPAID';
    case POSTPAID = 'POSTPAID';
}

