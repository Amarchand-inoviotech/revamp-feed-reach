<?php

namespace App\Enum;


enum DevicePlatformEnum: string
{
    case IOS = 'ios';
    case ANDROID = 'android';
    case DESKTOP = 'desktop';
    case MAC = "mac";
    case WEB = 'web';
}
