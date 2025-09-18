<?php

namespace App\Enum;

enum UsernameBlockedEnum: string
{
    case ADMIN = 'admin';
    case USER = 'user';
    case SUPPORT = 'support';
    case  ALL = 'all';
    case TEST = 'test';
    case CAREER = 'career';
}
