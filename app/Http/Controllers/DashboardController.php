<?php

namespace App\Http\Controllers;

use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantDocument;
use App\Models\ApplicantWorkspace;
use App\Models\Application;
use App\Models\ApplicationSetting;
use App\Models\AuditEvent;
use App\Models\PortalQuickLink;
use App\Models\SkClaim;
use App\Models\SkParticipant;
use App\Models\User;
use App\Support\UserPhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user !== null, 403);
        $request->validate(['period_id' => ['nullable', 'integer', 'exists:admission_periods,id']]);
        $periodId = $request->filled('period_id') ? $request->integer('period_id') : null;

        $applicationsQuery = Application::query()
            ->when($user->role === UserRole::Applicant, fn ($query) => $query->where('user_id', $user->id), fn ($query) => $query->whereHas('participant', fn ($participants) => $participants->visibleTo($user)))
            ->when($periodId !== null, fn ($query) => $query->where('admission_period_id', $periodId));

        $counts = (clone $applicationsQuery)->selectRaw('status,count(*) as total')->groupBy('status')->pluck('total', 'status');
        $totalSk = $user->role === UserRole::Applicant ? null : SkParticipant::query()->visibleTo($user)->when($periodId !== null, fn ($query) => $query->where('admission_period_id', $periodId))->count();

        $periods = AdmissionPeriod::query()->where('state', 'published')->orderByDesc('id')->get();
        $workspaces = collect();
        $claims = collect();
        $ownApplications = collect();
        $operatorContact = null;
        $operatorContactMessage = 'Operator belum ditugaskan.';

        if ($user->role === UserRole::Applicant) {
            $workspaces = ApplicantWorkspace::query()
                ->where('user_id', $user->id)
                ->withCount(['documents' => fn ($query) => $query->where('status', ApplicantDocument::STATUS_AVAILABLE)])
                ->get()
                ->keyBy('admission_period_id');

            $ownApplications = (clone $applicationsQuery)->with('period')->orderByDesc('id')->get();

            $claims = SkClaim::query()
                ->where('requester_user_id', $user->id)
                ->with(['period', 'responses'])
                ->orderByDesc('id')
                ->get();

            $contactPeriodId = $periodId ?? $ownApplications->first()->admission_period_id ?? $periods->first()?->id;
            $participant = SkParticipant::query()->where('claimed_user_id', $user->id)
                ->when($contactPeriodId !== null, fn ($query) => $query->where('admission_period_id', $contactPeriodId))
                ->with('assignedOperator')->orderByDesc('id')->first();
            $operator = $participant?->assignedOperator;
            if ($operator !== null) {
                $operatorContactMessage = 'Kontak operator belum tersedia. Hubungi pengelola.';
                if ($operator->role === UserRole::Operator && $operator->account_state === AccountState::Active) {
                    try {
                        $phone = UserPhoneNumber::normalize($operator->phone_number);
                    } catch (ValidationException) {
                        $phone = null;
                    }
                    if ($phone !== null) {
                        $operatorContact = ['name' => $operator->name, 'url' => 'https://wa.me/'.$phone.'?text='.rawurlencode('Shalom, Izin bertanya Bapak/Ibu Operator :')];
                    }
                }
            }
        }

        return view('portal.dashboard', [
            'settings' => ApplicationSetting::query()->where('key', 'public')->first()->value ?? [],
            'periods' => $periods,
            'staffCount' => $user->isAdministrator() ? User::query()->whereIn('role', ['administrator', 'operator'])->where('account_state', 'active')->count() : null,
            'events' => AuditEvent::query()->with('actor')->orderByDesc('id')->limit(5)->get(),
            'counts' => $counts,
            'totalSk' => $totalSk,
            'applicationCount' => (clone $applicationsQuery)->count(),
            'periodId' => $periodId,
            'ownApplications' => $ownApplications,
            'workspaces' => $workspaces,
            'claims' => $claims,
            'quickLinks' => PortalQuickLink::query()->visibleOn('dashboard')->orderBy('sort_order')->orderBy('id')->get(),
            'operatorContact' => $operatorContact,
            'operatorContactMessage' => $operatorContactMessage,
        ]);
    }
}
