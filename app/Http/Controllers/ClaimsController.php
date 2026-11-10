<?php

namespace App\Http\Controllers;

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\CancelClaimApproval;
use App\Actions\Admissions\DeleteWhatsappClaimAccount;
use App\Actions\Admissions\IssueClaimCredentials;
use App\Actions\Admissions\LookupPublicClaimParticipant;
use App\Actions\Admissions\SubmitSkClaim;
use App\Actions\Admissions\UpdateClaimIdentity;
use App\Actions\Admissions\UpdateClaimLoginId;
use App\Actions\Admissions\UpdateClaimWhatsapp;
use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Models\AdministrativeCase;
use App\Models\AdmissionPeriod;
use App\Models\Application;
use App\Models\ApplicationSetting;
use App\Models\ConsentRecord;
use App\Models\ConsentText;
use App\Models\CredentialIssuance;
use App\Models\SkClaim;
use App\Models\SkParticipant;
use App\Models\User;
use App\Models\WhatsappVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        $periods = AdmissionPeriod::query()->where('state', 'published')->orderByDesc('id')->get();
        $openPeriods = $periods->filter(fn (AdmissionPeriod $period) => $period->acceptsApplications());

        return view('public.claim', ['periods' => $periods, 'defaultPeriodId' => $openPeriods->count() === 1 ? $openPeriods->first()->id : null, 'consent' => ConsentText::query()->where('purpose', 'claim')->latest('id')->first(), 'requestKey' => (string) Str::uuid(), 'pegIdRequired' => ApplicationSetting::claimPegIdRequired()]);
    }

    public function store(Request $request, SubmitSkClaim $submit): RedirectResponse
    {
        $requester = $request->user();
        if ($requester?->role !== UserRole::Applicant) {
            $requester = null;
        }
        $claim = $submit->handle($requester, $request->only(['period_id', 'name', 'identifier', 'whatsapp_number', 'request_key', 'consent_id', 'consent']), ApplicationSetting::claimPegIdRequired());

        return redirect()->route('public.claim')
            ->with('claim_submitted', true)
            ->with('status', 'Pendaftaran akun diterima untuk pemeriksaan. Referensi: '.$claim->public_reference.'. Setelah data diperiksa dan disetujui, operator akan mengirimkan ID login dan kata sandi ke nomor WhatsApp yang Anda daftarkan.');
    }

    public function lookup(Request $request, LookupPublicClaimParticipant $lookup): JsonResponse
    {
        $participants = $lookup->handle($request->only(['identifier', 'whatsapp_number']));

        return response()->json(['participants' => $participants->map(fn (SkParticipant $participant) => [
            'period_id' => $participant->admission_period_id,
            'period_name' => $participant->period?->name,
            'name' => $participant->name_on_sk,
        ])])->header('Cache-Control', 'no-store, private');
    }

    public function own(Request $request): View
    {
        return view('portal.claims-own', ['claims' => SkClaim::query()->where('requester_user_id', $request->user()?->id)->with(['period', 'responses'])->orderByDesc('id')->paginate(20)]);
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SkClaim::class);
        $claims = SkClaim::query()->with(['period', 'candidate.assignedOperator', 'reviewOwner']);

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

    public function show(SkClaim $claim, IssueClaimCredentials $access): Response
    {
        abort_unless(Gate::allows('review', $claim), 404);

        $actor = auth()->user();
        $isStaff = $actor?->isAdministrator() || $actor?->role === UserRole::Operator;

        $credentials = $claim->status === 'approved' && $claim->access_path === 'whatsapp'
            ? $access->details($actor, $claim)
            : [];

        return response()->view('portal.claim-detail', [
            'claim' => $claim->load(['period', 'candidate.assignedOperator', 'requester', 'responses', 'reviewOwner']),
            'operators' => $isStaff ? User::query()->where('role', UserRole::Operator)->where('account_state', AccountState::Active)->where('must_change_password', false)->orderBy('name')->get() : collect(),
            'application' => $claim->status === 'approved' ? Application::query()->with('workspace.biodata')->where('sk_participant_id', $claim->candidate_sk_participant_id)->first() : null,
            'credentials' => $credentials,
        ], headers: ['Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache', 'Referrer-Policy' => 'no-referrer']);
    }

    public function approve(Request $request, SkClaim $claim, ApproveClaim $approve, IssueClaimCredentials $access): RedirectResponse
    {
        Gate::authorize('review', $claim);
        $data = $request->validate(['version' => ['required', 'integer', 'min:0'], 'candidate_id' => ['sometimes', 'required', 'integer', 'exists:sk_participants,id']]);
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        DB::transaction(function () use ($actor, $claim, $data, $approve, $access): void {
            $approve->handle($actor, $claim, (int) $data['version'], isset($data['candidate_id']) ? (int) $data['candidate_id'] : null);
            $claim->refresh();
            if ($claim->access_path === 'whatsapp') {
                $access->handle($actor, $claim);
            }
        });

        return redirect()->route('claims.show', $claim)->with('status', 'Klaim disetujui dan akun siap dikirim kepada peserta.');
    }

    public function updateIdentity(Request $request, SkClaim $claim, UpdateClaimIdentity $update): RedirectResponse
    {
        Gate::authorize('review', $claim);
        abort_unless($claim->access_path === 'whatsapp', 403);
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:0'],
            'submitted_name' => ['required', 'string', 'max:255'],
            'submitted_identifier' => ['nullable', 'string', 'max:100'],
        ], ['submitted_name.required' => 'Nama permohonan wajib diisi.']);
        $update->handle($request->user(), $claim, (int) $data['version'], $data['submitted_name'], $data['submitted_identifier'] ?? null);

        return redirect()->route('claims.show', $claim)->with('status', 'Nama dan Peg ID permohonan berhasil diperbarui. Periksa kembali kecocokan kandidat SK sebelum menyetujui klaim.');
    }

    public function updateWhatsapp(Request $request, SkClaim $claim, UpdateClaimWhatsapp $update): RedirectResponse
    {
        Gate::authorize('review', $claim);
        $data = $request->validate(['version' => ['required', 'integer', 'min:0'], 'whatsapp_number' => ['required', 'string', 'max:30']]);
        $update->handle($request->user(), $claim, (int) $data['version'], $data['whatsapp_number']);

        return redirect()->route('claims.show', $claim)->with('status', 'Nomor permohonan dan tujuan pengiriman akun berhasil diperbarui.');
    }

    public function updateLoginId(Request $request, SkClaim $claim, UpdateClaimLoginId $update): RedirectResponse
    {
        Gate::authorize('review', $claim);
        abort_unless($claim->requester_user_id !== null, 404);

        $request->merge([
            'login_id' => $request->filled('login_id') ? mb_strtolower(trim($request->string('login_id')->toString())) : null,
        ]);

        $data = $request->validate([
            'version' => ['required', 'integer', 'min:0'],
            'login_id' => [
                'required',
                'string',
                'regex:/^[a-z0-9._-]+$/',
                'max:64',
                Rule::unique('users', 'login_id')->ignore($claim->requester_user_id),
            ],
        ], [
            'login_id.required' => 'ID login wajib diisi.',
            'login_id.regex' => 'ID login hanya boleh berisi huruf kecil, angka, titik, garis bawah, dan tanda hubung.',
            'login_id.unique' => 'ID login sudah digunakan akun lain.',
            'login_id.max' => 'ID login maksimal 64 karakter.',
        ]);

        $actor = $request->user();
        abort_unless($actor !== null, 403);

        $update->handle($actor, $claim, (int) $data['version'], $data['login_id']);

        return redirect()->route('claims.show', $claim)->with('status', 'ID login berhasil diperbarui.');
    }

    public function cancelApproval(Request $request, SkClaim $claim, CancelClaimApproval $cancelApproval): RedirectResponse
    {
        Gate::authorize('review', $claim);
        $data = $request->validate(['version' => ['required', 'integer', 'min:0'], 'confirmed' => ['accepted']]);
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        $cancelApproval->handle($actor, $claim, (int) $data['version']);

        return back()->with('status', 'Persetujuan klaim berhasil dibatalkan. Status klaim dikembalikan ke Menunggu Pemeriksaan.');
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

    public function destroy(Request $request, SkClaim $claim, RecordAudit $audit, DeleteWhatsappClaimAccount $deleter): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        abort_unless($actor->isAdministrator() || $actor->role === UserRole::Operator, 403);
        abort_unless(Gate::allows('review', $claim), 403);
        $data = $request->validate(['version' => ['nullable', 'integer', 'min:0']]);
        if (isset($data['version']) && $claim->lock_version !== (int) $data['version']) {
            throw ValidationException::withMessages(['claim' => 'Klaim telah diproses atau berubah status. Muat ulang sebelum menghapus.']);
        }
        if ($claim->status === 'approved') {
            $deleter->handle($actor, $claim, (int) ($data['version'] ?? $claim->lock_version));

            return redirect()->route('claims.index')->with('status', 'Akses akun dan klaim WhatsApp telah dihapus. Peserta dapat mengajukan ulang; data SK, pendaftaran, biodata, dan berkas tetap tersimpan.');
        }
        abort_unless(in_array($claim->status, ['pending', 'needs_information', 'declined', 'cancelled'], true), 422, 'Status klaim tidak dapat dihapus.');

        DB::transaction(function () use ($actor, $claim, $audit, $data): void {
            AdmissionPeriod::query()->lockForUpdate()->findOrFail($claim->admission_period_id);
            $locked = SkClaim::query()->lockForUpdate()->findOrFail($claim->id);
            abort_unless(Gate::allows('review', $locked), 403);
            if (! in_array($locked->status, ['pending', 'needs_information', 'declined', 'cancelled'], true) || (isset($data['version']) && $locked->lock_version !== (int) $data['version'])) {
                throw ValidationException::withMessages(['claim' => 'Klaim telah diproses atau berubah status.']);
            }
            if (DB::table('application_ownership_transfers')->where('target_claim_id', $locked->id)
                ->orWhereIn('administrative_case_id', AdministrativeCase::query()->where('claim_id', $locked->id)->select('id'))->exists()) {
                throw ValidationException::withMessages(['claim' => 'Klaim terhubung dengan riwayat koreksi kepemilikan dan tidak dapat dihapus melalui daftar klaim.']);
            }

            $locked->responses()->delete();
            CredentialIssuance::query()->where('claim_id', $locked->id)->delete();
            ConsentRecord::query()->where('claim_id', $locked->id)->delete();
            WhatsappVerification::query()->where('claim_id', $locked->id)->delete();
            AdministrativeCase::query()->where('claim_id', $locked->id)->delete();

            $audit->handle($actor, 'Menghapus permohonan klaim', $locked, [
                'submitted_name' => $locked->submitted_name,
                'whatsapp_number' => $locked->whatsapp_number,
                'period_id' => $locked->admission_period_id,
            ]);

            $locked->delete();
        });

        return redirect()->route('claims.index')->with('status', 'Permohonan klaim berhasil dihapus.');
    }
}
