<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SaveApplicantDraftPatch;
use App\Actions\Admissions\SaveApplicantForm;
use App\Actions\Admissions\SubmitSkClaim;
use App\Enums\ApplicationStatus;
use App\Models\ReferenceDataset;
use App\Models\ReferenceOption;
use Illuminate\Support\Str;

require_once __DIR__.'/../../Fixtures/registration.php';

it('matches participant detail biodata and education for autosaved drafts', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    ReferenceOption::query()->where('group', 'gender')->update(['label' => 'Jenis Kelamin Uji']);
    app(SaveApplicantDraftPatch::class)->handle($c['applicant'], $c['period']->id, 0, (string) Str::uuid(), [
        'full_name' => 'Nama Draf Terkini', 'gender_code' => 'uji', 'province_code' => '01', 'city_code' => '0101', 'district_code' => '010101',
        'street_address' => 'Alamat Draf Terkini', 'degree_level' => 'S1', 'education_history.S1.pt_name' => 'Universitas Draf',
        'education_history.S1.prodi_name' => 'Program Studi Draf', 'education_history.S1.graduation_year' => '2012',
    ]);

    $participant = $this->actingAs($c['operator'])->get(route('participants.show', $c['participant']))->assertOk();
    $review = $this->get(route('reviews.show', $application))->assertOk();

    expect($review->viewData('data'))->toBe($participant->viewData('data'));
    expect($review->viewData('display'))->toBe($participant->viewData('display'));
    foreach (['Nama Draf Terkini', 'Jenis Kelamin Uji', 'Kecamatan Uji', 'Kota Uji', 'Provinsi Uji', 'Universitas Draf', 'Program Studi Draf', '2012', 'Draf peserta yang belum dikirim.'] as $value) {
        $review->assertSee($value);
    }
    $this->get(route('reviews.print', $application))->assertOk()->assertSee('Universitas Draf')->assertSee('Jenis Kelamin Uji');
});

it('matches participant detail submitted biodata despite newer draft or reference changes', function () {
    $c = registrationContext();
    ReferenceOption::query()->where('group', 'gender')->update(['label' => 'Label Saat Pengiriman']);
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    app(SaveApplicantForm::class)->handle($c['applicant'], $c['period']->id, 0, [
        ...completeApplicantForm($c), 'full_name' => 'Nama Pada Pengiriman',
    ], true);
    $application->forceFill(['status' => ApplicationStatus::Correction])->save();
    $workspace = $application->workspace->fresh();
    app(SaveApplicantDraftPatch::class)->handle($c['applicant'], $c['period']->id, $workspace->lock_version, (string) Str::uuid(), ['full_name' => 'Nama Draf Perbaikan']);
    $newDataset = ReferenceDataset::create(['version' => 'versi-lain', 'source' => 'Referensi uji lain', 'state' => 'active', 'created_by' => $c['admin']->id, 'activated_at' => now()]);
    ReferenceOption::create(['dataset_id' => $newDataset->id, 'group' => 'gender', 'code' => 'uji', 'label' => 'Label Referensi Berbeda']);
    $application->workspace->education->update(['reference_dataset_id' => $newDataset->id]);

    $participant = $this->actingAs($c['operator'])->get(route('participants.show', $c['participant']))->assertOk();
    $review = $this->get(route('reviews.show', $application))->assertOk();

    expect($review->viewData('data'))->toBe($participant->viewData('data'));
    expect($review->viewData('display'))->toBe($participant->viewData('display'));
    $review->assertSee('Nama Pada Pengiriman')->assertSee('Label Saat Pengiriman')
        ->assertSee('Data pada pengiriman terakhir peserta.')->assertDontSee('Nama Draf Perbaikan')->assertDontSee('Label Referensi Berbeda');
    $this->get(route('reviews.print', $application))->assertOk()->assertSee('Label Saat Pengiriman')->assertDontSee('Label Referensi Berbeda');
});
