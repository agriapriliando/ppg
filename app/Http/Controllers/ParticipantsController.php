<?php

namespace App\Http\Controllers;

use App\Actions\RecordAudit;
use App\Enums\PeriodState;
use App\Models\AdmissionPeriod;
use App\Models\PeriodOperator;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ParticipantsController extends Controller
{
    public function condition(Request $request, SkParticipant $participant, RecordAudit $audit): RedirectResponse
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        $data = $request->validate(['version' => ['required', 'integer', 'min:0'], 'roster_state' => ['required', Rule::in(['active', 'withdrawn'])], 'admin_hold' => ['required', 'boolean'], 'reason' => ['required', 'string', 'min:20', 'max:1000']]);
        DB::transaction(function () use ($request, $participant, $data, $audit): void {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($participant->admission_period_id);
            $locked = SkParticipant::query()->lockForUpdate()->findOrFail($participant->id);
            if ($period->state === PeriodState::Archived || $locked->lock_version !== (int) $data['version']) {
                throw ValidationException::withMessages(['condition' => 'Kepesertaan telah berubah atau periode diarsipkan. Muat ulang sebelum melanjutkan.']);
            }
            $locked->forceFill(['roster_state' => $data['roster_state'], 'admin_hold' => (bool) $data['admin_hold'], 'hold_reason' => $data['reason'], 'lock_version' => $locked->lock_version + 1])->save();
            $period->lock_version++;
            $period->save();
            $audit->handle($request->user(), 'Memperbarui kondisi kepesertaan SK', $locked, ['roster_state' => $locked->roster_state, 'admin_hold' => $locked->admin_hold], $data['reason']);
        });

        return back()->with('status', 'Kondisi kepesertaan diperbarui. Riwayat pendaftaran tetap disimpan.');
    }

    public function index(Request $request): View
    {
        $data = $request->validate(['period_id' => ['nullable', 'integer', 'exists:admission_periods,id'], 'search' => ['nullable', 'string', 'max:100']]);
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        $search = $data['search'] ?? '';

        return view('portal.participants', [
            'participants' => SkParticipant::query()->visibleTo($actor)->with(['assignedOperator', 'period'])->when(! empty($data['period_id']), fn ($query) => $query->where('admission_period_id', $data['period_id']))->when($search !== '', fn ($query) => $query->where(fn ($sub) => $sub->where('name_on_sk', 'like', '%'.$search.'%')->orWhere('official_participant_id', 'like', '%'.$search.'%')))->orderBy('sk_number')->orderBy('sk_order')->orderBy('id')->paginate(25)->withQueryString(),
            'periods' => AdmissionPeriod::query()->orderByDesc('id')->get(),
            'search' => $search,
            'periodId' => $data['period_id'] ?? '',
        ]);
    }

    public function show(SkParticipant $participant): View
    {
        abort_unless(Gate::allows('view', $participant), 404);

        return view('portal.participant', [
            'participant' => $participant->load(['assignedOperator', 'period']),
            'operators' => User::query()->where('role', 'operator')->where('account_state', 'active')->whereIn('id', PeriodOperator::query()->where('admission_period_id', $participant->admission_period_id)->where('is_active', true)->select('user_id'))->orderBy('name')->get(),
        ]);
    }
}
