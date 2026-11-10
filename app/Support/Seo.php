<?php

namespace App\Support;

use App\Enums\PeriodState;
use App\Models\AdmissionPeriod;
use Illuminate\Http\Request;

class Seo
{
    public static function url(string $path = ''): string
    {
        return rtrim((string) config('seo.url'), '/').'/'.ltrim($path, '/');
    }

    public static function indexable(Request $request): bool
    {
        if (! config('seo.indexable')) {
            return false;
        }
        if ($request->routeIs('home', 'guide')) {
            return true;
        }
        $period = $request->route('period');

        return $request->routeIs('public.period-documents.index')
            && $period instanceof AdmissionPeriod && $period->is_public && $period->state === PeriodState::Published;
    }

    public static function robots(Request $request): string
    {
        return self::indexable($request)
            ? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1'
            : 'noindex, follow';
    }
}
