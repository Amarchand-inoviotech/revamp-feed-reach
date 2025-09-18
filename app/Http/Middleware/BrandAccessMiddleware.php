<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\BrandAccessToken;

class BrandAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get token from request (could be in header, query param, etc.)
        $accessKey = $request->header('X-Access-Token') ?? $request->query('access_token');

        if (!$accessKey) {
            return response()->json([
                'message' => 'Access token is required'
            ], 401);
        }

        // Find the token in database
        $accessToken = BrandAccessToken::with('brand')->where('token', $accessKey)
            ->where('expires_at', '>', now())
            ->orWhereNull('expires_at')
            ->first();

        if (!$accessToken) {
            throw new \Exception('Invalid or expired access token', 401);
        }

        // Get the brand domain from the token's brand
        $brandDomain = $accessToken->brand->domain;

        // Get the requesting domain
        $requestDomain = $request->getHost(); // or parse from Referer header if needed


        // Remove www. and protocol if present for comparison
        $cleanBrandDomain = str_replace(['www.', 'http://', 'https://'], '', $brandDomain);
        $cleanRequestDomain = str_replace(['www.', 'http://', 'https://'], '', $requestDomain);


        // Check if domains match
        if ($cleanBrandDomain !== $cleanRequestDomain) {

            //throw new \Exception('Access token is not valid for this domain', 403);
        }



        // Attach the brand to the request for later use if needed
        $accessToken->last_used_at = now();
        $accessToken->save();
        $request->merge(['brand' => $accessToken->brand]);

        return $next($request);
    }
}
