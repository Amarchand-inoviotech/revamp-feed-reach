<?php

namespace App\Enum;


enum PaymentStatusEnum: string
{
    case INITIATED = 'initiated';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case VOIDED = 'voided';
    case REFUND_PARTIAL = 'refund_partial';
    case REFUND_FULL = 'refund_full';
    case CHARGEBACK = 'chargeback';
}
