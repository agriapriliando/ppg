<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SaveApplicantForm;
use App\Actions\Admissions\SubmitSkClaim;
use App\Enums\AccountState;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantBiodata;
use App\Models\ApplicantWorkspace;
use App\Models\Application;
use App\Models\ApplicationSubmission;
use App\Models\CredentialIssuance;
use App\Models\User;
use App\Models\WhatsappVerification;
use App\Support\SubmittedRegistrationData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;

require_once __DIR__.'/../../Fixtures/registration.php';

function contactConsistencyContext(): array
{
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $user = User::findOrFail($application->user_id);
    $user->forceFill(['account_state' => AccountState::Active, 'must_change_password' => false])->save();

    return [...$c, 'applicant' => $user, 'claim' => $claim->fresh(), 'application' => $application->fresh(), 'workspace' => $application->workspace->fresh()];
}

it('keeps active account claim biodata SK draft and delivery contacts consistent after each editing path', function (string $source) {
    $c = contactConsistencyContext();
    $c['workspace']->forceFill(['draft_payload' => ['whatsapp_number' => '+6281234567890', 'full_name' => 'Nama draf tetap ada']])->save();
    $issuance = CredentialIssuance::create(['user_id' => $c['applicant']->id, 'claim_id' => $c['claim']->id,
        'secret_hash' => Hash::make('RahasiaUji'), 'target_number' => '+6281234567890', 'issued_by' => $c['operator']->id,
        'expires_at' => now()->addDay(), 'delivered_at' => now(), 'delivered_by' => $c['operator']->id]);
    $verification = WhatsappVerification::create(['workspace_id' => $c['workspace']->id, 'number' => '+6281234567890',
        'verified_by' => $c['operator']->id, 'verified_at' => now(), 'evidence_summary' => 'Bukti nomor lama']);

    if ($source === 'account') {
        $this->actingAs($c['applicant'])->put(route('account.phone.update'), ['phone_number' => '081355551234'])->assertSessionHasNoErrors();
    } elseif ($source === 'claim') {
        $this->actingAs($c['operator'])->put(route('claims.update-whatsapp', $c['claim']), ['version' => $c['claim']->lock_version, 'whatsapp_number' => '081355551234'])->assertSessionHasNoErrors();
    } elseif ($source === 'SK modal') {
        Livewire::actingAs($c['operator'])->test('pages::portal.participants')->call('openPhone', $c['participant']->id)
            ->set('phoneNumber', '081355551234')->call('savePhone')->assertHasNoErrors();
    } elseif ($source === 'SK detail') {
        $participant = $c['participant']->fresh();
        $this->actingAs($c['operator'])->put(route('participants.update-sk', $participant), [
            ...$participant->only(['name_on_sk', 'sk_number', 'sk_order', 'official_participant_id', 'assigned_operator_id']),
            'sk_date' => $participant->sk_date->format('Y-m-d'), 'version' => $participant->lock_version, 'phone_number' => '081355551234',
        ])->assertSessionHasNoErrors();
    } elseif ($source === 'autosave') {
        $this->actingAs($c['applicant'])->patchJson(route('applicant.form.autosave', $c['period']), [
            'version' => $c['workspace']->lock_version, 'batch_id' => (string) Str::uuid(), 'changes' => ['whatsapp_number' => '081355551234'],
        ])->assertOk();
    } else {
        $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), [
            'version' => $c['workspace']->lock_version, 'action' => 'draft', 'whatsapp_number' => '081355551234',
        ])->assertSessionHasNoErrors();
    }

    expect($c['applicant']->fresh()->phone_number)->toBe('6281355551234');
    expect($c['participant']->fresh()->phone_number)->toBe('6281355551234');
    expect($c['claim']->fresh()->whatsapp_number)->toBe('+6281355551234');
    expect($c['workspace']->fresh()->biodata->whatsapp_number)->toBe('+6281355551234');
    if ($source !== 'form') {
        expect($c['workspace']->fresh()->draft_payload)->toBe(['whatsapp_number' => '+6281355551234', 'full_name' => 'Nama draf tetap ada']);
    }
    expect($issuance->fresh()->target_number)->toBe('+6281355551234');
    expect($issuance->fresh()->delivered_at)->toBeNull();
    expect($issuance->fresh()->delivered_by)->toBeNull();
    expect($verification->fresh()->invalidated_at)->not->toBeNull();
})->with(['account', 'claim', 'SK modal', 'SK detail', 'autosave', 'form']);

it('does not let a stale autosave restore the old phone after an account contact change', function () {
    $c = contactConsistencyContext();
    $this->actingAs($c['applicant'])->put(route('account.phone.update'), ['phone_number' => '081355551234'])->assertSessionHasNoErrors();

    $this->patchJson(route('applicant.form.autosave', $c['period']), [
        'version' => 0, 'batch_id' => (string) Str::uuid(), 'changes' => ['whatsapp_number' => '081234567890'],
    ])->assertConflict();

    expect($c['applicant']->fresh()->phone_number)->toBe('6281355551234');
    expect($c['workspace']->fresh()->biodata->whatsapp_number)->toBe('+6281355551234');
});

