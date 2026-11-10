<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SubmitSkClaim;
use App\Livewire\Documents\SubmitRegistration;
use App\Models\ApplicantWorkspace;
use App\Models\ApplicationSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;

require_once __DIR__.'/../../Fixtures/registration.php';

it('requires Peg ID before submitting while allowing an empty draft', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), [
        'version' => 0, 'action' => 'draft', 'peg_id' => '',
    ])->assertSessionHasNoErrors();

    $this->post(route('applicant.form.update', $c['period']), array_merge(completeApplicantForm($c), [
        'version' => 1, 'peg_id' => '   ',
    ]))->assertSessionHasErrors('peg_id');
    $this->assertDatabaseCount('application_submissions', 0);

    $this->get(route('applicant.form.edit', $c['period']))->assertOk()
        ->assertSee('Nomor Peg ID wajib diisi sebelum mengirim pendaftaran.');
    Livewire::test(SubmitRegistration::class, ['workspaceId' => $application->workspace_id])
        ->call('openConfirmation')->assertSet('showConfirmation', false)->assertHasErrors('peg_id');
});

it('autosaves partial additional identifiers and restores the residence selection', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $changes = ['peg_id' => '000123', 'nuptk' => '00123', 'residence_region' => 'WIT'];
    $this->actingAs($c['applicant'])->patchJson(route('applicant.form.autosave', $c['period']), [
        'version' => 0, 'batch_id' => (string) Str::uuid(), 'changes' => $changes,
    ])->assertOk()->assertJsonPath('version', 1);
    $workspace = $application->workspace->fresh();
    foreach ($changes as $key => $value) {
        expect($workspace->draft_payload[$key])->toBe($value);
    }
    expect($workspace->getRawOriginal('draft_payload'))->not->toContain('00123');
    $this->get(route('applicant.form.edit', $c['period']))->assertOk()
        ->assertSee('value="00123"', false)->assertSee('value="WIT" selected', false);
});

it('preserves additional identifiers and residence category through submission display print and export', function () {
    Storage::fake('local');
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $additional = ['peg_id' => '000123ABC', 'nuptk' => '0012345678901234', 'residence_region' => 'WITA'];

    $this->actingAs($c['applicant'])->get(route('applicant.form.edit', $c['period']))->assertOk()
        ->assertSee('Nomor Peg ID')->assertSee('NUPTK')
        ->assertSee('Wilayah Indonesia Bagian Barat (WIB)')
        ->assertSee('Wilayah Indonesia Bagian Tengah (WITA)')
        ->assertSee('Wilayah Indonesia Bagian Timur (WIT)');
    $this->post(route('applicant.form.update', $c['period']), array_merge(completeApplicantForm($c), $additional, [
        'version' => 0, 'action' => 'submit',
    ]))->assertSessionHasNoErrors()->assertRedirect(route('applicant.application.status', $c['period']));

    $biodata = $application->workspace->fresh()->biodata;
    expect($biodata->peg_id)->toBe($additional['peg_id'])
        ->and($biodata->nuptk_encrypted)->toBe($additional['nuptk'])
        ->and($biodata->residence_region)->toBe('WITA')
        ->and(DB::table('applicant_biodata')->where('id', $biodata->id)->value('nuptk_encrypted'))->not->toBe($additional['nuptk']);
    $snapshot = json_decode(ApplicationSubmission::query()->sole()->encrypted_snapshot, true);
    foreach ($additional as $key => $value) {
        expect($snapshot[$key])->toBe($value);
    }
    foreach (['reviews.show' => $application, 'reviews.print' => $application, 'participants.show' => $application->sk_participant_id] as $route => $parameter) {
        $this->actingAs($c['operator'])->get(route($route, $parameter))->assertOk()
            ->assertSee($additional['peg_id'])->assertSee($additional['nuptk'])
            ->assertSee('Wilayah Indonesia Bagian Tengah (WITA)');
    }
    $response = $this->actingAs($c['admin'])->get(route('participants.export'))->assertOk();
    Storage::disk('local')->put('additional-identity.xlsx', $response->streamedContent());
    $book = IOFactory::load(Storage::disk('local')->path('additional-identity.xlsx'));
    $rows = $book->getActiveSheet()->toArray();
    foreach (['Nomor Peg ID (Biodata)' => $additional['peg_id'], 'NUPTK' => $additional['nuptk'], 'Kategori Wilayah Tempat Tinggal' => 'Wilayah Indonesia Bagian Tengah (WITA)'] as $header => $value) {
        expect($rows[1][array_search($header, $rows[0], true)])->toBe($value);
    }
    $book->disconnectWorksheets();
});

