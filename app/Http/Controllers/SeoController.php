<?php

namespace App\Http\Controllers;

use App\Enums\PeriodState;
use App\Models\AdmissionPeriod;
use App\Support\Seo;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = [Seo::url(), Seo::url('panduan')];
        foreach (AdmissionPeriod::query()->where('is_public', true)->where('state', PeriodState::Published)->orderBy('id')->pluck('id') as $id) {
            $urls[] = Seo::url(route('public.period-documents.index', $id, false));
        }

        return response()->view('public.sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $rules = config('seo.indexable') ? "User-agent: *\nAllow: /\n" : "User-agent: *\nDisallow: /\n";

        return response($rules."\nSitemap: ".Seo::url('sitemap.xml')."\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
