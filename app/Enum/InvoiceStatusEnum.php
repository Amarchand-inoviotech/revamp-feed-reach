<?php

namespace App\Enum;


enum InvoiceStatusEnum: string
{
    case UNPAID = 'unpaid';
    case CANCELLED = 'cancelled';
    case PAID = 'paid';
    case REFUNDED = 'refunded';
    case CHARGEBACK = 'chargeback';
    case VOIDED = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Unpaid',
            self::CANCELLED => 'Cancelled',
            self::PAID => 'Paid',
            self::REFUNDED => 'Refunded',
            self::CHARGEBACK => 'Chargeback',
            self::VOIDED => 'Voided',
        };
    }


    public static function toArray(): array
    {
        $data = [];

        foreach (self::cases() as $case) {
            $data[$case->value] = $case->label();
        }

        return $data;
    }
}
