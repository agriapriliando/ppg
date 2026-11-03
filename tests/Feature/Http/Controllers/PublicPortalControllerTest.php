<?php

use App\Models\AdmissionPeriod;
use App\Models\User;

it('shows an honest empty state without fabricated participants or dates', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Periode penerimaan belum diumumkan')
        ->assertSee('Pendaftaran')
        ->assertDontSee('Ajukan Klaim WhatsApp')
        ->assertDontSee('400')
        ->assertDontSee('Yohanes');
});

it('does not expose draft or archived periods on the public portal', function () {
    AdmissionPeriod::factory()->create(['name' => 'Periode Internal Rahasia', 'is_public' => true]);
    $this->get(route('home'))->assertOk()->assertDontSee('Periode Internal Rahasia');
});

it('renders all implemented administrator pages with the Bootstrap layout', function () {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin);
    foreach (['dashboard', 'admin.periods', 'admin.users', 'admin.settings', 'admin.audit', 'account', 'password.change'] as $route) {
        $this->get(route($route))->assertOk()->assertSee('portal-sidebar')->assertDontSee('Lihat sebagai');
    }
});

it('redirects guests away from administrator pages', function () {
    $this->get(route('admin.periods'))->assertRedirect(route('login'));
});

it('allows applicants to open only their own workspace', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee($user->name);
    $this->get(route('admin.audit'))->assertForbidden();
    $this->get(route('admin.periods'))->assertForbidden();
});
