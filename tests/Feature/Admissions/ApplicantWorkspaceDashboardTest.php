<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SubmitSkClaim;
use App\Models\ApplicantDocument;
use App\Models\ApplicantWorkspace;
use App\Models\DocumentPolicyVersion;
use Illuminate\Support\Str;

require_once __DIR__.'/../../Fixtures/registration.php';

it('highlights accepted registration prominently only for the accepted status', function (string $status) {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $application->forceFill(['status' => $status, 'accepted_at' => $status === 'diterima_lengkap' ? now() : null])->save();

    $response = $this->actingAs($c['applicant'])->get(route('dashboard'))->assertOk();

    if ($status === 'diterima_lengkap') {
        $response->assertSee('Pendaftaran Anda Sudah Diterima Lengkap')->assertSee($application->application_number)
            ->assertSee('Lihat Status Pendaftaran');
    } else {
        $response->assertDontSee('Pendaftaran Anda Sudah Diterima Lengkap');
    }
})->with(['diterima_lengkap', 'terkirim', 'perbaikan', 'draf']);

it('displays application and sk claim sections directly in workspace dashboard for applicant and hides them from sidebar', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);

    // Create active document policy and upload a sample document to test docCount badge
    $policy = DocumentPolicyVersion::create([
        'admission_period_id' => $c['period']->id,
        'version' => 1,
        'allowed_extensions' => ['pdf', 'jpg', 'png'],
        'max_file_size_bytes' => 5242880,
        'workspace_quota_bytes' => 52428800,
        'created_by' => $c['admin']->id,
    ]);
    $workspace = ApplicantWorkspace::query()->where('user_id', $c['applicant']->id)->first();
    expect($workspace)->not->toBeNull();

    ApplicantDocument::create([
        'workspace_id' => $workspace->id,
        'admission_period_id' => $c['period']->id,
        'owner_user_id' => $c['applicant']->id,
        'policy_version_id' => $policy->id,
        'original_name' => 'ijazah.pdf',
        'display_name' => 'ijazah.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'byte_size' => 1024,
        'disk' => 'private',
        'object_key' => 'applicant-documents/dummy.pdf',
        'uploaded_by' => $c['applicant']->id,
        'status' => ApplicantDocument::STATUS_AVAILABLE,
        'lock_version' => 0,
        'request_key' => (string) Str::uuid(),
    ]);

    $response = $this->actingAs($c['applicant'])->get(route('dashboard'));

    $response->assertOk();

    // Verify sidebar navigation does not contain separate links for claims and applications
    $content = $response->getContent();
    expect($content)->not->toContain('<a href="'.route('applicant.claims').'"')
        ->and($content)->not->toContain('/pendaftar/pendaftaran');

    // Verify the sections are rendered directly on the workspace dashboard
    $response->assertSee('Pendaftaran Saya')
        ->assertSee('Kepesertaan SK Resmi')
        ->assertSee($c['period']->name)
        ->assertSee($application->application_number)
        ->assertSee('Berkas Saya')
        ->assertSee('Status & Perbaikan', false)
        ->assertSee('Daftar Akun')
        ->assertSee('Disetujui')
        ->assertSee($claim->public_reference);
});

it('displays claim response form on workspace dashboard when claim needs information', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $claim->update([
        'status' => 'needs_information',
        'public_note' => 'Mohon sertakan surat keterangan klarifikasi nama.',
    ]);

    $response = $this->actingAs($c['applicant'])->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Perlu informasi tambahan')
        ->assertSee('Mohon sertakan surat keterangan klarifikasi nama.')
        ->assertSee('Kirim Informasi Tambahan')
        ->assertSee('Batalkan permohonan ini');
});

it('preserves operator and administrator sidebar menus and metric summary', function () {
    $c = registrationContext();

    $responseAdmin = $this->actingAs($c['admin'])->get(route('dashboard'));
    $responseAdmin->assertOk()
        ->assertSee('Peserta SK')
        ->assertSee('Akun WhatsApp', false)
        ->assertSee('Antrean Verifikasi')
        ->assertSee('Periode & Gelombang', false);

    $responseOperator = $this->actingAs($c['operator'])->get(route('dashboard'));
    $responseOperator->assertOk()
        ->assertSee('Peserta SK')
        ->assertSee('Akun WhatsApp', false)
        ->assertSee('Antrean Verifikasi');
});
