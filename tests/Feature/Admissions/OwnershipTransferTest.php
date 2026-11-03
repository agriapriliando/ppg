<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SaveApplicantForm;
use App\Actions\Admissions\SubmitSkClaim;
use App\Enums\AccountState;
use App\Enums\ApplicationStatus;
use App\Enums\PeriodState;
use App\Models\ApplicantWorkspace;
use App\Models\Application;
use App\Models\ApplicationReview;
use App\Models\ApplicationSubmission;
use App\Models\ConsentRecord;
use App\Models\CredentialIssuance;
use App\Models\SkParticipant;
use App\Models\User;
use App\Models\WhatsappVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require_once __DIR__.'/../../Fixtures/registration.php';

function ownershipContext(bool $submitted = false, bool $targetDraft = false): array
{
    $c = registrationContext();
    $sourceClaim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $sourceClaim, 0);
    if ($submitted) {
        $form = [...completeApplicantForm($c), 'full_name' => 'Biodata Lama Pribadi', 'street_address' => 'Alamat Lama Rahasia'];
        app(SaveApplicantForm::class)->handle($c['applicant'], $c['period']->id, 0, $form, true);
        ApplicationReview::create(['application_id' => $application->id, 'reviewer_id' => $c['operator']->id, 'submission_revision' => 1, 'action' => 'correction', 'public_note' => 'CATATAN PRIVAT PEMILIK LAMA', 'field_keys' => ['Alamat']]);
        $application->refresh()->forceFill(['status' => ApplicationStatus::Accepted, 'accepted_at' => now(), 'accepted_by' => $c['operator']->id])->save();
    }
    $target = User::factory()->create(['name' => 'Akun Pemilik Sah']);
    $input = claimInput($c);
    $input['whatsapp_number'] = '081234567899';
    $targetClaim = app(SubmitSkClaim::class)->handle($target, $input);
    if ($targetDraft) {
        app(SaveApplicantForm::class)->handle($target, $c['period']->id, 0, ['full_name' => 'Biodata Tujuan Pribadi', 'nik' => '0000000000000002', 'street_address' => 'Alamat Tujuan Pribadi', 'whatsapp_number' => '081234567899'], false);
    }
    $c['participant']->refresh()->forceFill(['admin_hold' => true, 'hold_reason' => 'Penahanan sementara untuk pemeriksaan kedua pihak.', 'lock_version' => $c['participant']->lock_version + 1])->save();

    return [...$c, 'application' => $application->refresh(), 'sourceClaim' => $sourceClaim->refresh(), 'targetClaim' => $targetClaim->refresh(), 'target' => $target];
}

function ownershipInput(array $c): array
{
    $application = $c['application']->fresh();

    return ['target_claim_id' => $c['targetClaim']->id, 'application_version' => $application->lock_version, 'participant_version' => $c['participant']->fresh()->lock_version, 'target_claim_version' => $c['targetClaim']->fresh()->lock_version, 'source_workspace_version' => $application->workspace->lock_version, 'target_workspace_version' => ApplicantWorkspace::query()->where('user_id', $c['target']->id)->where('admission_period_id', $c['period']->id)->value('lock_version') ?? 0, 'reason' => 'Kaitan pemilik lama keliru; hasil pemeriksaan menunjukkan pemilik sah pada akun tujuan.', 'evidence' => 'Identitas kedua pihak dan akses akun tujuan diperiksa melalui layanan resmi kampus.', 'source_identity_checked' => 1, 'target_identity_checked' => 1, 'target_account_confirmed' => 1, 'privacy_confirmed' => 1];
}

