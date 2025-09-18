<?php

namespace App\Http\Controllers\Api;

use App\Enum\LocaleEnum;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;

class GenericController extends Controller
{
    public function apiPrefixRoute(Request $request)
    {
        return successResponse(
            [
                'application' =>  [config('app.name') . ' application is LIVE'],
                'time' => Carbon::now($request->timezone)->format('Y-m-d h:i:s'),
                'timezone' => $request->timezone ?? config('app.timezone')


            ],
            'The APIs are live. You can now access them through the integrated application.',
            200
        );
    }

 public function localeLanguages(Request $request)
{
    // Define allowed language codes
    $allowedLocales = ['en', 'fr']; // Replace with your preferred locales

    // Filter only allowed locales
    $localeLanguages = array_values(array_map(function ($case) {
        return [
            'name' => ucwords(str_replace('_', ' ', strtolower($case->name))),
            'code' => $case->value,
        ];
    }, array_filter(LocaleEnum::cases(), function ($case) use ($allowedLocales) {
        return in_array($case->value, $allowedLocales);
    })));

    return successResponse($localeLanguages, 'Locale languages retrieved successfully', 200);
}
}
