<?php

use App\Models\AdmissionPeriod;
use App\Models\ApplicationSetting;
use App\Models\PortalQuickLink;
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

it('renders homepage with complete seo meta tags and og image', function () {
    $response = $this->get(route('home'))->assertOk();

    $response->assertSee('<meta name="description"', false)
        ->assertSee('ppg%20iaknpky%20seo.jpg', false)
        ->assertSee('property="og:image"', false)
        ->assertSee('name="twitter:card"', false)
        ->assertSee('https://schema.org', false)
        ->assertSee('EducationalOrganization', false);
});

it('renders the applicant-focused registration guide page correctly', function () {
    $response = $this->get(route('guide'))->assertOk();

    $response->assertSee('Alur Pendaftaran Peserta PPG')
        ->assertSee('Pendaftaran Akun Baru')
        ->assertSee('Pengiriman Akun via WhatsApp')
        ->assertSee('Login dan Unggah Berkas')
        ->assertSee('Pastikan Nomor WhatsApp Anda Benar-benar Aktif')
        ->assertSee('Informasi lanjutan')
        ->assertDontSee('Masuk dengan Google')
        ->assertDontSee('Google')
        ->assertDontSee('Gunakan akun yang telah diprovisikan administrator')
        ->assertDontSee('Siapkan periode, referensi, dan akun operator');
});

it('renders top announcement banner between navbar and hero when notice is active', function () {
    ApplicationSetting::updateOrCreate(
        ['key' => 'public'],
        ['value' => ['notice' => 'Jadwal seleksi berkas gelombang 1 resmi dibuka'], 'version' => 1]
    );

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('top-announcement-wrapper', false)
        ->assertSee('Jadwal seleksi berkas gelombang 1 resmi dibuka')
        ->assertDontSee('PPG Dalam Jabatan · IAKN Palangka Raya');
});

it('renders academic services and important quick links on homepage', function () {
    PortalQuickLink::create([
        'title' => 'Portal SIAKAD IAKN',
        'url' => 'https://siakad.iaknpky.ac.id',
        'description' => 'Sistem Informasi Akademik',
        'icon' => 'bi-laptop',
        'button_color' => 'blue',
        'sort_order' => 10,
        'is_active' => true,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Layanan Akademik', false)
        ->assertSee('Tautan Penting', false)
        ->assertSee('Portal SIAKAD IAKN')
        ->assertSee('https://siakad.iaknpky.ac.id');
});
