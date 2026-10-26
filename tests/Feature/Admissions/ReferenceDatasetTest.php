<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\PublishReferenceDataset;
use App\Actions\Admissions\SubmitSkClaim;
use App\Models\AdministrativeCase;
use App\Models\ReferenceDataset;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../../Fixtures/registration.php';

it('keeps the active dataset unchanged when a new dataset has invalid parent relationships', function () {
    $c = registrationContext();
    $bad = $c['reference'];
    $bad['version'] = 'uji-invalid';
    $bad['regions'][2]['parent_code'] = 'not-a-city';
    expect(fn () => app(PublishReferenceDataset::class)->handle($c['admin'], $bad))->toThrow(ValidationException::class);
    expect(ReferenceDataset::query()->where('state', 'active')->sole()->version)->toBe('uji-1');
    $this->assertDatabaseCount('reference_datasets', 1);
});

it('paginates reference lookup and restricts programs to the selected university', function () {
    $c = registrationContext();
    $this->actingAs($c['applicant'])->getJson(route('references.lookup', ['type' => 'prodi', 'parent' => 'pt-uji']))->assertOk()->assertJsonPath('data.0.code', 'prodi-uji')->assertJsonPath('has_more', false);
    $this->getJson(route('references.lookup', ['type' => 'prodi', 'parent' => 'other-pt']))->assertOk()->assertExactJson(['data' => [], 'has_more' => false]);
    $this->getJson(route('references.lookup', ['type' => 'city']))->assertOk()->assertExactJson(['data' => [], 'has_more' => false]);
});

it('blocks a shared WhatsApp claim until an administrator records a resolution', function () {
    $c = registrationContext();
    app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $other = User::factory()->create();
    $participant = SkParticipant::create(['admission_period_id' => $c['period']->id, 'sk_number' => 'SK-UJI-001', 'sk_date' => '2026-10-01', 'sk_order' => 2, 'name_on_sk' => $other->name, 'normalized_name' => mb_strtolower(Str::squish($other->name)), 'official_participant_id' => '000002', 'assigned_operator_id' => $c['operator']->id]);
    $input = claimInput($c);
    $input['name'] = $other->name;
    $input['identifier'] = '000002';
    $claim = app(SubmitSkClaim::class)->handle($other, $input);
    $case = AdministrativeCase::query()->sole();
    expect(fn () => app(ApproveClaim::class)->handle($c['operator'], $claim, 0))->toThrow(ValidationException::class);
    $resolution = ['resolution' => 'Kedua pemohon dan bukti identitas diperiksa; penggunaan nomor keluarga telah dikonfirmasi.', 'confirmed' => 1];
    $this->actingAs($c['operator'])->post(route('admin.cases.resolve', $case), $resolution)->assertForbidden();
    $this->actingAs($c['admin'])->post(route('admin.cases.resolve', $case), $resolution)->assertRedirect()->assertSessionHasNoErrors();
    expect($case->fresh()->state)->toBe('resolved');
    $app = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    expect($app->sk_participant_id)->toBe($participant->id);
});
