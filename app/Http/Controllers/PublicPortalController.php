<?php

namespace App\Http\Controllers;

use App\Enums\PeriodState;
use App\Models\AdmissionPeriod;
use App\Models\ApplicationSetting;
use App\Models\PortalQuickLink;
use Illuminate\View\View;

class PublicPortalController extends Controller
{
    public function __invoke(): View
    {
        return view('public.home', [
            'period' => AdmissionPeriod::query()->where('is_public', true)->where('state', PeriodState::Published)->withCount(['periodDocuments as public_documents_count' => fn ($query) => $query->publicFiles()])->first(),
            'settings' => ApplicationSetting::query()->where('key', 'public')->first()->value ?? [],
            'quickLinks' => PortalQuickLink::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }
}
