<?php

namespace App\Enum;


enum OtpTypeEnum: string
{
    case EMAIL = 'email';
    case PHONE = 'phone';
    case FORGOT_PASSWORD = 'forgot-password';
    case TWO_FACTOR = 'two-factor';
}
