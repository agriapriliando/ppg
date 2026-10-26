<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdmissionConfigVersion;
use App\Models\AdmissionPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConfigurationHistoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        $data = $request->validate(['period_id' => ['nullable', 'integer', 'exists:admission_periods,id']]);

        return view('admin.configuration-history', [
            'periods' => AdmissionPeriod::query()->orderByDesc('id')->get(),
            'versions' => AdmissionConfigVersion::query()->with(['period', 'consentText'])->withCount(['applications', 'submissions'])->when(! empty($data['period_id']), fn ($query) => $query->where('admission_period_id', $data['period_id']))->orderByDesc('id')->paginate(20)->withQueryString(),
        ]);
    }
}
