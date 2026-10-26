<?php

namespace App\Http\Controllers;

use App\Enums\PeriodState;
use App\Models\AdmissionPeriod;
use App\Models\ApplicationSetting;
use Illuminate\View\View;

class PublicPortalController extends Controller
{
    public function __invoke(): View
    {
        return view('public.home', [
            'period' => AdmissionPeriod::query()->where('is_public', true)->where('state', PeriodState::Published)->first(),
            'settings' => ApplicationSetting::query()->where('key', 'public')->first()->value ?? [],
        ]);
    }
}
