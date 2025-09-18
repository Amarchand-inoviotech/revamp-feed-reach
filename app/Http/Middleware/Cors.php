<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Handle preflight OPTIONS request
        if ($request->isMethod('OPTIONS')) {
            $response = new Response('', 200);
        } else {
            // Pass the request to the next middleware
            $response = $next($request);
        }

        // Get CORS configuration
        $config = config('cors');
        $origin = $request->header('Origin');

        // For debugging - always allow all origins in development
        if (app()->environment('local')) {
            $response->headers->set('Access-Control-Allow-Origin', $origin ?: '*');
            $response->headers->set('Access-Control-Allow-Methods', implode(', ', $config['allowed_methods']));
            $response->headers->set('Access-Control-Allow-Headers', implode(', ', $config['allowed_headers']));
            if ($config['supports_credentials']) {
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }
            $response->headers->set('Access-Control-Max-Age', (string) $config['max_age']);
        }
        // Normal CORS check for non-development environments
        elseif (in_array('*', $config['allowed_origins']) || in_array($origin, $config['allowed_origins'])) {
            // For '*', we need to set the actual origin
            $response->headers->set('Access-Control-Allow-Origin', in_array('*', $config['allowed_origins']) ? '*' : $origin);

            // Set allowed methods
            $response->headers->set('Access-Control-Allow-Methods', implode(', ', $config['allowed_methods']));

            // Set allowed headers
            $response->headers->set('Access-Control-Allow-Headers', implode(', ', $config['allowed_headers']));

            // Set exposed headers if any
            if (!empty($config['exposed_headers'])) {
                $response->headers->set('Access-Control-Expose-Headers', implode(', ', $config['exposed_headers']));
            }

            // Set credentials support
            if ($config['supports_credentials']) {
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }

            // Set max age for preflight caching
            $response->headers->set('Access-Control-Max-Age', (string) $config['max_age']);
        }

        return $response;
    }
}
