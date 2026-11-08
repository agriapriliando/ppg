<?php

namespace App\Http\Controllers;

use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Enums\PeriodState;
use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\SkClaim;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClaimFollowupController extends Controller
{
    public function respond(Request $request, SkClaim $claim, RecordAudit $audit): RedirectResponse
    {
        abort_unless($claim->requester_user_id === $request->user()?->id, 404);
        $data = $request->validate(['version' => ['required', 'integer', 'min:0'], 'message' => ['required', 'string', 'min:10', 'max:1000', 'not_regex:/[0-9]{16}/']]);
        DB::transaction(function () use ($request, $claim, $data, $audit): void {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($claim->admission_period_id);
            $locked = SkClaim::query()->lockForUpdate()->findOrFail($claim->id);
            abort_unless($locked->requester_user_id === $request->user()?->id, 404);
            if ($period->state === PeriodState::Archived || $locked->status !== 'needs_information' || $locked->lock_version !== (int) $data['version']) {
                throw ValidationException::withMessages(['claim' => 'Klaim telah berubah atau tidak menerima informasi tambahan. Muat ulang halaman.']);
            }
            $locked->responses()->create(['user_id' => $request->user()?->id, 'message' => $data['message']]);
            $locked->forceFill(['status' => 'pending', 'lock_version' => $locked->lock_version + 1])->save();
            $audit->handle($request->user(), 'Mengirim informasi tambahan klaim', $locked);
        });

        return back()->with('status', 'Informasi tambahan diterima. Klaim kembali menunggu pemeriksaan.');
    }

    public function cancel(Request $request, SkClaim $claim, RecordAudit $audit): RedirectResponse
    {
        abort_unless($claim->requester_user_id === $request->user()?->id, 404);
        $data = $request->validate(['version' => ['required', 'integer', 'min:0'], 'confirmed' => ['accepted']]);
        DB::transaction(function () use ($request, $claim, $data, $audit): void {
            AdmissionPeriod::query()->lockForUpdate()->findOrFail($claim->admission_period_id);
            $locked = SkClaim::query()->lockForUpdate()->findOrFail($claim->id);
            abort_unless($locked->requester_user_id === $request->user()?->id, 404);
            if (! in_array($locked->status, ['pending', 'needs_information'], true) || $locked->lock_version !== (int) $data['version']) {
                throw ValidationException::withMessages(['claim' => 'Klaim telah berubah atau tidak dapat dibatalkan. Muat ulang halaman.']);
            }
            $locked->forceFill(['status' => 'cancelled', 'lock_version' => $locked->lock_version + 1])->save();
            $audit->handle($request->user(), 'Membatalkan permohonan klaim sendiri', $locked);
        });

        return back()->with('status', 'Permohonan klaim dibatalkan.');
    }

    public function delegate(Request $request, SkClaim $claim, RecordAudit $audit): RedirectResponse
    {
        abort_unless($request->user()?->isAdministrator() || $request->user()?->role === UserRole::Operator, 403);
        $data = $request->validate(['version' => ['required', 'integer', 'min:0'], 'operator_id' => ['required', 'integer'], 'reason' => ['required', 'string', 'min:10', 'max:1000']]);
        DB::transaction(function () use ($request, $claim, $data, $audit): void {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($claim->admission_period_id);
            $locked = SkClaim::query()->lockForUpdate()->findOrFail($claim->id);
            if ($period->state === PeriodState::Archived || $locked->candidate_sk_participant_id !== null || ! in_array($locked->status, ['pending', 'needs_information'], true) || $locked->lock_version !== (int) $data['version']) {
                throw ValidationException::withMessages(['claim' => 'Delegasi hanya untuk klaim aktif tanpa kandidat. Muat ulang halaman.']);
            }
            $operator = User::query()->whereKey($data['operator_id'])->where('role', UserRole::Operator)->where('account_state', AccountState::Active)->where('must_change_password', false)->lockForUpdate()->first();
            if ($operator === null) {
                throw ValidationException::withMessages(['operator_id' => 'Pilih akun Operator yang aktif.']);
            }
            $oldOwner = $locked->review_owner_id;
            $locked->forceFill(['review_owner_id' => $operator->id, 'lock_version' => $locked->lock_version + 1])->save();
            $audit->handle($request->user(), 'Mendelegasikan pemeriksaan klaim ambigu', $locked, ['old_operator_id' => $oldOwner, 'operator_id' => $operator->id], $data['reason']);
        });

        return back()->with('status', 'Penanggung jawab pemeriksaan klaim disimpan.');
    }
}
