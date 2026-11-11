<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SubmitSkClaim;
use App\Actions\Documents\UploadApplicantDocument;
use App\Livewire\Documents\DocumentManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

require_once __DIR__.'/../../Fixtures/registration.php';

it('searches stored document names for applicants and authorized staff without changing capacity', function (string $role) {
    Storage::fake('local');
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $uploader = app(UploadApplicantDocument::class);
    $first = $uploader->handle($c['applicant'], $application->workspace, UploadedFile::fake()->create('ijazah-asli.pdf', 10, 'application/pdf'), 'search-1');
    $first->forceFill(['display_name' => 'Dokumen Pendidikan'])->save();
    $uploader->handle($c['applicant'], $application->workspace, UploadedFile::fake()->create('surat-tugas.pdf', 10, 'application/pdf'), 'search-2');

    $component = Livewire::actingAs($c[$role])->test(DocumentManager::class, [
        'workspaceId' => $application->workspace_id, 'periodId' => $c['period']->id,
    ])->assertSee('Dokumen Pendidikan')->assertSee('surat-tugas');

    $component->set('documentSearch', '  PENDIDIKAN  ')->assertSee('Dokumen Pendidikan')->assertDontSee('surat-tugas')
        ->assertViewHas('totalBytesUsed', 20 * 1024);
    $component->set('documentSearch', 'IJAZAH-ASLI.PDF')->assertSee('Dokumen Pendidikan')->assertDontSee('surat-tugas');
    $component->set('documentSearch', 'tidak ada')->assertSee('Tidak ada dokumen yang cocok')->assertDontSee('Dokumen Pendidikan');
    $component->set('documentSearch', '')->assertSee('Dokumen Pendidikan')->assertSee('surat-tugas');
})->with(['applicant', 'operator', 'admin']);

it('shows the upload time with the display name without exposing the original name on the uploads page', function (string $role) {
    Storage::fake('local');
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $document = app(UploadApplicantDocument::class)->handle($c['applicant'], $application->workspace, UploadedFile::fake()->create('nama-asli-rahasia.pdf', 10, 'application/pdf'), 'display-test');
    $document->forceFill(['display_name' => 'Nama Tampilan'])->save();

    $this->actingAs($c[$role])->get(route('admin.document-policies.uploads'))
        ->assertOk()->assertSee('Nama Tampilan')->assertDontSee('nama-asli-rahasia')
        ->assertSee('Diunggah '.$document->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB')
        ->assertDontSee('<th>Waktu</th>', false);
})->with(['admin', 'operator']);
