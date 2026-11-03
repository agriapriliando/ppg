<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SubmitSkClaim;
use App\Enums\AccountState;
use App\Enums\ApplicationStatus;
use App\Enums\PeriodState;
use App\Models\ApplicantBiodata;
use App\Models\ApplicantWorkspace;
use App\Models\AuditEvent;
use App\Models\CredentialIssuance;
use App\Models\User;
use App\Models\WhatsappVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require_once __DIR__.'/../../Fixtures/registration.php';

function whatsappRecoveryContext(): array
{
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    WhatsappVerification::create(['claim_id' => $claim->id, 'number' => $claim->whatsapp_number, 'verified_by' => $c['operator']->id, 'verified_at' => now(), 'evidence_summary' => 'Balasan nomor asli diperiksa oleh operator.']);
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $claim->refresh();
    $owner = $claim->requester;
    $owner->forceFill(['account_state' => AccountState::Active, 'password' => 'SandiLamaPeserta2026!', 'must_change_password' => false, 'remember_token' => 'token-lama'])->save();

    return [...$c, 'claim' => $claim, 'application' => $application, 'owner' => $owner];
}

function whatsappRecoveryInput(array $c): array
{
    return ['claim_version' => $c['claim']->fresh()->lock_version, 'application_version' => $c['application']->fresh()->lock_version, 'workspace_version' => $c['application']->workspace->fresh()->lock_version, 'whatsapp_number' => '081234567899', 'whatsapp_number_confirmation' => '081234567899', 'reason' => 'Nomor lama hilang; pemilik mengajukan penggantian melalui layanan resmi.', 'evidence' => 'Identitas SK dan pemilik akun diperiksa; balasan nomor baru diterima pada jam layanan.', 'identity_checked' => 1, 'reply_verified' => 1, 'access_revocation_confirmed' => 1];
}

it('recovers the same account through a new verified number and revokes previous access before reactivation', function () {
    $c = whatsappRecoveryContext();
    $old = CredentialIssuance::create(['user_id' => $c['owner']->id, 'claim_id' => $c['claim']->id, 'secret_hash' => Hash::make('RahasiaAksesLama'), 'target_number' => $c['claim']->whatsapp_number, 'issued_by' => $c['operator']->id, 'expires_at' => now()->addDay(), 'delivered_at' => now()]);
    DB::table('sessions')->insert(['id' => 'sesi-pemilik-lama', 'user_id' => $c['owner']->id, 'payload' => base64_encode(serialize([])), 'last_activity' => now()->timestamp]);
    $this->actingAs($c['admin'])->get(route('admin.whatsapp-recovery.edit', $c['claim']))->assertOk()->assertSee($c['owner']->login_id);
    $this->post(route('admin.whatsapp-recovery.update', $c['claim']), whatsappRecoveryInput($c))->assertRedirect(route('claims.show', $c['claim']))->assertSessionHasNoErrors();
    expect($c['application']->workspace->fresh()->biodata->whatsapp_number)->toBe('+6281234567899')
        ->and($c['claim']->fresh()->whatsapp_number)->toBe('+6281234567890')
        ->and($c['owner']->fresh()->password)->toBeNull()->and($c['owner']->fresh()->account_state)->toBe(AccountState::PendingActivation)
        ->and($old->fresh()->revoked_at)->not->toBeNull()->and($c['owner']->fresh()->remember_token)->not->toBe('token-lama');
    $this->assertDatabaseMissing('sessions', ['id' => 'sesi-pemilik-lama']);
    $this->assertDatabaseHas('administrative_cases', ['type' => 'whatsapp_recovery', 'state' => 'resolved', 'resolved_by' => $c['admin']->id]);
    expect(WhatsappVerification::query()->where('claim_id', $c['claim']->id)->whereNull('invalidated_at')->count())->toBe(0);
    expect(WhatsappVerification::query()->where('workspace_id', $c['application']->workspace_id)->whereNull('invalidated_at')->sole()->number)->toBe('+6281234567899');
    $audit = AuditEvent::query()->where('action', 'Memulihkan akses melalui penggantian nomor WhatsApp')->sole();
    expect(json_encode($audit->toArray()))->not->toContain('081234567899')->not->toContain('+6281234567899');
    $response = $this->actingAs($c['operator'])->post(route('credentials.issue', $c['claim']), ['reason' => 'Penerbitan ulang setelah pemulihan oleh administrator.']);
    $response->assertOk();
    $issuance = $response->viewData('issuance');
    expect($issuance->target_number)->toBe('+6281234567899');
    $this->post(route('credentials.deliver', $issuance), ['delivered' => 1])->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('logout'));
    $this->post(route('login.store'), ['login' => $c['owner']->login_id, 'password' => 'SandiLamaPeserta2026!'])->assertSessionHasErrors('login');
    $this->post(route('login.store'), ['login' => $c['owner']->login_id, 'password' => $response->viewData('secret')])->assertRedirect(route('dashboard'));
    expect($c['owner']->fresh()->account_state)->toBe(AccountState::Active);
    $this->assertDatabaseCount('applications', 1);
});

