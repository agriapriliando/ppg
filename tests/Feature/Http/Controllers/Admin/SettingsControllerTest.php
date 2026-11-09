<?php

use App\Models\ApplicationSetting;
use App\Models\User;

it('publishes approved contact information and escapes untrusted notice text', function () {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin)->put(route('admin.settings.update'), ['helpdesk_name' => 'Helpdesk Resmi', 'helpdesk_whatsapp' => '+628123456789', 'service_hours' => '08.00–16.00', 'notice' => '<script>alert(1)</script>', 'version' => 0])->assertRedirect(route('admin.settings'));
    $this->get(route('home'))->assertSee('Helpdesk Resmi')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    expect(ApplicationSetting::query()->where('key', 'public')->sole()->version)->toBe(1);
});

it('does not overwrite settings using an outdated version', function () {
    $admin = User::factory()->administrator()->create();
    ApplicationSetting::create(['key' => 'public', 'value' => ['helpdesk_name' => 'Versi terkini'], 'version' => 2]);
    $this->actingAs($admin)->put(route('admin.settings.update'), ['helpdesk_name' => 'Versi lama', 'version' => 1])->assertSessionHasErrors('version');
    expect(ApplicationSetting::query()->where('key', 'public')->sole()->value['helpdesk_name'])->toBe('Versi terkini');
});

it('forbids applicants from changing public information', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->put(route('admin.settings.update'), ['helpdesk_name' => 'Penyusup', 'version' => 0])->assertForbidden();
    $this->assertDatabaseCount('application_settings', 0);
});

it('saves announcement placement and shows it to the selected audience', function (string $placement, string $role) {
    $admin = User::factory()->administrator()->create();
    $notice = 'Informasi jadwal terbaru https://kampus.example/pengumuman?gelombang=1&tahun=2026';

    $this->actingAs($admin)->put(route('admin.settings.update'), [
        'helpdesk_name' => 'Helpdesk Resmi',
        'notice' => $notice,
        'notice_placement' => $placement,
        'version' => 0,
    ])->assertRedirect(route('admin.settings'))->assertSessionHasNoErrors();

    expect(ApplicationSetting::query()->where('key', 'public')->sole()->value['notice_placement'])->toBe($placement);
    expect(ApplicationSetting::query()->where('key', 'public')->sole()->value['notice'])->toBe($notice);
    $home = $this->get(route('home'))->assertOk();
    if (in_array($placement, ['dashboard', 'hidden'], true)) {
        $home->assertDontSee('Informasi jadwal terbaru');
    } else {
        $home->assertSee('Informasi jadwal terbaru')->assertSee('href="https://kampus.example/pengumuman?gelombang=1&amp;tahun=2026"', false);
    }

    $user = match ($role) {
        'administrator' => $admin,
        'operator' => User::factory()->operator()->create(),
        default => User::factory()->create(),
    };
    $dashboard = $this->actingAs($user)->get(route('dashboard'))->assertOk();
    if (in_array($placement, ['homepage', 'hidden'], true)) {
        $dashboard->assertDontSee('Informasi jadwal terbaru');
    } else {
        $dashboard->assertSeeInOrder(['PENGUMUMAN PENTING', 'welcome-banner'], false)
            ->assertSee('href="https://kampus.example/pengumuman?gelombang=1&amp;tahun=2026"', false);
    }
})->with(['both', 'homepage', 'dashboard', 'hidden'])->with(['applicant', 'operator', 'administrator']);

it('lets administrators show a previously hidden announcement without rewriting its text', function () {
    $admin = User::factory()->administrator()->create();
    ApplicationSetting::create(['key' => 'public', 'value' => ['helpdesk_name' => 'Helpdesk', 'notice' => 'Pengumuman tersimpan', 'notice_placement' => 'hidden'], 'version' => 1]);

    $this->actingAs($admin)->get(route('admin.settings'))->assertOk()
        ->assertSee('Tidak ditampilkan')->assertSee('Pengumuman tersimpan');
    $this->put(route('admin.settings.update'), [
        'helpdesk_name' => 'Helpdesk', 'notice' => 'Pengumuman tersimpan', 'notice_placement' => 'both', 'version' => 1,
    ])->assertRedirect(route('admin.settings'))->assertSessionHasNoErrors();

    $this->get(route('home'))->assertOk()->assertSee('Pengumuman tersimpan');
    $this->get(route('dashboard'))->assertOk()->assertSee('Pengumuman tersimpan');
});

it('escapes announcement HTML and only links web URLs on homepage and dashboard', function () {
    ApplicationSetting::create(['key' => 'public', 'value' => [
        'notice' => "Baca https://kampus.example/info.\n<script>alert(1)</script> javascript:alert(2) <img src=x onerror=alert(3)>",
    ]]);
    $user = User::factory()->create();

    foreach (['home', 'dashboard'] as $route) {
        $this->actingAs($user)->get(route($route))->assertOk()
            ->assertSee('href="https://kampus.example/info"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x', false)
            ->assertDontSee('href="javascript:', false);
    }
});

it('rejects an unknown announcement placement without saving settings', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)->put(route('admin.settings.update'), [
        'helpdesk_name' => 'Helpdesk Resmi', 'notice_placement' => 'unknown', 'version' => 0,
    ])->assertSessionHasErrors('notice_placement');

    $this->assertDatabaseCount('application_settings', 0);
});

it('hides empty announcements from homepage and dashboard', function () {
    ApplicationSetting::create(['key' => 'public', 'value' => ['notice' => '', 'notice_placement' => 'both']]);
    $user = User::factory()->create();

    $this->get(route('home'))->assertOk()->assertDontSee('PENGUMUMAN PENTING');
    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('PENGUMUMAN PENTING');
});
