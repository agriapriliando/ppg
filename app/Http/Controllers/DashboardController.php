<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user !== null, 403);
        $request->validate(['period_id' => ['nullable', 'integer', 'exists:admission_periods,id']]);
        $periodId = $request->filled('period_id') ? $request->integer('period_id') : null;
        $applications = Application::query()->when($user->role === UserRole::Applicant, fn ($query) => $query->where('user_id', $user->id), fn ($query) => $query->whereHas('participant', fn ($participants) => $participants->visibleTo($user)))->when($periodId !== null, fn ($query) => $query->where('admission_period_id', $periodId));
        $counts = (clone $applications)->selectRaw('status,count(*) as total')->groupBy('status')->pluck('total', 'status');
        $totalSk = $user->role === UserRole::Applicant ? null : SkParticipant::query()->visibleTo($user)->when($periodId !== null, fn ($query) => $query->where('admission_period_id', $periodId))->count();

        return view('portal.dashboard', [
            'periods' => AdmissionPeriod::query()->where('state', 'published')->orderByDesc('id')->get(),
            'staffCount' => $user->isAdministrator() ? User::query()->whereIn('role', ['administrator', 'operator'])->where('account_state', 'active')->count() : null,
            'events' => AuditEvent::query()->with('actor')->when(! $user->isAdministrator(), fn ($query) => $query->where('actor_id', $user->id))->orderByDesc('id')->limit(5)->get(),
            'counts' => $counts, 'totalSk' => $totalSk, 'applicationCount' => (clone $applications)->count(), 'periodId' => $periodId,
            'ownApplications' => $user->role === UserRole::Applicant ? (clone $applications)->with('period')->orderByDesc('id')->get() : collect(),
        ]);
    }
}
