<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HTTPStrictTransportSecurity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->secure() && app()->environment('production')) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubdomains');

            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $response;
    }
}
