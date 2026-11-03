<?php

namespace App\Http\Controllers;

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SubmitSkClaim;
use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Models\AdministrativeCase;
use App\Models\AdmissionPeriod;
use App\Models\Application;
use App\Models\ConsentRecord;
use App\Models\ConsentText;
use App\Models\SkClaim;
use App\Models\SkParticipant;
use App\Models\User;
use App\Models\WhatsappVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClaimsController extends Controller
{
    public function create(): View
    {
        return view('public.claim', ['periods' => AdmissionPeriod::query()->where('state', 'published')->orderByDesc('id')->get(), 'consent' => ConsentText::query()->where('purpose', 'claim')->latest('id')->first(), 'requestKey' => (string) Str::uuid()]);
    }

    public function store(Request $request, SubmitSkClaim $submit): RedirectResponse
    {
        $claim = $submit->handle($request->user(), $request->only(['period_id', 'name', 'identifier', 'whatsapp_number', 'request_key', 'consent_id', 'consent']));

        return redirect()->route('public.claim')->with('status', 'Permohonan diterima untuk pemeriksaan. Referensi: '.$claim->public_reference.'. Operator akan menindaklanjuti melalui kanal resmi. Permohonan belum memberikan akses dashboard.');
    }

    public function own(Request $request): View
    {
        return view('portal.claims-own', ['claims' => SkClaim::query()->where('requester_user_id', $request->user()?->id)->with(['period', 'responses'])->orderByDesc('id')->paginate(20)]);
    }

    public function index(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        $claims = SkClaim::query()->with(['period', 'candidate.assignedOperator', 'reviewOwner']);
        if (! $actor->isAdministrator()) {
            $claims->where(function ($query) use ($actor): void {
                $query->whereHas('candidate', fn ($candidate) => $candidate->visibleTo($actor))
                    ->orWhere(fn ($unmatched) => $unmatched->whereNull('candidate_sk_participant_id')->where('review_owner_id', $actor->id)->whereExists(fn ($membership) => $membership->selectRaw('1')->from('period_operators')->whereColumn('period_operators.admission_period_id', 'sk_claims.admission_period_id')->where('period_operators.user_id', $actor->id)->where('is_active', true)));
            });
        }

        $search = trim((string) $request->input('search'));
        if ($search !== '') {
            $claims->where(function ($query) use ($search): void {
                $query->where('submitted_name', 'like', "%{$search}%")
                    ->orWhere('whatsapp_number', 'like', "%{$search}%")
                    ->orWhere('submitted_identifier', 'like', "%{$search}%")
                    ->orWhere('public_reference', 'like', "%{$search}%");
            });
        }

        $status = $request->input('status');
        if (! empty($status)) {
            $claims->where('status', $status);
        }

        $periodId = $request->input('period_id');
        if (! empty($periodId)) {
            $claims->where('admission_period_id', $periodId);
        }

        return view('portal.claims', [
            'claims' => $claims->orderBy('status')->orderBy('created_at')->orderBy('id')->paginate(25)->withQueryString(),
            'periods' => AdmissionPeriod::query()->orderByDesc('id')->get(),
            'search' => $search,
            'status' => $status,
            'periodId' => $periodId,
        ]);
    }

    public function show(SkClaim $claim): View
    {
        abort_unless(Gate::allows('review', $claim), 404);

        $actor = auth()->user();
        $isStaff = $actor?->isAdministrator() || $actor?->role === UserRole::Operator;

        return view('portal.claim-detail', [
            'claim' => $claim->load(['period', 'candidate.assignedOperator', 'requester', 'responses', 'reviewOwner']),
            'operators' => $isStaff ? User::query()->where('role', UserRole::Operator)->where('account_state', AccountState::Active)->where('must_change_password', false)->whereIn('id', DB::table('period_operators')->where('admission_period_id', $claim->admission_period_id)->where('is_active', true)->select('user_id'))->orderBy('name')->get() : collect(),
            'candidates' => $isStaff ? SkParticipant::query()->where('admission_period_id', $claim->admission_period_id)->whereNull('claimed_user_id')->orderBy('name_on_sk')->limit(1000)->get() : collect(),
            'application' => $claim->status === 'approved' ? Application::query()->with('workspace.biodata')->where('sk_participant_id', $claim->candidate_sk_participant_id)->first() : null,
        ]);
    }

    public function approve(Request $request, SkClaim $claim, ApproveClaim $approve): RedirectResponse
    {
        Gate::authorize('review', $claim);
        $data = $request->validate(['version' => ['required', 'integer', 'min:0'], 'identity_checked' => ['accepted'], 'sk_checked' => ['accepted']]);
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        $approve->handle($actor, $claim, (int) $data['version']);

        return back()->with('status', 'Klaim disetujui dan satu pendaftaran resmi dibuat.');
    }

    public function candidate(Request $request, SkClaim $claim, RecordAudit $audit): RedirectResponse
    {
        abort_unless($request->user()?->isAdministrator() || $request->user()?->role === UserRole::Operator, 403);
        $data = $request->validate(['candidate_id' => ['required', 'integer', 'exists:sk_participants,id'], 'reason' => ['nullable', 'string', 'max:1000'], 'version' => ['required', 'integer', 'min:0']]);
        DB::transaction(function () use ($claim, $data, $request, $audit): void {
            AdmissionPeriod::query()->whereKey($claim->admission_period_id)->lockForUpdate()->firstOrFail();
            $participant = SkParticipant::query()->whereKey($data['candidate_id'])->where('admission_period_id', $claim->admission_period_id)->whereNull('claimed_user_id')->lockForUpdate()->firstOrFail();
            $locked = SkClaim::query()->lockForUpdate()->findOrFail($claim->id);
            if (in_array($locked->status, ['approved', 'cancelled'], true) || $locked->lock_version !== (int) $data['version']) {
                throw ValidationException::withMessages(['claim' => 'Klaim telah berubah. Muat ulang pemeriksaan.']);
            }
            $locked->forceFill(['candidate_sk_participant_id' => $participant->id, 'lock_version' => $locked->lock_version + 1])->save();
            $audit->handle($request->user(), 'Menetapkan kandidat kepesertaan', $locked, ['participant_id' => $participant->id], $data['reason'] ?? null);
        });

        return back()->with('status', 'Kandidat dipilih untuk pemeriksaan. Entri SK belum dikunci oleh klaim.');
    }

    public function note(Request $request, SkClaim $claim, RecordAudit $audit): RedirectResponse
    {
        Gate::authorize('review', $claim);
        $data = $request->validate(['status' => ['required', Rule::in(['needs_information', 'declined'])], 'public_note' => ['required', 'string', 'min:10', 'max:1000'], 'version' => ['required', 'integer', 'min:0']]);
        DB::transaction(function () use ($claim, $data, $request, $audit): void {
            AdmissionPeriod::query()->whereKey($claim->admission_period_id)->lockForUpdate()->firstOrFail();
            $locked = SkClaim::query()->lockForUpdate()->findOrFail($claim->id);
            Gate::authorize('review', $locked);
            if (in_array($locked->status, ['approved', 'cancelled'], true) || $locked->lock_version !== (int) $data['version']) {
                throw ValidationException::withMessages(['claim' => 'Klaim telah berubah. Muat ulang pemeriksaan.']);
            }
            $locked->forceFill(['status' => $data['status'], 'public_note' => $data['public_note'], 'reviewed_by' => $request->user()?->id, 'reviewed_at' => now(), 'lock_version' => $locked->lock_version + 1])->save();
            $audit->handle($request->user(), 'Mencatat tindak lanjut klaim', $locked, ['status' => $locked->status]);
        });

        return back()->with('status', 'Catatan pemeriksaan klaim disimpan.');
    }

    public function verifyWhatsapp(Request $request, SkClaim $claim, RecordAudit $audit): RedirectResponse
    {
        Gate::authorize('review', $claim);
        $data = $request->validate(['evidence' => ['required', 'string', 'min:10', 'max:1000', 'not_regex:/[0-9]{16}/'], 'confirmed' => ['accepted']]);
        DB::transaction(function () use ($claim, $request, $data, $audit): void {
            AdmissionPeriod::query()->whereKey($claim->admission_period_id)->lockForUpdate()->firstOrFail();
            $locked = SkClaim::query()->lockForUpdate()->findOrFail($claim->id);
            Gate::authorize('review', $locked);
            $application = $locked->status === 'approved' ? Application::query()->where('sk_participant_id', $locked->candidate_sk_participant_id)->first() : null;
            $number = $application?->workspace->biodata->whatsapp_number ?? $locked->whatsapp_number;
            WhatsappVerification::create(['claim_id' => $application === null ? $locked->id : null, 'workspace_id' => $application?->workspace_id, 'number' => $number, 'evidence_summary' => $data['evidence'], 'verified_by' => $request->user()?->id, 'verified_at' => now()]);
            $audit->handle($request->user(), 'Mencatat bukti WhatsApp aktif', $locked);
        });

        return back()->with('status', 'Bukti balasan nomor aktif telah dicatat.');
    }

    public function destroy(Request $request, SkClaim $claim, RecordAudit $audit): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        abort_unless($actor->isAdministrator() || $actor->role === UserRole::Operator, 403);
        abort_unless($claim->status === 'pending', 422, 'Hanya permohonan klaim yang menunggu pemeriksaan yang dapat dihapus.');

        DB::transaction(function () use ($actor, $claim, $audit): void {
            $locked = SkClaim::query()->lockForUpdate()->findOrFail($claim->id);
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['claim' => 'Klaim telah diproses atau berubah status.']);
            }

            $locked->responses()->delete();
            ConsentRecord::query()->where('claim_id', $locked->id)->delete();
            WhatsappVerification::query()->where('claim_id', $locked->id)->delete();
            AdministrativeCase::query()->where('claim_id', $locked->id)->delete();

            $audit->handle($actor, 'Menghapus permohonan klaim menunggu pemeriksaan', $locked, [
                'submitted_name' => $locked->submitted_name,
                'whatsapp_number' => $locked->whatsapp_number,
                'period_id' => $locked->admission_period_id,
            ]);

            $locked->delete();
        });

        return redirect()->route('claims.index')->with('status', 'Permohonan klaim berhasil dihapus.');
    }
}
