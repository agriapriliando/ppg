<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SaveApplicantDraftPatch;
use App\Actions\Admissions\SaveApplicantForm;
use App\Actions\Admissions\SubmitSkClaim;
use App\Enums\ApplicationStatus;
use App\Models\ReferenceOption;
use Illuminate\Support\Str;

require_once __DIR__.'/../../Fixtures/registration.php';

it('shows every submitted biodata field and resolves reference labels on participant details', function () {
    $c = registrationContext();
    ReferenceOption::query()->where('group', 'gender')->update(['label' => 'Laki-laki']);
    ReferenceOption::query()->where('group', 'religion')->update(['label' => 'Kristen']);
    ReferenceOption::query()->where('group', 'nationality')->update(['label' => 'Indonesia']);
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $input = array_merge(completeApplicantForm($c), ['full_name' => 'Nama Biodata Lengkap', 'hamlet' => 'Dusun Mawar', 'contact_email' => 'peserta@example.test', 'other_phone' => '081355551234']);
    app(SaveApplicantForm::class)->handle($c['applicant'], $c['period']->id, 0, $input, true);

    $response = $this->actingAs($c['operator'])->get(route('participants.show', $c['participant']))->assertOk();

    foreach (['Nama Biodata Lengkap', '0000000000000001', '0000000001', 'Ibu Uji', 'Laki-laki', 'Kristen', 'Indonesia', 'Jalan Uji', 'Desa Uji', 'Kecamatan Uji', 'Kota Uji', 'Provinsi Uji', 'Dusun Mawar', 'peserta@example.test', '081355551234', 'Informasi Tambahan', 'Penerima KPS', 'Tidak', 'S1', '2012'] as $value) {
        $response->assertSee($value);
    }
    expect($response->viewData('display')['gender_code'])->toBe('Laki-laki');
    expect($response->viewData('display')['district_code'])->toBe('Kecamatan Uji');

    $application->forceFill(['status' => ApplicationStatus::Correction])->save();
    $workspace = $application->workspace->fresh();
    app(SaveApplicantDraftPatch::class)->handle($c['applicant'], $c['period']->id, $workspace->lock_version, (string) Str::uuid(), ['full_name' => 'Draf Belum Dikirim']);
    $this->get(route('participants.show', $c['participant']))->assertOk()->assertSee('Nama Biodata Lengkap')->assertDontSee('Draf Belum Dikirim');
});

it('shows effective autosaved biodata on a participant who has not submitted yet', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    app(SaveApplicantDraftPatch::class)->handle($c['applicant'], $c['period']->id, 0, (string) Str::uuid(), ['full_name' => 'Nama Draf Baru', 'street_address' => 'Alamat Draf Baru']);

    $this->actingAs($c['admin'])->get(route('participants.show', $c['participant']))->assertOk()
        ->assertSee('Nama Draf Baru')->assertSee('Alamat Draf Baru')->assertSee('Draf peserta yang belum dikirim.');
});
