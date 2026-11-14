<?php

namespace App\Http\Controllers;

use App\Enums\PeriodState;
use App\Models\AdmissionPeriod;
use App\Models\ApplicationSetting;
use App\Models\PortalQuickLink;
use App\Support\PublicSkParticipantStatistics;
use Illuminate\Http\Response;

class PublicPortalController extends Controller
{
    public function __invoke(PublicSkParticipantStatistics $statistics): Response
    {
        return response()->view('public.home', [
            'skStatistics' => $statistics->get(),
            'period' => AdmissionPeriod::query()->where('is_public', true)->where('state', PeriodState::Published)->withCount(['periodDocuments as public_documents_count' => fn ($query) => $query->publicFiles()])->first(),
            'settings' => ApplicationSetting::query()->where('key', 'public')->first()->value ?? [],
            'quickLinks' => PortalQuickLink::query()->visibleOn('homepage')->orderBy('sort_order')->orderBy('id')->get(),
        ])->header('Cache-Control', 'no-store, private');
    }
}
