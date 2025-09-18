<?php

namespace App\Enum;


enum BillingCycleEnum: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';

    case BI_QUARTERLY = 'bi-quarterly';
    case YEARLY = 'yearly';
}
