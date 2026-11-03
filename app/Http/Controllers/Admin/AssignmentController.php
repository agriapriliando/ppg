<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Assignments\AssignOperators;
use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Enums\PeriodState;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AdmissionPeriod;
use App\Models\PeriodOperator;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['period_id' => ['nullable', 'integer', 'exists:admission_periods,id'], 'operators' => ['nullable', 'array'], 'operators.*' => ['integer', 'distinct', 'exists:users,id']]);
        $period = $request->filled('period_id') ? AdmissionPeriod::query()->whereKey($request->integer('period_id'))->firstOrFail() : null;
        $ids = array_map(intval(...), $data['operators'] ?? []);
        sort($ids);
        $operators = User::query()->where('role', UserRole::Operator)->where('account_state', AccountState::Active)->orderBy('id')->get();
        $unassigned = $period ? SkParticipant::query()->where('admission_period_id', $period->id)->where('roster_state', 'active')->whereNull('assigned_operator_id')->count() : 0;
        $totalParticipants = $period ? SkParticipant::query()->where('admission_period_id', $period->id)->where('roster_state', 'active')->count() : 0;
        $loads = $period ? SkParticipant::query()->where('admission_period_id', $period->id)->where('roster_state', 'active')->whereNotNull('assigned_operator_id')->selectRaw('assigned_operator_id, count(*) as total')->groupBy('assigned_operator_id')->pluck('total', 'assigned_operator_id') : SkParticipant::query()->where('roster_state', 'active')->whereNotNull('assigned_operator_id')->selectRaw('assigned_operator_id, count(*) as total')->groupBy('assigned_operator_id')->pluck('total', 'assigned_operator_id');
        $assignedOperatorIds = $period ? PeriodOperator::query()->where('admission_period_id', $period->id)->where('is_active', true)->pluck('user_id')->map(intval(...))->all() : [];
        sort($assignedOperatorIds);
        $selected = $request->has('operators') ? $ids : $assignedOperatorIds;

        $targetLoads = [];
        $isExpanded = false;
        if ($period && $ids !== []) {
            $count = count($ids);
            $isExpanded = count($ids) > count($assignedOperatorIds) || array_diff($ids, $assignedOperatorIds) !== [];

            if ($isExpanded || $unassigned === $totalParticipants) {
                foreach ($ids as $index => $id) {
                    $targetLoads[$id] = intdiv($totalParticipants, $count) + ($index < ($totalParticipants % $count) ? 1 : 0);
                }
            } else {
                foreach ($ids as $index => $id) {
                    $addition = intdiv($unassigned, $count) + ($index < ($unassigned % $count) ? 1 : 0);
                    $targetLoads[$id] = ($loads[$id] ?? 0) + $addition;
                }
            }
        }

        $redistribute = $isExpanded;
        $token = $period && $ids !== [] ? Crypt::encryptString(json_encode(['period_id' => $period->id, 'version' => $period->lock_version, 'ids' => $ids, 'redistribute' => $redistribute, 'expires' => now()->addHour()->timestamp], JSON_THROW_ON_ERROR)) : null;

        return view('admin.assignments', [
            'periods' => AdmissionPeriod::query()->where('state', '!=', 'archived')->orderByDesc('id')->get(),
            'period' => $period,
            'operators' => $operators,
            'selected' => $selected,
            'unassigned' => $unassigned,
            'totalParticipants' => $totalParticipants,
            'loads' => $loads,
            'targetLoads' => $targetLoads,
            'token' => $token,
        ]);
    }

    public function store(Request $request, AssignOperators $assign): RedirectResponse
    {
        $data = $request->validate(['preview_token' => ['required', 'string']]);
        try {
            $preview = json_decode(Crypt::decryptString($data['preview_token']), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw ValidationException::withMessages(['assignment' => 'Pratinjau tidak sah. Buat pratinjau baru.']);
        }
        if (! is_array($preview) || ($preview['expires'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages(['assignment' => 'Pratinjau sudah kedaluwarsa. Buat pratinjau baru.']);
        }
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        $assign->handle($actor, $preview['period_id'], $preview['version'], $preview['ids'], (bool) ($preview['redistribute'] ?? false));

        return redirect()->route('admin.assignments', ['period_id' => $preview['period_id']])->with('status', 'Operator periode disimpan dan pembagian peserta berhasil diperbarui.');
    }

    public function transfer(Request $request, SkParticipant $participant, RecordAudit $audit): RedirectResponse
    {
        $data = $request->validate(['operator_id' => ['required', 'integer', 'exists:users,id'], 'version' => ['required', 'integer', 'min:0'], 'reason' => ['required', 'string', 'min:10', 'max:1000']]);
        DB::transaction(function () use ($request, $participant, $data, $audit): void {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($participant->admission_period_id);
            $operator = User::query()->where('id', $data['operator_id'])->where('role', UserRole::Operator)->where('account_state', AccountState::Active)->lockForUpdate()->first();
            if ($operator === null || ! PeriodOperator::query()->where('admission_period_id', $period->id)->where('user_id', $operator->id)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['operator_id' => 'Operator tujuan harus aktif dan terdaftar pada periode.']);
            }
            $locked = SkParticipant::query()->lockForUpdate()->findOrFail($participant->id);
            if ($locked->lock_version !== (int) $data['version'] || $period->state === PeriodState::Archived) {
                throw ValidationException::withMessages(['version' => 'Data telah berubah atau periode diarsipkan. Muat ulang sebelum transfer.']);
            }
            if ($locked->assigned_operator_id === $operator->id) {
                throw ValidationException::withMessages(['operator_id' => 'Pilih operator tujuan yang berbeda.']);
            }
            $old = $locked->assigned_operator_id;
            $locked->assignedOperator()->associate($operator);
            $locked->lock_version++;
            $locked->save();
            $period->lock_version++;
            $period->save();
            DB::table('assignment_events')->insert(['sk_participant_id' => $locked->id, 'old_operator_id' => $old, 'new_operator_id' => $operator->id, 'actor_id' => $request->user()?->id, 'mode' => 'transfer', 'reason' => $data['reason'], 'created_at' => now()]);
            $audit->handle($request->user(), 'Memindahkan penugasan', $locked, ['old_operator_id' => $old, 'new_operator_id' => $operator->id], $data['reason']);
        });

        return back()->with('status', 'Penugasan berhasil dipindahkan. Akses operator lama telah dicabut.');
    }
}