it('rejects invalid additional identifiers and residence categories', function (string $key, string $value) {
    $c = registrationContext();
    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), [
        'version' => 0, 'action' => 'draft', $key => $value,
    ])->assertSessionHasErrors($key);
})->with([
    ['nuptk', '123456789012345'],
    ['nuptk', '12345678901234567'],
    ['nuptk', '123456789012345a'],
    ['npwp', '0123ABC'],
    ['npwp', '01.234.567-8'],
    ['residence_region', 'GMT'],
    ['peg_id', str_repeat('1', 101)],
]);

it('preserves leading zeroes when saving a digits only NPWP', function () {
    $c = registrationContext();

    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), [
        'version' => 0, 'action' => 'draft', 'npwp' => '0012345678901234',
    ])->assertSessionHasNoErrors();

    $workspace = ApplicantWorkspace::query()->where('user_id', $c['applicant']->id)->where('admission_period_id', $c['period']->id)->firstOrFail();
    expect($workspace->biodata->npwp_encrypted)->toBe('0012345678901234');
});

it('shows a clear NPWP notification and correction link on the documents page', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $application->workspace->forceFill(['draft_payload' => [...completeApplicantForm($c), 'npwp' => '01.234.567-8']])->save();
    $message = 'NPWP hanya boleh berisi angka 0-9, tanpa titik, tanda hubung, spasi, atau huruf. Perbaiki kolom NPWP pada bagian Tambahan.';
    $correctionUrl = route('applicant.form.edit', ['period' => $c['period']->id, 'section' => 3]).'#form-npwp';

    $this->actingAs($c['applicant'])->get(route('applicant.documents.index', $c['period']))->assertOk()
        ->assertSee('NPWP perlu diperbaiki')->assertSee($message)
        ->assertSee('href="'.e($correctionUrl).'"', false);

    Livewire::test(SubmitRegistration::class, ['workspaceId' => $application->workspace_id])
        ->call('openConfirmation')->assertSet('showConfirmation', false)->assertHasErrors('npwp')
        ->assertSee($message);
    $this->assertDatabaseCount('application_submissions', 0);
});

it('shows the clear NPWP error beside its field when saving or submitting the form', function (string $action) {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $message = 'NPWP hanya boleh berisi angka 0-9, tanpa titik, tanda hubung, spasi, atau huruf. Perbaiki kolom NPWP pada bagian Tambahan.';

    $this->actingAs($c['applicant'])->followingRedirects()->from(route('applicant.form.edit', $c['period']))
        ->post(route('applicant.form.update', $c['period']), [
            ...completeApplicantForm($c), 'action' => $action, 'npwp' => '01.234.567-8',
        ])->assertOk()
        ->assertSee('id="npwp-error"', false)->assertSee($message);
    $this->assertDatabaseCount('application_submissions', 0);
})->with(['draft', 'submit']);

it('saves each supported residence category without reference data changes', function (string $region) {
    $c = registrationContext();
    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), [
        'version' => 0, 'action' => 'draft', 'residence_region' => $region,
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('applicant_biodata', ['residence_region' => $region]);
})->with(['WIB', 'WITA', 'WIT']);
