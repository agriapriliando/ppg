<?php

use App\Models\AdmissionPeriod;
use App\Models\User;

beforeEach(function () {
    // Pastikan tidak ada data periode
    AdmissionPeriod::query()->delete();
});

test('public welcome page renders friendly notice when no period exists', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Periode penerimaan belum diumumkan');
});

test('public claim page renders friendly empty notice when no period exists', function () {
    $this->get(route('public.claim'))
        ->assertOk()
        ->assertSee('Pendaftaran Belum Dibuka')
        ->assertSee('Saat ini belum ada periode pendaftaran mahasiswa baru yang aktif')
        ->assertDontSee('<select id="claim-period"', false);
});

test('administrator dashboard renders informative empty state banner when no period exists', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Belum ada periode pendaftaran di sistem')
        ->assertSee(route('admin.periods'));
});

test('operator dashboard renders informative empty state notice when no period exists', function () {
    $operator = User::factory()->operator()->create();

    $this->actingAs($operator)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Belum ada periode pendaftaran aktif');
});

test('applicant dashboard renders graceful empty state when no period exists', function () {
    $applicant = User::factory()->applicant()->create();

    $this->actingAs($applicant)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Belum ada periode penerimaan');
});

test('document policies page renders empty state card without 404 when no period exists', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)
        ->get(route('admin.document-policies.index'))
        ->assertOk()
        ->assertSee('Belum Ada Periode Pendaftaran')
        ->assertSee(route('admin.periods'));
});

test('sk imports page renders informative notice when no period exists', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)
        ->get(route('admin.imports'))
        ->assertOk()
        ->assertSee('Belum Ada Periode Pendaftaran')
        ->assertSee('Pengunggahan berkas SK memerlukan periode pendaftaran aktif');
});

test('operator assignment page renders informative notice when no period exists', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)
        ->get(route('admin.assignments'))
        ->assertOk()
        ->assertSee('Belum Ada Periode Pendaftaran')
        ->assertSee('Pembagian tugas operator dilakukan per periode pendaftaran');
});

test('bulk assignment page renders informative notice when no period exists', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)
        ->get(route('admin.assignments.bulk'))
        ->assertOk()
        ->assertSee('Belum Ada Periode Pendaftaran')
        ->assertSee('Penugasan massal operator memerlukan periode pendaftaran aktif');
});

test('exports page renders cleanly when no period exists', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)
        ->get(route('admin.exports'))
        ->assertOk()
        ->assertSee('Belum ada periode pendaftaran di sistem');
});

test('configuration history page renders cleanly when no period exists', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)
        ->get(route('admin.configuration-history'))
        ->assertOk()
        ->assertSee('Belum ada periode pendaftaran di sistem');
});

test('portal participants page renders cleanly when no period exists', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)
        ->get(route('participants.index'))
        ->assertOk()
        ->assertSee('Belum ada periode pendaftaran di sistem');
});

test('portal claims page renders cleanly when no period exists', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)
        ->get(route('claims.index'))
        ->assertOk()
        ->assertSee('Belum ada periode pendaftaran aktif');
});

test('portal review queue page renders cleanly when no period exists', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)
        ->get(route('reviews.index'))
        ->assertOk()
        ->assertSee('Belum ada periode pendaftaran aktif');
});

test('admin document uploads page renders cleanly when no period exists', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)
        ->get(route('admin.document-policies.uploads'))
        ->assertOk()
        ->assertSee('Belum ada periode pendaftaran aktif di sistem');
});

test('admin periods management page renders form and empty state when no period exists', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)
        ->get(route('admin.periods'))
        ->assertOk()
        ->assertSee('Belum ada periode')
        ->assertSee('Buat Periode Penerimaan');
});
