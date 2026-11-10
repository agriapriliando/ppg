<?php

namespace App\Http\Middleware;

use App\Support\Seo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SeoHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Resolve route bindings before deciding whether a period is public.
        $response = $next($request);
        if (Seo::indexable($request) && $response->isSuccessful()
            && config('seo.redirect_canonical') && $request->isMethodSafe()) {
            $canonical = Seo::url($request->path() === '/' ? '' : $request->path());
            if (rtrim($request->url(), '/') !== rtrim($canonical, '/')) {
                $query = $request->getQueryString();

                return redirect()->away($canonical.($query ? '?'.$query : ''), 301);
            }
        }
        if (! Seo::indexable($request)) {
            $response->headers->set('X-Robots-Tag', 'noindex, follow');
        }

        return $response;
    }
}