it('transfers ownership without moving private drafts or leaking former reviews and requires fresh submission for proof', function () {
    $c = ownershipContext(true, true);
    $application = $c['application'];
    $oldWorkspaceId = $application->workspace_id;
    $firstDate = $application->tanggal_daftar->toDateString();
    $snapshot = $application->config_snapshot;
    $oldSubmission = ApplicationSubmission::query()->sole();
    WhatsappVerification::create(['workspace_id' => $oldWorkspaceId, 'number' => '+6281234567890', 'verified_by' => $c['operator']->id, 'verified_at' => now(), 'evidence_summary' => 'Bukti balasan lama sebelum pemeriksaan.']);
    foreach ([$c['applicant'], $c['target']] as $user) {
        DB::table('sessions')->insert(['id' => 'sesi-uji-'.$user->id, 'user_id' => $user->id, 'payload' => base64_encode(serialize([])), 'last_activity' => now()->timestamp]);
        CredentialIssuance::create(['user_id' => $user->id, 'claim_id' => $user->is($c['applicant']) ? $c['sourceClaim']->id : $c['targetClaim']->id, 'secret_hash' => Hash::make('AksesUjiYangDicabut'), 'target_number' => '+6281234567890', 'issued_by' => $c['operator']->id, 'expires_at' => now()->addDay()]);
    }
    $this->actingAs($c['admin'])->get(route('admin.ownership.edit', ['application' => $application, 'target_claim_id' => $c['targetClaim']->id]))->assertOk()->assertSee('Pratinjau akun tujuan')->assertDontSee('Alamat Lama Rahasia')->assertDontSee('0000000000000001');
    $input = [...ownershipInput($c), 'status' => 'diterima_lengkap', 'workspace_id' => $oldWorkspaceId];
    $this->post(route('admin.ownership.update', $application), $input)->assertRedirect(route('reviews.show', $application))->assertSessionHasNoErrors();
    $application->refresh();
    expect($application->user_id)->toBe($c['target']->id)->and($application->workspace_id)->not->toBe($oldWorkspaceId)
        ->and($application->status)->toBe(ApplicationStatus::Correction)->and($application->ownership_epoch)->toBe(1)
        ->and($application->accepted_at)->toBeNull()->and($application->config_snapshot)->toBe($snapshot)
        ->and($application->tanggal_daftar->toDateString())->toBe($firstDate)
        ->and($application->workspace->biodata->street_address)->toBe('Alamat Tujuan Pribadi');
    expect(ApplicantWorkspace::findOrFail($oldWorkspaceId)->biodata->street_address)->toBe('Alamat Lama Rahasia');
    expect($oldSubmission->refresh()->submitted_by)->toBe($c['applicant']->id);
    expect($c['sourceClaim']->fresh()->status)->toBe('cancelled')->and($c['targetClaim']->fresh()->status)->toBe('approved');
    $this->assertDatabaseHas('application_ownership_transfers', ['old_user_id' => $c['applicant']->id, 'new_user_id' => $c['target']->id, 'ownership_epoch' => 1]);
    $this->assertDatabaseHas('administrative_cases', ['type' => 'ownership_transfer', 'state' => 'resolved']);
    $this->post(route('admin.ownership.update', $application), $input)->assertSessionHasErrors('ownership');
    $this->assertDatabaseCount('application_ownership_transfers', 1);
    $this->get(route('admin.ownership.edit', $application))->assertOk()->assertSee('Riwayat koreksi kepemilikan')->assertSee($c['target']->name);
    expect(CredentialIssuance::query()->whereNull('revoked_at')->count())->toBe(0);
    expect(DB::table('sessions')->whereIn('user_id', [$c['applicant']->id, $c['target']->id])->count())->toBe(0);
    expect(WhatsappVerification::query()->where('workspace_id', $oldWorkspaceId)->whereNull('invalidated_at')->count())->toBe(0);
    $this->actingAs($c['target'])->get(route('applicant.application.status', $c['period']))->assertOk()->assertDontSee('CATATAN PRIVAT PEMILIK LAMA')->assertDontSee('Bukti Pendaftaran');
    $this->get(route('applicant.form.edit', $c['period']))->assertOk()->assertSee('Alamat Tujuan Pribadi')->assertDontSee('Alamat Lama Rahasia')->assertDontSee('0000000000000001');
    $this->get(route('applicant.application.proof', $c['period']))->assertNotFound();
    $form = [...completeApplicantForm($c), 'version' => $application->workspace->lock_version, 'full_name' => 'Biodata Tujuan Pribadi', 'nik' => '0000000000000002', 'whatsapp_number' => '081234567899', 'street_address' => 'Alamat Tujuan Pribadi'];
    $this->post(route('applicant.form.update', $c['period']), $form)->assertRedirect()->assertSessionHasNoErrors();
    expect($application->fresh()->submission_revision)->toBe(2)->and($application->fresh()->current_owner_submitted_at)->not->toBeNull();
    $this->get(route('applicant.application.proof', $c['period']))->assertOk()->assertSee('Biodata Tujuan Pribadi')->assertDontSee('Biodata Lama Pribadi');
    expect(ApplicationSubmission::query()->where('revision', 2)->sole()->ownership_epoch)->toBe(1);
    $this->assertDatabaseHas('consent_records', ['application_id' => $application->id, 'user_id' => $c['applicant']->id, 'revision' => 1]);
    $this->assertDatabaseHas('consent_records', ['application_id' => $application->id, 'user_id' => $c['target']->id, 'revision' => 2]);
    $this->actingAs($c['applicant'])->get(route('applicant.application.proof', $c['period']))->assertNotFound();
    $this->get(route('applicant.application.status', $c['period']))->assertOk()->assertDontSee('Alamat Tujuan Pribadi')->assertDontSee('Kepesertaan dikoreksi');
    $this->get(route('applicant.form.edit', $c['period']))->assertOk()->assertSee('Alamat Lama Rahasia')->assertDontSee('Alamat Tujuan Pribadi');
    $this->actingAs($c['admin'])->get(route('reviews.show', $application))->assertOk()->assertSee('CATATAN PRIVAT PEMILIK LAMA');
});

