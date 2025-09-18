<?php

namespace App\Enum;


enum NotificationTypeEnum: string
{
    case SMS = 'sms';
    case EMAIL = 'email';
    case BOTH = 'both';
}
