<?php

use App\Models\AuditEvent;
use App\Models\User;

require_once __DIR__.'/../../Fixtures/registration.php';

it('paginates audit activity events and supports navigation on riwayat page', function () {
    $c = registrationContext();
    $operator = $c['operator'];

    // Bersihkan audit event lama jika ada
    AuditEvent::query()->delete();

    // Buat tepat 30 event audit untuk operator ini
    for ($i = 1; $i <= 30; $i++) {
        AuditEvent::create([
            'actor_id' => $operator->id,
            'actor_role_snapshot' => $operator->role->value,
            'action' => 'Tindakan Pengujian Riwayat '.$i,
            'subject_type' => User::class,
            'subject_id' => $operator->id,
            'safe_changes' => [],
            'reason' => 'Pengujian pagination riwayat #'.$i,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'request_id' => 'req-test-'.$i,
        ]);
    }

    // Buka halaman riwayat page 1
    $response = $this->actingAs($operator)->get(route('activity'));
    $response->assertOk()
        ->assertSee('Tindakan Pengujian Riwayat 30')
        ->assertSee('Jumlah per halaman:')
        ->assertSee('per_page=10')
        ->assertSee('per_page=25')
        ->assertSee('per_page=50')
        ->assertSee('per_page=100')
        ->assertSee('pagination')
        ->assertSee('page=2');

    // Buka halaman riwayat page 2
    $responsePage2 = $this->actingAs($operator)->get(route('activity', ['page' => 2]));
    $responsePage2->assertOk()
        ->assertSee('Tindakan Pengujian Riwayat 1')
        ->assertSee('page=1');

    // Uji pemilihan jumlah per page: 10 item per halaman
    $responsePerPage10 = $this->actingAs($operator)->get(route('activity', ['per_page' => 10]));
    $responsePerPage10->assertOk()
        ->assertSee('page=3')
        ->assertSee('btn btn-purple">', false);

    // Uji pemilihan jumlah per page: 50 item per halaman (semua 30 item termuat dalam 1 halaman)
    $responsePerPage50 = $this->actingAs($operator)->get(route('activity', ['per_page' => 50]));
    $responsePerPage50->assertOk()
        ->assertSee('Tindakan Pengujian Riwayat 30')
        ->assertSee('Tindakan Pengujian Riwayat 1')
        ->assertDontSee('rel="next"', false);
});

it('allows operators to see all activity events including actions by administrator and other operators', function () {
    $admin = User::factory()->administrator()->create(['name' => 'Admin Utama']);
    $operator = User::factory()->operator()->create(['name' => 'Operator Satu']);
    $otherOperator = User::factory()->operator()->create(['name' => 'Operator Dua']);

    AuditEvent::query()->delete();

    AuditEvent::create([
        'actor_id' => $admin->id,
        'actor_role_snapshot' => 'administrator',
        'action' => 'Aksi oleh Administrator Utama',
        'subject_type' => User::class,
        'subject_id' => $admin->id,
        'safe_changes' => [],
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PHPUnit',
        'request_id' => 'req-admin',
    ]);

    AuditEvent::create([
        'actor_id' => $otherOperator->id,
        'actor_role_snapshot' => 'operator',
        'action' => 'Aksi oleh Operator Dua',
        'subject_type' => User::class,
        'subject_id' => $otherOperator->id,
        'safe_changes' => [],
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PHPUnit',
        'request_id' => 'req-operator-2',
    ]);

    $response = $this->actingAs($operator)->get(route('activity'));
    $response->assertOk()
        ->assertSee('Aksi oleh Administrator Utama')
        ->assertSee('Aksi oleh Operator Dua');

    $responseDashboard = $this->actingAs($operator)->get(route('dashboard'));
    $responseDashboard->assertOk()
        ->assertSee('Aksi oleh Administrator Utama')
        ->assertSee('Aksi oleh Operator Dua');
});