it('keeps incomplete autosaved phone text from overwriting usable contacts', function () {
    $c = contactConsistencyContext();

    $this->actingAs($c['applicant'])->patchJson(route('applicant.form.autosave', $c['period']), [
        'version' => 0, 'batch_id' => (string) Str::uuid(), 'changes' => ['whatsapp_number' => '08'],
    ])->assertOk();

    expect($c['workspace']->fresh()->draft_payload['whatsapp_number'])->toBe('08');
    expect($c['applicant']->fresh()->phone_number)->toBe('6281234567890');
    expect($c['claim']->fresh()->whatsapp_number)->toBe('+6281234567890');
});

it('rejects empty or invalid contact edits for an SK participant already linked to an account', function (string $phone) {
    $c = contactConsistencyContext();

    Livewire::actingAs($c['operator'])->test('pages::portal.participants')->call('openPhone', $c['participant']->id)
        ->set('phoneNumber', $phone)->call('savePhone')->assertHasErrors('phoneNumber');

    expect($c['participant']->fresh()->phone_number)->toBe('6281234567890');
    expect($c['applicant']->fresh()->phone_number)->toBe('6281234567890');
})->with(['', 'Nomor menyusul']);

it('does not change another applicants contact even when the old phone is shared', function () {
    $c = contactConsistencyContext();
    $other = User::factory()->create(['phone_number' => '6281234567890']);
    $workspace = ApplicantWorkspace::create(['user_id' => $other->id, 'admission_period_id' => $c['period']->id]);
    $biodata = ApplicantBiodata::create(['workspace_id' => $workspace->id, 'whatsapp_number' => '+6281234567890']);

    $this->actingAs($c['applicant'])->put(route('account.phone.update'), ['phone_number' => '081355551234'])->assertSessionHasNoErrors();

    expect($other->fresh()->phone_number)->toBe('6281234567890');
    expect($biodata->fresh()->whatsapp_number)->toBe('+6281234567890');
});

it('displays the current contact without rewriting a submitted registration revision', function () {
    $c = contactConsistencyContext();
    app(SaveApplicantForm::class)->handle($c['applicant'], $c['period']->id, 0, completeApplicantForm($c), true);
    $submission = ApplicationSubmission::query()->sole();
    $snapshot = $submission->encrypted_snapshot;

    $this->actingAs($c['applicant'])->put(route('account.phone.update'), ['phone_number' => '081355551234'])->assertSessionHasNoErrors();
    $display = SubmittedRegistrationData::forDisplay(Application::findOrFail($c['application']->id));

    expect($display['whatsapp_number'])->toBe('+6281355551234');
    expect($submission->fresh()->encrypted_snapshot)->toBe($snapshot);
    $this->assertDatabaseCount('application_submissions', 1);
});

it('keeps archived period contact records as history when the account phone changes', function () {
    $c = contactConsistencyContext();
    $c['period']->update(['state' => 'archived']);

    $this->actingAs($c['applicant'])->put(route('account.phone.update'), ['phone_number' => '081355551234'])->assertSessionHasNoErrors();

    expect($c['applicant']->fresh()->phone_number)->toBe('6281355551234');
    expect($c['claim']->fresh()->whatsapp_number)->toBe('+6281234567890');
    expect($c['workspace']->fresh()->biodata->whatsapp_number)->toBe('+6281234567890');
});

it('exports the same updated contact in the SK and registration phone columns', function () {
    $c = contactConsistencyContext();
    $this->actingAs($c['applicant'])->put(route('account.phone.update'), ['phone_number' => '081355551234'])->assertSessionHasNoErrors();

    $response = $this->actingAs($c['admin'])->get(route('participants.export', ['period_id' => $c['period']->id]))->assertOk();
    $file = UploadedFile::fake()->createWithContent('kontak.xlsx', $response->streamedContent());
    $book = IOFactory::load($file->getPathname());
    try {
        $rows = $book->getActiveSheet()->toArray();
        expect($rows[1][array_search('Nomor HP (SK)', $rows[0], true)])->toBe('6281355551234');
        expect($rows[1][array_search('Nomor WhatsApp Aktif', $rows[0], true)])->toBe('+6281355551234');
    } finally {
        $book->disconnectWorksheets();
    }
});

it('synchronizes the same owners contacts across multiple unarchived periods', function () {
    $c = contactConsistencyContext();
    $period = AdmissionPeriod::factory()->published()->create();
    $c['participant']->replicate()->forceFill(['admission_period_id' => $period->id, 'claimed_user_id' => null])->save();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], [...claimInput($c), 'period_id' => $period->id]);
    $workspace = ApplicantWorkspace::create(['user_id' => $c['applicant']->id, 'admission_period_id' => $period->id]);
    $biodata = ApplicantBiodata::create(['workspace_id' => $workspace->id, 'whatsapp_number' => '+6281234567890']);

    $this->actingAs($c['applicant'])->put(route('account.phone.update'), ['phone_number' => '081355551234'])->assertSessionHasNoErrors();

    expect($claim->fresh()->whatsapp_number)->toBe('+6281355551234');
    expect($biodata->fresh()->whatsapp_number)->toBe('+6281355551234');
    expect($c['claim']->fresh()->whatsapp_number)->toBe('+6281355551234');
    expect($c['workspace']->fresh()->biodata->whatsapp_number)->toBe('+6281355551234');
});