it('creates an empty target workspace from its own claim when no draft exists', function () {
    $c = ownershipContext();
    $this->actingAs($c['admin'])->post(route('admin.ownership.update', $c['application']), ownershipInput($c))->assertRedirect()->assertSessionHasNoErrors();
    $application = $c['application']->fresh();
    expect($application->status)->toBe(ApplicationStatus::Draft)->and($application->workspace->user_id)->toBe($c['target']->id)
        ->and($application->workspace->biodata->whatsapp_number)->toBe('+6281234567899')->and($application->workspace->biodata->getAttribute('nik_encrypted'))->toBeNull();
    $this->assertDatabaseCount('applications', 1);
    $this->actingAs($c['target'])->get(route('applicant.form.edit', $c['period']))->assertOk();
});

it('rejects stale versions of every affected record without changing ownership', function (string $field) {
    $c = ownershipContext(false, true);
    $input = ownershipInput($c);
    $input[$field]++;
    $this->actingAs($c['admin'])->post(route('admin.ownership.update', $c['application']), $input)->assertSessionHasErrors('ownership');
    expect($c['application']->fresh()->user_id)->toBe($c['applicant']->id);
    $this->assertDatabaseCount('application_ownership_transfers', 0);
})->with(['application_version', 'participant_version', 'target_claim_version', 'source_workspace_version', 'target_workspace_version']);

it('requires all identity and privacy confirmations before transferring', function () {
    $c = ownershipContext();
    $input = ownershipInput($c);
    unset($input['source_identity_checked'], $input['target_identity_checked'], $input['target_account_confirmed'], $input['privacy_confirmed']);
    $this->actingAs($c['admin'])->post(route('admin.ownership.update', $c['application']), $input)->assertSessionHasErrors(['source_identity_checked', 'target_identity_checked', 'target_account_confirmed', 'privacy_confirmed']);
    $this->assertDatabaseCount('application_ownership_transfers', 0);
});

it('rejects missing holds archived periods withdrawn SK and disabled targets', function (string $condition) {
    $c = ownershipContext();
    if ($condition === 'hold') {
        $c['participant']->forceFill(['admin_hold' => false])->save();
    } elseif ($condition === 'archived') {
        $c['period']->state = PeriodState::Archived;
        $c['period']->save();
    } elseif ($condition === 'withdrawn') {
        $c['participant']->forceFill(['roster_state' => 'withdrawn'])->save();
    } elseif ($condition === 'disabled') {
        $c['target']->account_state = AccountState::Disabled;
        $c['target']->save();
    }
    $this->actingAs($c['admin'])->post(route('admin.ownership.update', $c['application']), ownershipInput($c))->assertSessionHasErrors('ownership');
    expect($c['application']->fresh()->user_id)->toBe($c['applicant']->id);
    $this->assertDatabaseCount('application_ownership_transfers', 0);
})->with(['hold', 'archived', 'withdrawn', 'disabled']);

it('rejects claims for another SK or without the target account consent', function (string $condition) {
    $c = ownershipContext();
    if ($condition === 'sk') {
        $participant = SkParticipant::create(['admission_period_id' => $c['period']->id, 'sk_number' => 'SK-LAIN', 'sk_date' => '2026-10-01', 'sk_order' => 1, 'name_on_sk' => 'Peserta Lain', 'normalized_name' => 'peserta lain']);
        $c['targetClaim']->forceFill(['candidate_sk_participant_id' => $participant->id])->save();
    } else {
        ConsentRecord::query()->where('claim_id', $c['targetClaim']->id)->delete();
    }
    $this->actingAs($c['admin'])->post(route('admin.ownership.update', $c['application']), ownershipInput($c))->assertSessionHasErrors($condition === 'sk' ? 'target_claim_id' : 'ownership');
    $this->assertDatabaseCount('application_ownership_transfers', 0);
})->with(['sk', 'consent']);

it('rejects ownership correction by operators and applicants', function () {
    $c = ownershipContext();
    foreach ([$c['operator'], $c['applicant'], $c['target']] as $user) {
        $this->actingAs($user)->get(route('admin.ownership.edit', $c['application']))->assertForbidden();
        $this->post(route('admin.ownership.update', $c['application']), ownershipInput($c))->assertForbidden();
    }
    $this->assertDatabaseCount('application_ownership_transfers', 0);
});