it('reopens an accepted registration after contact recovery without changing first submission or old evidence', function () {
    $c = whatsappRecoveryContext();
    $this->actingAs($c['owner'])->post(route('applicant.form.update', $c['period']), completeApplicantForm($c))->assertRedirect()->assertSessionHasNoErrors();
    $application = $c['application']->refresh();
    $first = $application->submitted_at;
    $snapshot = $application->config_snapshot;
    $application->forceFill(['status' => ApplicationStatus::Accepted, 'accepted_at' => now(), 'accepted_by' => $c['operator']->id])->save();
    $this->actingAs($c['admin'])->post(route('admin.whatsapp-recovery.update', $c['claim']), whatsappRecoveryInput($c))->assertRedirect()->assertSessionHasNoErrors();
    expect($application->refresh()->status)->toBe(ApplicationStatus::Correction)
        ->and($application->accepted_at)->toBeNull()->and($application->accepted_by)->toBeNull()
        ->and($application->submitted_at->equalTo($first))->toBeTrue()->and($application->config_snapshot)->toBe($snapshot)
        ->and($application->submission_revision)->toBe(1);
    $this->assertDatabaseHas('application_reviews', ['application_id' => $application->id, 'action' => 'contact_recovery', 'submission_revision' => 1]);
    $this->assertDatabaseCount('application_submissions', 1);
});

it('rejects stale recovery requests without changing the number or revoking access', function () {
    $c = whatsappRecoveryContext();
    $input = whatsappRecoveryInput($c);
    $input['workspace_version']++;
    $this->actingAs($c['admin'])->post(route('admin.whatsapp-recovery.update', $c['claim']), $input)->assertSessionHasErrors('recovery');
    expect($c['owner']->fresh()->account_state)->toBe(AccountState::Active)
        ->and($c['application']->workspace->fresh()->biodata->whatsapp_number)->toBe('+6281234567890');
    $this->assertDatabaseCount('administrative_cases', 0);
});

it('requires identity reply and access revocation checks and matching new-number confirmation', function () {
    $c = whatsappRecoveryContext();
    $input = whatsappRecoveryInput($c);
    unset($input['identity_checked'], $input['reply_verified'], $input['access_revocation_confirmed']);
    $input['whatsapp_number_confirmation'] = '081111111111';
    $this->actingAs($c['admin'])->post(route('admin.whatsapp-recovery.update', $c['claim']), $input)->assertSessionHasErrors(['identity_checked', 'reply_verified', 'access_revocation_confirmed', 'whatsapp_number']);
    $this->assertDatabaseCount('administrative_cases', 0);
});

it('blocks disabled accounts and archived periods', function (string $condition) {
    $c = whatsappRecoveryContext();
    if ($condition === 'disabled') {
        $c['owner']->account_state = AccountState::Disabled;
        $c['owner']->save();
    } else {
        $c['period']->state = PeriodState::Archived;
        $c['period']->save();
    }
    $this->actingAs($c['admin'])->post(route('admin.whatsapp-recovery.update', $c['claim']), whatsappRecoveryInput($c))->assertSessionHasErrors('recovery');
    expect($c['application']->workspace->fresh()->biodata->whatsapp_number)->toBe('+6281234567890');
    $this->assertDatabaseMissing('administrative_cases', ['type' => 'whatsapp_recovery']);
})->with(['disabled', 'archived']);

it('rejects a replacement number already used by another account', function () {
    $c = whatsappRecoveryContext();
    $workspace = ApplicantWorkspace::create(['user_id' => User::factory()->create()->id, 'admission_period_id' => $c['period']->id]);
    ApplicantBiodata::create(['workspace_id' => $workspace->id, 'full_name' => 'Pemilik Nomor Lain', 'whatsapp_number' => '+6281234567899']);
    $this->actingAs($c['admin'])->post(route('admin.whatsapp-recovery.update', $c['claim']), whatsappRecoveryInput($c))->assertSessionHasErrors('whatsapp_number');
    $this->assertDatabaseCount('administrative_cases', 0);
});

it('allows operators to perform number recovery while restricting applicants', function () {
    $c = whatsappRecoveryContext();
    $this->actingAs($c['operator'])->get(route('admin.whatsapp-recovery.edit', $c['claim']))->assertOk();
    $this->actingAs($c['owner'])->get(route('admin.whatsapp-recovery.edit', $c['claim']))->assertForbidden();
    $this->post(route('admin.whatsapp-recovery.update', $c['claim']), whatsappRecoveryInput($c))->assertForbidden();
});

it('rejects malformed or unchanged replacement numbers', function (string $number) {
    $c = whatsappRecoveryContext();
    $input = whatsappRecoveryInput($c);
    $input['whatsapp_number'] = $number;
    $input['whatsapp_number_confirmation'] = $number;
    $this->actingAs($c['admin'])->post(route('admin.whatsapp-recovery.update', $c['claim']), $input)->assertSessionHasErrors('whatsapp_number');
    $this->assertDatabaseCount('administrative_cases', 0);
    expect($c['owner']->fresh()->account_state)->toBe(AccountState::Active);
})->with(['nomor sama' => '081234567890', 'format salah' => 'bukan-nomor']);
