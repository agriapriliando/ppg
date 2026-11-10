<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SubmitSkClaim;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\CredentialIssuance;
use App\Models\SkParticipant;
use App\Models\User;
use App\Models\WhatsappVerification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;
use Symfony\Component\Process\Process;

require_once __DIR__.'/../Fixtures/registration.php';

function prepareRaceDatabase(): void
{
    if (config('app.env') !== 'testing' || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'pmb_isolated_race') {
        throw new LogicException('Tes ini memerlukan database kosong khusus pmb_isolated_race, driver mysql, dan APP_ENV=testing. Jangan gunakan database aplikasi.');
    }
    test()->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
}

it('serializes competing autosave batches without losing the winning draft', function () {
    prepareRaceDatabase();
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $base = ['operation' => 'draft', 'actor' => $c['applicant']->id, 'subject' => $c['period']->id, 'version' => 0];

    expect(runAdmissionsRace([
        [...$base, 'batch_id' => (string) Str::uuid(), 'changes' => ['full_name' => 'Tab Satu']],
        [...$base, 'batch_id' => (string) Str::uuid(), 'changes' => ['full_name' => 'Tab Dua']],
    ]))->toBe(['conflict', 'ok']);
    expect($application->workspace->fresh()->lock_version)->toBe(1);
    expect($application->workspace->fresh()->draft_payload['full_name'])->toBeIn(['Tab Satu', 'Tab Dua']);
});

it('serializes autosave against final submission and keeps submitted data immutable', function () {
    prepareRaceDatabase();
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $base = ['actor' => $c['applicant']->id, 'subject' => $c['period']->id, 'version' => 0];

    expect(runAdmissionsRace([
        [...$base, 'operation' => 'draft', 'batch_id' => (string) Str::uuid(), 'changes' => ['full_name' => 'Draf Baru']],
        [...$base, 'operation' => 'submit', 'input' => completeApplicantForm($c)],
    ]))->toBe(['conflict', 'ok']);
    $application->refresh();
    if ($application->status === ApplicationStatus::Submitted) {
        expect($application->workspace->fresh()->draft_payload)->toBeNull();
        test()->assertDatabaseCount('application_submissions', 1);
    } else {
        expect($application->workspace->fresh()->draft_payload['full_name'])->toBe('Draf Baru');
        test()->assertDatabaseCount('application_submissions', 0);
    }
});

/** @return list<string> */
function runAdmissionsRace(array $operations): array
{
    $directory = storage_path('framework/testing/race-'.Str::uuid());
    mkdir($directory, 0777, true);
    $processes = [];
    try {
        foreach ($operations as $index => $operation) {
            $path = $directory.'/input-'.$index.'.json';
            file_put_contents($path, json_encode([...$operation, 'worker' => $index, 'barrier' => $directory], JSON_THROW_ON_ERROR));
            $process = new Process([PHP_BINARY, base_path('tests/Support/race-worker.php'), $path], base_path(), [
                'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => config('database.connections.mysql.host'),
                'DB_PORT' => (string) config('database.connections.mysql.port'), 'DB_DATABASE' => 'pmb_isolated_race',
                'DB_USERNAME' => config('database.connections.mysql.username'), 'DB_PASSWORD' => config('database.connections.mysql.password'),
                'QUEUE_CONNECTION' => 'sync', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
            ]);
            $process->setTimeout(25)->start();
            $processes[] = $process;
        }
        $deadline = microtime(true) + 15;
        while (count(glob($directory.'/ready-*')) !== count($operations) && microtime(true) < $deadline) {
            usleep(10000);
        }
        Assert::assertCount(count($operations), glob($directory.'/ready-*'), 'Kedua worker harus siap sebelum barrier dibuka.');
        touch($directory.'/start');
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            Assert::assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['status'];
        }
        sort($results);

        return $results;
    } finally {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
        foreach (glob($directory.'/*') as $path) {
            unlink($path);
        }
        rmdir($directory);
    }
}

it('allows only one of two simultaneous claims to acquire the same SK', function () {
    prepareRaceDatabase();
    $c = registrationContext();
    $first = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $other = User::factory()->create();
    $input = claimInput($c);
    $input['whatsapp_number'] = '081234567891';
    $input['identifier'] = '';
    $second = app(SubmitSkClaim::class)->handle($other, $input);
    $results = runAdmissionsRace([
        ['operation' => 'approve', 'actor' => $c['operator']->id, 'subject' => $first->id, 'version' => 0],
        ['operation' => 'approve', 'actor' => $c['operator']->id, 'subject' => $second->id, 'version' => 0],
    ]);
    expect($results)->toBe(['conflict', 'ok']);
    $this->assertDatabaseCount('applications', 1);
    expect($c['participant']->fresh()->claimed_user_id)->toBe(Application::query()->sole()->user_id);
});

