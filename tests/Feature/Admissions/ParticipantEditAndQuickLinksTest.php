<?php

use App\Models\AuditEvent;
use App\Models\PortalQuickLink;

require_once __DIR__.'/../../Fixtures/registration.php';

it('allows operators and administrators to edit official sk participant data with audit trail', function () {
    $c = registrationContext();
    $participant = $c['participant'];

    $this->actingAs($c['operator'])->get(route('participants.show', $participant))
        ->assertOk()
        ->assertSee('Edit Data SK')
        ->assertDontSee('Alasan Perubahan Data')
        ->assertSee($participant->name_on_sk);

    $payload = [
        'name_on_sk' => 'Nama Peserta Telah Diperbaiki',
        'sk_number' => 'SK-REVISI-2026',
        'sk_date' => '2026-11-15',
        'sk_order' => 5,
        'official_participant_id' => '999888777',
        'assigned_operator_id' => $c['operator']->id,
        'version' => $participant->lock_version,
    ];

    $initialVersion = $participant->lock_version;

    $this->put(route('participants.update-sk', $participant), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');

    $participant->refresh();
    expect($participant->name_on_sk)->toBe('Nama Peserta Telah Diperbaiki')
        ->and($participant->normalized_name)->toBe('nama peserta telah diperbaiki')
        ->and($participant->sk_number)->toBe('SK-REVISI-2026')
        ->and($participant->sk_date->format('Y-m-d'))->toBe('2026-11-15')
        ->and($participant->sk_order)->toBe(5)
        ->and($participant->official_participant_id)->toBe('999888777')
        ->and($participant->lock_version)->toBe($initialVersion + 1);

    expect(AuditEvent::query()->where('action', 'Memperbarui data resmi SK peserta')->exists())->toBeTrue();

    // Pastikan pendaftar / non-staf dilarang mengubah data SK
    $this->actingAs($c['applicant'])->put(route('participants.update-sk', $participant), $payload)
        ->assertForbidden();
});

it('displays active quick link buttons on dashboard workspace', function () {
    $c = registrationContext();

    $this->actingAs($c['applicant'])->get(route('dashboard'))
        ->assertOk()
        ->assertSee('LMS PPG')
        ->assertSee('UKIN PPG')
        ->assertSee('Web IAKN Palangka Raya')
        ->assertSee('https://lms.iaknpky.ac.id');
});

it('allows managing quick links CRUD and reordering with arrows in settings', function () {
    $c = registrationContext();

    // 1. Tampilkan di pengaturan
    $this->actingAs($c['admin'])->get(route('admin.settings'))
        ->assertOk()
        ->assertSee('Tombol Tautan Cepat')
        ->assertSee('LMS PPG')
        ->assertSee('UKIN PPG');

    // 2. Tambah tautan baru
    $storePayload = [
        'title' => 'Simpatika Kemenag',
        'url' => 'https://simpatika.kemenag.go.id',
        'description' => 'Layanan Simpatika Guru Madrasah & Keagamaan',
        'icon' => 'bi-laptop',
        'button_color' => 'teal',
        'target' => '_blank',
        'is_active' => '1',
    ];

    $this->post(route('admin.quick-links.store'), $storePayload)
        ->assertRedirect(route('admin.settings'))
        ->assertSessionHasNoErrors();

    $newLink = PortalQuickLink::query()->where('title', 'Simpatika Kemenag')->first();
    expect($newLink)->not->toBeNull()
        ->and($newLink->sort_order)->toBeGreaterThan(3);

    // 3. Edit tautan
    $updatePayload = [
        'title' => 'Simpatika Portal Kemenag',
        'url' => 'https://simpatika.kemenag.go.id/portal',
        'description' => 'Portal Layanan Guru',
        'icon' => 'bi-globe2',
        'button_color' => 'green',
        'target' => '_blank',
        'is_active' => '1',
    ];

    $this->put(route('admin.quick-links.update', $newLink), $updatePayload)
        ->assertRedirect(route('admin.settings'))
        ->assertSessionHasNoErrors();

    expect($newLink->fresh()->title)->toBe('Simpatika Portal Kemenag')
        ->and($newLink->fresh()->button_color)->toBe('green');

    // 4. Ubah urutan naik (move up)
    $oldOrder = $newLink->fresh()->sort_order;
    $this->post(route('admin.quick-links.move-up', $newLink))
        ->assertRedirect(route('admin.settings'))
        ->assertSessionHasNoErrors();

    expect($newLink->fresh()->sort_order)->toBeLessThan($oldOrder);

    // 5. Ubah urutan turun (move down)
    $this->post(route('admin.quick-links.move-down', $newLink))
        ->assertRedirect(route('admin.settings'))
        ->assertSessionHasNoErrors();

    // 6. Hapus tautan
    $this->delete(route('admin.quick-links.destroy', $newLink))
        ->assertRedirect(route('admin.settings'))
        ->assertSessionHasNoErrors();

    expect(PortalQuickLink::query()->whereKey($newLink->id)->exists())->toBeFalse();
});

it('renders transfer assignment section as collapsed by default with alpine toggle and renders upload list sidebar menu', function () {
    $c = registrationContext();
    $participant = $c['participant'];

    $response = $this->actingAs($c['operator'])->get(route('participants.show', $participant));
    $response->assertOk()
        ->assertSee('openTransfer: false', false)
        ->assertSee('Buka Formulir')
        ->assertSee('Pindahkan penugasan')
        ->assertSee('Daftar Unggahan');

    // Pastikan urutan di HTML: Antrean Verifikasi lalu Daftar Unggahan
    $content = $response->getContent();
    $antreanPos = strpos($content, 'Antrean Verifikasi');
    $unggahanPos = strpos($content, 'Daftar Unggahan');
    expect($antreanPos)->not->toBeFalse()
        ->and($unggahanPos)->not->toBeFalse()
        ->and($unggahanPos)->toBeGreaterThan($antreanPos);
});
