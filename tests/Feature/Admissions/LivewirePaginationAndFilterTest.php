<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SubmitSkClaim;
use App\Actions\Documents\UploadApplicantDocument;
use App\Models\ApplicantWorkspace;
use App\Models\SkParticipant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

require_once __DIR__.'/../../Fixtures/registration.php';

beforeEach(function () {
    Storage::fake('local');
});

it('supports livewire reactive filtering and per page switching on participants page', function () {
    $c = registrationContext();

    // Create extra participants to test pagination
    for ($i = 2; $i <= 15; $i++) {
        SkParticipant::create([
            'admission_period_id' => $c['period']->id,
            'sk_number' => 'SK-UJI-001',
            'sk_date' => '2026-10-01',
            'sk_order' => $i,
            'name_on_sk' => 'Peserta Nomor '.$i,
            'normalized_name' => 'peserta nomor '.$i,
            'official_participant_id' => sprintf('%06d', $i),
            'assigned_operator_id' => $c['operator']->id,
        ]);
    }

    $component = Livewire::actingAs($c['operator'])
        ->test('pages::portal.participants')
        ->assertOk()
        ->assertSee($c['participant']->name_on_sk)
        ->assertSee('Jumlah per halaman:');

    // Test per_page change to 10
    $component->call('setPerPage', 10)
        ->assertSet('perPage', 10)
        ->assertSee('Peserta Nomor 2');

    // Test search filter
    $component->set('search', 'Nomor 15')
        ->assertSee('Peserta Nomor 15')
        ->assertDontSee('Peserta Nomor 2');

    // Reset search
    $component->set('search', '')
        ->assertSee('Peserta Nomor 2');

    // Test unauthorized
    Livewire::actingAs($c['applicant'])
        ->test('pages::portal.participants')
        ->assertForbidden();
});

it('supports livewire reactive filtering and per page switching on claims page', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));

    $component = Livewire::actingAs($c['operator'])
        ->test('pages::portal.claims')
        ->assertOk()
        ->assertSee($claim->submitted_name)
        ->assertSee('Jumlah per halaman:');

    // Test per_page
    $component->call('setPerPage', 10)
        ->assertSet('perPage', 10);

    // Test status filter
    $component->set('status', 'approved')
        ->assertDontSee($claim->submitted_name);

    $component->set('status', 'pending')
        ->assertSee($claim->submitted_name);

    // Test search filter
    $component->set('search', 'NonExistentClaim')
        ->assertDontSee($claim->submitted_name);

    $component->set('search', '')
        ->assertSee($claim->submitted_name);

    // Test unauthorized
    Livewire::actingAs($c['applicant'])
        ->test('pages::portal.claims')
        ->assertForbidden();
});

it('supports livewire reactive filtering and per page switching on review queue page', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);

    $component = Livewire::actingAs($c['operator'])
        ->test('pages::portal.review-queue')
        ->assertOk()
        ->assertSee($c['participant']->name_on_sk)
        ->assertSee($application->application_number)
        ->assertSee('Jumlah per halaman:');

    // Test per_page
    $component->call('setPerPage', 10)
        ->assertSet('perPage', 10);

    // Test search filter
    $component->set('search', 'NonExistentPmbNumber')
        ->assertDontSee($application->application_number);

    $component->set('search', '')
        ->assertSee($application->application_number);

    // Test unauthorized
    Livewire::actingAs($c['applicant'])
        ->test('pages::portal.review-queue')
        ->assertForbidden();
});

it('supports livewire reactive filtering and per page switching on document uploads page', function () {
    $c = registrationContext();

    $workspace = ApplicantWorkspace::create([
        'user_id' => $c['applicant']->id,
        'admission_period_id' => $c['period']->id,
    ]);

    $filePdf = UploadedFile::fake()->create('berkas_saya.pdf', 250, 'application/pdf');
    $fileJpg = UploadedFile::fake()->create('foto_saya.jpg', 150, 'image/jpeg');

    app(UploadApplicantDocument::class)->handle($c['applicant'], $workspace, $filePdf, 'up-pdf');
    app(UploadApplicantDocument::class)->handle($c['applicant'], $workspace, $fileJpg, 'up-jpg');

    $component = Livewire::actingAs($c['admin'])
        ->test('pages::admin.document-uploads')
        ->assertOk()
        ->assertSee('berkas_saya')
        ->assertSee('foto_saya')
        ->assertSee('Jumlah per halaman:');

    // Test per_page
    $component->call('setPerPage', 10)
        ->assertSet('perPage', 10);

    // Filter by extension
    $component->set('extension', 'pdf')
        ->assertSee('berkas_saya')
        ->assertDontSee('foto_saya');

    // Filter by search
    $component->call('resetFilters')
        ->assertSet('extension', '')
        ->assertSee('berkas_saya')
        ->assertSee('foto_saya');

    $component->set('search', 'foto_saya')
        ->assertSee('foto_saya')
        ->assertDontSee('berkas_saya');

    // Test operator can also access
    Livewire::actingAs($c['operator'])
        ->test('pages::admin.document-uploads')
        ->assertOk();

    // Test unauthorized for applicant
    Livewire::actingAs($c['applicant'])
        ->test('pages::admin.document-uploads')
        ->assertForbidden();
});