it('rejects one of two assignments using the same period version', function () {
    prepareRaceDatabase();
    $c = registrationContext();
    SkParticipant::query()->whereKey($c['participant']->id)->update(['assigned_operator_id' => null]);
    $other = User::factory()->operator()->create();
    $version = $c['period']->fresh()->lock_version;
    $results = runAdmissionsRace([
        ['operation' => 'assign', 'actor' => $c['admin']->id, 'subject' => $c['period']->id, 'version' => $version, 'operators' => [$c['operator']->id]],
        ['operation' => 'assign', 'actor' => $c['admin']->id, 'subject' => $c['period']->id, 'version' => $version, 'operators' => [$other->id]],
    ]);
    expect($results)->toBe(['conflict', 'ok']);
    expect($c['period']->fresh()->lock_version)->toBe($version + 1);
    $this->assertDatabaseCount('assignment_events', 2);
});

it('makes simultaneous duplicate submissions idempotent with a single configuration version', function () {
    prepareRaceDatabase();
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $operation = ['operation' => 'submit', 'actor' => $c['applicant']->id, 'subject' => $c['period']->id, 'version' => 0, 'input' => completeApplicantForm($c)];
    expect(runAdmissionsRace([$operation, $operation]))->toBe(['ok', 'ok']);
    expect($application->fresh()->submission_revision)->toBe(1);
    $this->assertDatabaseCount('application_submissions', 1);
    $this->assertDatabaseCount('consent_records', 2);
    $this->assertDatabaseCount('admission_config_versions', 1);
});

it('records one review when two operators submit the same decision version', function () {
    prepareRaceDatabase();
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), completeApplicantForm($c))->assertRedirect()->assertSessionHasNoErrors();
    $operation = ['operation' => 'review', 'actor' => $c['operator']->id, 'subject' => $application->id, 'version' => $application->fresh()->lock_version];
    expect(runAdmissionsRace([$operation, $operation]))->toBe(['conflict', 'ok']);
    expect($application->fresh()->status)->toBe(ApplicationStatus::Verified);
    $this->assertDatabaseCount('application_reviews', 1);
});

it('keeps only the last issuance valid when two credentials are issued concurrently', function () {
    prepareRaceDatabase();
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    WhatsappVerification::create(['claim_id' => $claim->id, 'number' => $claim->whatsapp_number, 'verified_by' => $c['operator']->id, 'verified_at' => now(), 'evidence_summary' => 'Balasan sah dari nomor yang diperiksa.']);
    app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $operation = ['operation' => 'issue', 'actor' => $c['operator']->id, 'subject' => $claim->id];
    expect(runAdmissionsRace([$operation, $operation]))->toBe(['ok', 'ok']);
    $this->assertDatabaseCount('credential_issuances', 2);
    expect(CredentialIssuance::query()->whereNull('revoked_at')->count())->toBe(1);
});

it('commits only one correction when two administrators choose different owners concurrently', function () {
    prepareRaceDatabase();
    $c = registrationContext();
    $sourceClaim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $sourceClaim, 0);
    $application->refresh();
    $participant = $c['participant']->refresh();
    $participant->forceFill(['admin_hold' => true, 'hold_reason' => 'Penahanan untuk uji dua koreksi kepemilikan.'])->save();
    $otherAdmin = User::factory()->administrator()->create();
    $operations = [];
    foreach ([$c['admin'], $otherAdmin] as $index => $admin) {
        $target = User::factory()->create();
        $input = claimInput($c);
        $input['whatsapp_number'] = $index === 0 ? '081234567891' : '081234567892';
        $targetClaim = app(SubmitSkClaim::class)->handle($target, $input);
        $operations[] = ['operation' => 'ownership', 'actor' => $admin->id, 'subject' => $application->id, 'input' => ['target_claim_id' => $targetClaim->id, 'application_version' => $application->lock_version, 'participant_version' => $participant->lock_version, 'target_claim_version' => 0, 'source_workspace_version' => 0, 'target_workspace_version' => 0, 'reason' => 'Koreksi kaitan akun berdasarkan pemeriksaan identitas kedua pihak.', 'evidence' => 'Akses akun tujuan dan sumber SK diperiksa melalui kanal resmi.', 'source_identity_checked' => 1, 'target_identity_checked' => 1, 'target_account_confirmed' => 1, 'privacy_confirmed' => 1]];
    }
    expect(runAdmissionsRace($operations))->toBe(['conflict', 'ok']);
    expect($application->fresh()->ownership_epoch)->toBe(1)->and($participant->fresh()->claimed_user_id)->toBe($application->fresh()->user_id);
    $this->assertDatabaseCount('applications', 1);
    $this->assertDatabaseCount('application_ownership_transfers', 1);
    $this->assertDatabaseCount('administrative_cases', 1);
});