it('refuses to merge when the target already owns another registration in the same period', function () {
    $c = ownershipContext(false, true);
    $participant = SkParticipant::create(['admission_period_id' => $c['period']->id, 'sk_number' => 'SK-LAIN', 'sk_date' => '2026-10-01', 'sk_order' => 1, 'name_on_sk' => 'Peserta Lain', 'normalized_name' => 'peserta lain']);
    $participant->forceFill(['claimed_user_id' => $c['target']->id])->save();
    $workspace = ApplicantWorkspace::query()->where('user_id', $c['target']->id)->sole();
    Application::create(['sk_participant_id' => $participant->id, 'user_id' => $c['target']->id, 'admission_period_id' => $c['period']->id, 'workspace_id' => $workspace->id, 'application_number' => 'PMB-UJI-LAIN']);
    $this->actingAs($c['admin'])->post(route('admin.ownership.update', $c['application']), ownershipInput($c))->assertSessionHasErrors('ownership');
    expect($c['application']->fresh()->user_id)->toBe($c['applicant']->id);
    $this->assertDatabaseCount('application_ownership_transfers', 0);
    $this->assertDatabaseCount('applications', 2);
});

it('creates a pending WhatsApp account only after recording a verified reply and supports one-time access delivery', function () {
    $c = ownershipContext(true);
    $input = claimInput($c);
    $input['whatsapp_number'] = '081234567898';
    $claim = app(SubmitSkClaim::class)->handle(null, $input);
    $payload = [...ownershipInput($c), 'target_claim_id' => $claim->id, 'target_claim_version' => 0];
    $this->actingAs($c['admin'])->get(route('admin.ownership.edit', ['application' => $c['application'], 'target_claim_id' => $claim->id]))->assertOk()->assertSee('Akun baru, menunggu aktivasi');
    $this->post(route('admin.ownership.update', $c['application']), $payload)->assertSessionHasErrors('ownership');
    $this->assertDatabaseCount('application_ownership_transfers', 0);
    $this->assertDatabaseCount('users', 4);
    $this->post(route('claims.verify-whatsapp', $claim), ['confirmed' => 1, 'evidence' => 'Balasan dari pemilik nomor tujuan diperiksa melalui kanal resmi.'])->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('admin.ownership.update', $c['application']), $payload)->assertRedirect()->assertSessionHasNoErrors();
    $owner = $claim->fresh()->requester;
    expect($owner->account_state)->toBe(AccountState::PendingActivation)->and($owner->password)->toBeNull()->and($owner->must_change_password)->toBeTrue();
    $application = $c['application']->fresh();
    expect($application->workspace->biodata->whatsapp_number)->toBe('+6281234567898')
        ->and($application->workspace->biodata->getAttribute('nik_encrypted'))->toBeNull();
    $response = $this->actingAs($c['operator'])->post(route('credentials.issue', $claim), ['reason' => 'Penyerahan akses kepada pemilik sah sesudah koreksi kepemilikan.']);
    $response->assertOk();
    expect($response->viewData('issuance')->target_number)->toBe('+6281234567898');
    $this->assertDatabaseCount('applications', 1);
    $this->assertDatabaseCount('application_ownership_transfers', 1);
});

it('allows an administrator to match an ambiguous target claim during the recorded ownership correction', function () {
    $c = ownershipContext();
    $c['targetClaim']->forceFill(['candidate_sk_participant_id' => null])->save();
    $this->actingAs($c['admin'])->get(route('admin.ownership.edit', ['application' => $c['application'], 'target_claim_id' => $c['targetClaim']->id]))->assertOk()->assertSee('Kandidat belum ditetapkan');
    $this->post(route('admin.ownership.update', $c['application']), ownershipInput($c))->assertRedirect()->assertSessionHasNoErrors();
    expect($c['targetClaim']->fresh()->candidate_sk_participant_id)->toBe($c['participant']->id);
});

it('blocks schema rollback after a transfer so historical privacy boundaries cannot be removed', function () {
    $c = ownershipContext();
    $this->actingAs($c['admin'])->post(route('admin.ownership.update', $c['application']), ownershipInput($c))->assertRedirect()->assertSessionHasNoErrors();
    $migration = require base_path('database/migrations/2026_10_18_081739_add_application_ownership_history.php');
    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
    $this->assertDatabaseCount('application_ownership_transfers', 1);
    expect($c['application']->fresh()->ownership_epoch)->toBe(1);
});
