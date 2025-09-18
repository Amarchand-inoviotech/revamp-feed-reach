<?php

namespace App\Http\Middleware;

use App\Enum\UserGaurdEnum;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;

class UserGaurdMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->tokenCan("guard:" . UserGaurdEnum::USER->value)) {
            return $next($request);
        }
        throw new UnauthorizedException(trans('auth.not_authorized'));
    }
}
