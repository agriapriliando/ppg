<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SubmitSkClaim;
use App\Enums\AccountState;
use App\Enums\ApplicationStatus;
use App\Models\AdministrativeCase;
use App\Models\Application;
use App\Models\SkClaim;
use App\Models\SkParticipant;
use App\Models\WhatsappVerification;
use Illuminate\Support\Str;

require_once __DIR__.'/../../Fixtures/registration.php';

it('allows operators to match administrative candidates on claims', function () {
    $c = registrationContext();
    $input = claimInput($c);
    $input['name'] = 'Nama Belum Cocok';
    $input['identifier'] = null;
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], $input);

    $this->actingAs($c['operator'])->get(route('claims.show', $claim))
        ->assertOk()
        ->assertSee('Kandidat resmi pada periode ini')
        ->assertDontSee('Dasar pencocokan')
        ->assertSee('Ketik minimal 2 karakter nama, Peg ID, atau nomor SK');

    $this->post(route('claims.candidate', $claim), [
        'candidate_id' => $c['participant']->id,
        'version' => 0,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($claim->refresh()->candidate_sk_participant_id)->toBe($c['participant']->id);
});

it('allows operators to approve claims directly without blocking administrative cases', function () {
    $c = registrationContext();
    $firstClaim = app(SubmitSkClaim::class)->handle(null, claimInput($c));

    // Ajukan klaim kedua dengan nomor WhatsApp yang sama pada periode yang sama
    $otherParticipant = SkParticipant::create([
        'admission_period_id' => $c['period']->id,
        'sk_number' => 'SK-002',
        'sk_date' => now()->toDateString(),
        'sk_order' => 2,
        'official_participant_id' => '000002',
        'name_on_sk' => 'Budi Santoso',
        'normalized_name' => 'budi santoso',
        'assigned_operator_id' => $c['operator']->id,
        'roster_state' => 'active',
        'admin_hold' => false,
    ]);

    $secondInput = claimInput($c);
    $secondInput['name'] = 'Budi Santoso';
    $secondInput['identifier'] = '000002';
    $secondInput['request_key'] = (string) Str::uuid();
    $secondClaim = app(SubmitSkClaim::class)->handle(null, $secondInput);

    // Memastikan tidak ada kasus administratif yang dibuat
    expect(AdministrativeCase::query()->where('claim_id', $secondClaim->id)->count())->toBe(0);

    // Operator membuka halaman klaim kedua dan tombol setujui klaim langsung aktif
    $this->actingAs($c['operator'])->get(route('claims.show', $secondClaim))
        ->assertOk()
        ->assertDontSee('Kasus Administratif Terbuka')
        ->assertSee('Setujui Akun');

    // Operator dapat langsung menyetujui klaim
    $this->post(route('claims.approve', $secondClaim), [
        'version' => 0,
        'identity_checked' => 1,
        'sk_checked' => 1,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($secondClaim->refresh()->status)->toBe('approved');
});

it('allows operators to delete pending claims from the claims list', function () {
    $c = registrationContext();
    $input = claimInput($c);
    $claim = app(SubmitSkClaim::class)->handle(null, $input);
    $claim->refresh();
    expect($claim->status)->toBe('pending');

    // Tampil tombol hapus di daftar klaim
    $this->actingAs($c['operator'])->get(route('claims.index'))
        ->assertOk()
        ->assertSee('Hapus')
        ->assertSee(route('claims.destroy', $claim));

    // Hapus klaim
    $this->delete(route('claims.destroy', $claim))
        ->assertRedirect(route('claims.index'))
        ->assertSessionHas('status', 'Permohonan klaim berhasil dihapus.');

    expect(SkClaim::query()->whereKey($claim->id)->exists())->toBeFalse();

    // Operator juga dapat menghapus klaim WhatsApp yang telah disetujui.
    $approvedInput = claimInput($c);
    $approvedInput['request_key'] = (string) Str::uuid();
    $approvedClaim = app(SubmitSkClaim::class)->handle(null, $approvedInput);
    app(ApproveClaim::class)->handle($c['operator'], $approvedClaim, 0);

    $this->delete(route('claims.destroy', $approvedClaim))
        ->assertRedirect(route('claims.index'))->assertSessionHasNoErrors();
    $this->assertSoftDeleted($approvedClaim);
    $this->assertDatabaseHas('applications', ['sk_participant_id' => $c['participant']->id]);
});

it('allows operators to access and perform whatsapp recovery when old number is lost', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    WhatsappVerification::create(['claim_id' => $claim->id, 'number' => $claim->whatsapp_number, 'verified_by' => $c['operator']->id, 'verified_at' => now(), 'evidence_summary' => 'Balasan nomor asli diperiksa oleh operator.']);
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $claim->refresh();
    $owner = $claim->requester;
    $owner->forceFill(['account_state' => AccountState::Active, 'password' => 'SandiLamaPeserta2026!', 'must_change_password' => false, 'remember_token' => 'token-lama'])->save();

    $this->actingAs($c['operator'])->get(route('claims.show', $claim))
        ->assertOk()
        ->assertSee('Edit nomor HP')->assertDontSee('Pemulihan Saat Nomor Lama Hilang');

    $this->get(route('admin.whatsapp-recovery.edit', $claim))
        ->assertOk()
        ->assertSee('Pemulihan Nomor WhatsApp');

    $payload = [
        'claim_version' => $claim->fresh()->lock_version,
        'application_version' => $application->fresh()->lock_version,
        'workspace_version' => $application->workspace->fresh()->lock_version,
        'whatsapp_number' => '081234567899',
        'whatsapp_number_confirmation' => '081234567899',
        'reason' => 'Nomor lama hilang; pemilik mengajukan penggantian melalui layanan resmi.',
        'evidence' => 'Identitas SK dan pemilik akun diperiksa; balasan nomor baru diterima pada jam layanan.',
        'identity_checked' => 1,
        'reply_verified' => 1,
        'access_revocation_confirmed' => 1,
    ];

    $this->post(route('admin.whatsapp-recovery.update', $claim), $payload)
        ->assertRedirect(route('claims.show', $claim))
        ->assertSessionHasNoErrors();
});

it('allows operators to cancel approval of an approved claim', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);

    $claim->refresh();
    expect($claim->status)->toBe('approved');
    expect($c['participant']->refresh()->claimed_user_id)->toBe($claim->requester_user_id);
    expect(Application::query()->whereKey($application->id)->exists())->toBeTrue();

    // Halaman klaim menampilkan tombol pembatalan persetujuan
    $this->actingAs($c['operator'])->get(route('claims.show', $claim))
        ->assertOk()
        ->assertSee('Batalkan Persetujuan Klaim')
        ->assertSee('Konfirmasi Pembatalan Persetujuan');

    // Operator membatalkan persetujuan
    $this->post(route('claims.cancel-approval', $claim), [
        'version' => $claim->lock_version,
        'confirmed' => 1,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $claim->refresh();
    expect($claim->status)->toBe('pending');
    expect($claim->reviewed_by)->toBeNull();
    expect($c['participant']->refresh()->claimed_user_id)->toBeNull();
    expect(Application::query()->whereKey($application->id)->exists())->toBeFalse();
});

it('prevents cancelling claim approval if application is already accepted', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);

    $application->forceFill([
        'status' => ApplicationStatus::Accepted,
        'accepted_at' => now(),
    ])->save();

    $this->actingAs($c['operator'])->post(route('claims.cancel-approval', $claim), [
        'version' => $claim->fresh()->lock_version,
        'confirmed' => 1,
    ])->assertSessionHasErrors('claim');

    expect($claim->fresh()->status)->toBe('approved');
});
