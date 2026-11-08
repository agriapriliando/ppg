<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\CancelClaimApproval;
use App\Actions\Admissions\IssueClaimCredentials;
use App\Actions\Admissions\SaveApplicantForm;
use App\Actions\Admissions\SubmitSkClaim;
use App\Actions\Documents\UploadApplicantDocument;
use App\Enums\AccountState;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationSubmission;
use App\Models\CredentialIssuance;
use App\Models\SkClaim;
use App\Models\User;
use App\Support\WhatsappNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require_once __DIR__.'/../../Fixtures/registration.php';

it('removes WhatsApp account access and claim while retaining registration files and allowing a fresh claim and login', function (ApplicationStatus $status, string $newNumber) {
    Storage::fake('local');
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $otherInput = [...claimInput($c), 'request_key' => (string) Str::uuid(), 'whatsapp_number' => '081299999999'];
    $other = app(SubmitSkClaim::class)->handle(User::factory()->create(), $otherInput);
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $oldIssuance = app(IssueClaimCredentials::class)->handle($c['operator'], $claim->fresh());
    $oldSecret = $oldIssuance->encrypted_secret;
    $owner = User::findOrFail($application->user_id);
    $oldLogin = $owner->login_id;
    $owner->forceFill(['account_state' => AccountState::Active, 'must_change_password' => false, 'password' => $oldSecret])->save();
    DB::table('sessions')->insert(['id' => 'old-whatsapp-session', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => now()->timestamp]);
    $document = app(UploadApplicantDocument::class)->handle($owner, $application->workspace, UploadedFile::fake()->create('tetap-tersimpan.pdf', 10, 'application/pdf'), 'claim-delete');
    app(SaveApplicantForm::class)->handle($owner, $c['period']->id, 0, [...completeApplicantForm($c), 'documents_version' => 1], true);
    $application->refresh()->forceFill(['status' => $status, 'accepted_at' => $status === ApplicationStatus::Accepted ? now() : null])->save();
    $snapshot = ApplicationSubmission::query()->sole()->encrypted_snapshot;
    $claim->refresh();
    $this->actingAs($c['admin'])->get(route('claims.index'))->assertOk()
        ->assertSee(route('claims.destroy', $claim))->assertSee('Hapus Permohonan Klaim?');
    $this->delete(route('claims.destroy', $claim), ['version' => $claim->lock_version])->assertRedirect(route('claims.index'))->assertSessionHasNoErrors();
    $this->assertSoftDeleted('sk_claims', ['id' => $claim->id]);
    $this->assertDatabaseHas('applications', ['id' => $application->id]);
    $this->assertDatabaseHas('applicant_workspaces', ['id' => $application->workspace_id]);
    $this->assertDatabaseHas('applicant_documents', ['id' => $document->id]);
    $this->assertDatabaseHas('sk_participants', ['id' => $c['participant']->id, 'claimed_user_id' => null, 'assigned_operator_id' => $c['operator']->id]);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'login_id' => null, 'password' => null, 'account_state' => 'disabled']);
    $this->assertDatabaseHas('sk_claims', ['id' => $other->id]);
    $this->assertDatabaseCount('application_submissions', 1);
    $this->assertDatabaseCount('application_submission_documents', 1);
    $this->assertDatabaseCount('assignment_events', 1);
    expect(CredentialIssuance::query()->whereNull('revoked_at')->count())->toBe(0)
        ->and($oldIssuance->fresh()->encrypted_secret)->toBeNull();
    $this->assertDatabaseMissing('sessions', ['id' => 'old-whatsapp-session']);
    Storage::disk('local')->assertExists($document->object_key);
    $this->post(route('logout'))->assertRedirect();
    $this->post(route('login.store'), ['login' => $oldLogin, 'password' => $oldSecret])->assertSessionHasErrors('login');
    $this->actingAs($c['operator'])->post(route('claims.approve', $other), ['version' => $other->fresh()->lock_version])->assertSessionHasErrors('claim');
    $replacement = app(SubmitSkClaim::class)->handle(null, [...claimInput($c), 'request_key' => (string) Str::uuid(), 'whatsapp_number' => $newNumber]);
    $this->actingAs($c['operator'])->post(route('claims.approve', $replacement), ['version' => 0])->assertSessionHasNoErrors()->assertRedirect();
    expect($replacement->fresh()->requester_user_id)->toBe($owner->id)
        ->and(Application::query()->sole()->id)->toBe($application->id)
        ->and(Application::query()->sole()->status)->toBe($status)
        ->and(Application::query()->sole()->claim_account_reset_at)->toBeNull()
        ->and(ApplicationSubmission::query()->sole()->encrypted_snapshot)->toBe($snapshot)
        ->and($application->workspace->fresh()->biodata->whatsapp_number)->toBe(WhatsappNumber::normalize($newNumber));
    $issuance = CredentialIssuance::query()->where('claim_id', $replacement->id)->sole();
    expect($issuance->encrypted_secret)->not->toBe($oldSecret);
    $this->post(route('logout'))->assertRedirect();
    $this->post(route('login.store'), ['login' => $owner->fresh()->login_id, 'password' => $issuance->encrypted_secret])->assertSessionHasNoErrors()->assertRedirect();
    $this->assertAuthenticatedAs($owner);
})->with([[ApplicationStatus::Submitted, '081234567890'], [ApplicationStatus::Accepted, '081288888888']]);

it('rejects stale claim versions and deletion by applicants', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $this->actingAs(User::factory()->create())->delete(route('claims.destroy', $claim))->assertForbidden();
    $claim->increment('lock_version');
    $this->actingAs($c['admin'])->delete(route('claims.destroy', $claim), ['version' => 0])->assertSessionHasErrors('claim');
    $this->assertDatabaseHas('sk_claims', ['id' => $claim->id]);
});

it('deletes related pending claim records after an approval is cancelled', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    CredentialIssuance::query()->create(['user_id' => $c['applicant']->id, 'claim_id' => $claim->id, 'secret_hash' => 'hash-kredensial-uji', 'target_number' => $claim->whatsapp_number, 'issued_by' => $c['operator']->id, 'expires_at' => now()->addDay()]);
    expect(CredentialIssuance::count())->toBe(1);
    app(CancelClaimApproval::class)->handle($c['admin'], $claim->fresh(), $claim->fresh()->lock_version);
    $claim->refresh();
    $claim->responses()->create(['user_id' => $c['applicant']->id, 'message' => 'Tanggapan uji']);
    $this->actingAs($c['operator'])->delete(route('claims.destroy', $claim), ['version' => $claim->lock_version])->assertSessionHasNoErrors()->assertRedirect();
    expect(SkClaim::find($claim->id))->toBeNull();
    $this->assertDatabaseCount('claim_responses', 0);
    $this->assertDatabaseCount('credential_issuances', 0);
});

it('deletes unapproved claim statuses without removing the SK participant', function (string $status) {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $claim->forceFill(['status' => $status])->save();
    $this->actingAs($c['operator'])->get(route('claims.index'))->assertOk()->assertSee(route('claims.destroy', $claim));
    $this->delete(route('claims.destroy', $claim), ['version' => $claim->lock_version])->assertSessionHasNoErrors()->assertRedirect();
    $this->assertSoftDeleted('sk_claims', ['id' => $claim->id]);
    $this->assertDatabaseHas('sk_participants', ['id' => $c['participant']->id]);
})->with(['needs_information', 'declined', 'cancelled']);
